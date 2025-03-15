<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Open AI provider implementation.
 *
 * @package    block_terusrag
 * @copyright  2023 TerusElearning
 * @author     khairu@teruselearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

use curl;
use moodle_exception;

/**
 * OpenAI API provider implementation for the TerusRAG block.
 */
class openai implements provider_interface {

    /** @var string API key for Gemini services */
    protected string $apikey;

    /** @var string Base URL for the Gemini API */
    protected string $host;

    /** @var string Model name for chat functionality */
    protected string $chatmodel;

    /** @var string Model name for embedding functionality */
    protected string $embeddingmodel;

    /** @var array HTTP headers for API requests */
    protected array $headers;

    /** @var curl HTTP client for API communication */
    protected curl $httpclient;

    /** @var string System prompt to guide model behavior */
    protected string $systemprompt;

    /**
     * Constructor for the OpenAI provider.
     *
     * Initializes the provider with API credentials, model settings,
     * and configures the HTTP client for API communication.
     */
    public function __construct() {
        $apikey = get_config('block_terusrag', 'openai_api_key');
        $host = get_config('block_terusrag', 'openai_endpoint');
        $embeddingmodels = get_config('block_terusrag', 'openai_model_embedding');
        $chatmodels = get_config('block_terusrag', 'openai_model_chat');
        $systemprompt = get_config('block_terusrag', 'system_prompt');

        $this->systemprompt = $systemprompt;
        $this->apikey = $apikey;
        $this->host = $host;
        $this->chatmodel = $chatmodels;
        $this->embeddingmodel = $embeddingmodels;
        $this->headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apikey,
        ];
        $this->httpclient = new curl(['cache' => true, 'module_cache' => 'terusrag']);
        $this->httpclient->setHeader($this->headers);
        $this->httpclient->setopt([
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => false,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 30,
        ]);
    }

    /**
     * Generate embedding vectors for the given text query.
     *
     * @param string|array $query Text to generate embeddings for
     * @return array Array of embedding values
     * @throws moodle_exception If API request fails
     */
    public function get_embedding($query) {
        $payload = [
            'input' => $query,
            'model' => $this->embeddingmodel,
            'encoding_format' => 'float',
        ];

        $response = $this->httpclient->post(
            $this->host . '/embeddings',
            json_encode($payload)
        );

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception('JSON decode error: ' . json_last_error_msg());
        }

        if (isset($data['data']) && is_array($data['data'])) {
            $embeddingsdata = $data['data'][0]['embedding'];
            return $embeddingsdata;
        } else {
            debugging('Open API: Invalid response format: ' . $response);
            throw new moodle_exception('Invalid response from Open API');
        }
    }

    /**
     * Get a response from the Open AI chat model.
     *
     * @param string $prompt The prompt to send to the model
     * @return array The response data from the API
     * @throws moodle_exception If the API request fails
     */
    public function get_response($prompt) {
        $payload = [
            'model' => $this->chatmodel,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt,
                ],
            ],
        ];

        $response = $this->httpclient->post(
            $this->host . '/chat/completions',
            json_encode($payload)
        );

        if ($this->httpclient->get_errno()) {
            $error = $this->httpclient->error;
            debugging('Curl error: ' . $error);
            throw new moodle_exception('Curl error: ' . $error);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception('JSON decode error: ' . json_last_error_msg());
        }

        if (isset($data['choices']) && is_array($data['choices'])) {
            $return = [
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'promptTokenCount' => $data['usage']['prompt_tokens'] ?? 0,
                'responseTokenCount' => $data['usage']['completion_tokens'] ?? 0,
                'totalTokenCount' => $data['usage']['total_tokens'] ?? 0,
            ];
            return $return;
        } else {
            debugging('Open API: Invalid response format: ' . $response);
            throw new moodle_exception('Invalid response from Open API');
        }
    }

    /**
     * Get the top ranked content chunks for a given query.
     *
     * @param string $query The search query
     * @return array The top-ranked content chunks
     */
    public function get_top_ranked_chunks(string $query): array {
        global $DB;
        // 1. Generate embedding for the query.
        $queryembeddingresponse = $this->get_embedding($query);
        $queryembedding = $queryembeddingresponse; // Extract the actual embedding values.
        // 2. Retrieve all content chunks from the database.
        $contentchunks = $DB->get_records('block_terusrag', [], '', 'id, content, embedding');

        // 3. Calculate cosine similarity between the query embedding and each content chunk embedding.
        $chunkscores = [];
        foreach ($contentchunks as $chunk) {
            $chunkembedding = unserialize($chunk->embedding); // Unserialize the embedding.
            if ($chunkembedding) {
                $llm = new llm();
                $similarity = $llm->cosine_similarity($queryembedding, $chunkembedding);
                $chunkscores[$chunk->id] = $similarity;
            } else {
                $chunkscores[$chunk->id] = 0; // If embedding is null, assign a score of 0.
            }
        }

        // 4. Sort the content chunks by cosine similarity.
        arsort($chunkscores);

        // 5. BM25 Re-ranking (Example implementation - adjust as needed).
        $bm25 = new bm25(array_column((array)$contentchunks, 'content', 'id'));
        $bm25scores = [];
        foreach ($contentchunks as $chunk) {
            $bm25scores[$chunk->id] = $bm25->score($query, $chunk->content, $chunk->id);
        }

        // 6. Hybrid Scoring and Re-ranking.
        $hybridscores = [];
        foreach ($chunkscores as $chunkid => $cosinesimilarity) {
            $bm25score = isset($bm25scores[$chunkid]) ? $bm25scores[$chunkid] : 0;
            $hybridscores[$chunkid] = (0.7 * $cosinesimilarity) + (0.3 * $bm25score);
        }
        arsort($hybridscores);

        // 7. Select top N chunks.
        $topnchunkids = array_slice(array_keys($hybridscores), 0, 5, true);
        $topnchunks = [];

        foreach ($topnchunkids as $chunkid) {
            $topnchunks[] = ['content' => $contentchunks[$chunkid]->content, 'id' => $chunkid];
        }

        return $topnchunks;
    }

    /**
     * Process a RAG query with the OpenAI model.
     *
     * @param string $userquery The user's query
     * @return array The processed response
     */
    public function process_rag_query(string $userquery) {
        global $DB;

        $systemprompt = $this->systemprompt;
        $toprankchunks = $this->get_top_ranked_chunks($userquery);
        $contextinjection = "Context:\n" . json_encode($toprankchunks) . "\n\n";
        $prompt = $systemprompt . "\n" . $contextinjection . "Question: " . $userquery . "\nAnswer:";
        $answer = $this->get_response($prompt);

        $response = [
            'answer' => isset($answer['content']) ? $this->parse_response($answer['content']) : [],
            'promptTokenCount' => isset($answer['promptTokenCount']) ? $answer['promptTokenCount'] : 0,
            'responseTokenCount' => isset($answer['responseTokenCount']) ? $answer['responseTokenCount'] : 0,
            'totalTokenCount' => isset($answer['totalTokenCount']) ? $answer['totalTokenCount'] : 0,
        ];
        return $response;
    }

    /**
     * Parse the response from the Gemini API.
     *
     * @param string $response The response from the API
     * @return array Parsed response as an array of lines
     */
    public function parse_response(string $response) {
        $text = trim($response);
        $lines = explode("\n", $text);
        $cleanlines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $cleanlines[] = $this->get_course_from_proper_answer($this->get_proper_answer($line));
            }
        }

        // Filter out items where id is 0 or not set.
        return array_filter($cleanlines, function($item) {
            return isset($item['id']) && $item['id'] != 0;
        });
    }

    /**
     * Format a string answer into a structured response.
     *
     * @param string $originalstring The original response string
     * @return array Structured response with ID and content
     */
    public function get_proper_answer($originalstring) {
        preg_match('/(\d+)/', $originalstring, $matches);
        $id = isset($matches[1]) ? (int)$matches[1] : null;
        $cleanstring = preg_replace('/^\[\d+\]\s*/', '', $originalstring);
        return ['id' => $id, 'content' => $cleanstring];
    }

    /**
     * Get course information from a properly formatted answer.
     *
     * @param array $response The formatted response array
     * @return array Course information with id, title, content, and view URL
     */
    public function get_course_from_proper_answer(array $response) {
        global $DB;
        if ($response) {
            if (isset($response['id'])) {
                $course = $DB->get_record('course', ['id' => $response['id']]);
                $viewurl = $course ? new \moodle_url('/course/view.php', ['id' => $response['id']]) : null;
                return [
                    'id' => $response['id'],
                    'title' => $course ? $course->fullname : 'Unknown Course',
                    'content' => $response['content'],
                    'viewurl' => !is_null($viewurl) ? $viewurl->out() : null,
                ];
            }
        }
        return ['id' => 0, 'title' => 'Unknown Course', 'content' => 'Unknown Course', 'viewurl' => null];
    }

    /**
     * Initializes data by processing courses, chunking content, and generating embeddings.
     *
     * This method retrieves visible courses, processes their content into chunks,
     * generates embeddings for each chunk, and stores the data in the database.
     *
     * @return void
     */
    public function data_initialization() {
        global $DB;
        $courses = $DB->get_records('course', ['visible' => 1], 'id', 'id, fullname, shortname, summary');

        $chunksize = 1024;

        foreach ($courses as $j => $course) {
            $coursecontent = !empty($course->summary) ? $course->summary : $course->fullname;
            $string = strip_tags($coursecontent);

            $stringlength = mb_strlen($string);
            for ($i = 0; $i < $stringlength; $i += $chunksize) {
                $chunk = mb_substr($string, $i, $chunksize);
                $embeddingsdata = $this->get_embedding($chunk);
                if (count($embeddingsdata) > 0) {
                    $contenthash = sha1($chunk);
                    $coursellm = [
                        'title' => $course->fullname,
                        'moduletype' => 'course',
                        'moduleid' => $course->id,
                        'content' => $chunk,
                        'contenthash' => $contenthash,
                        'embedding' => serialize($embeddingsdata),
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ];

                    $isexists = $DB->get_record('block_terusrag', [
                            'contenthash' => $contenthash,
                            'moduleid' => $coursellm['moduleid'],
                        ]
                    );

                    if ($isexists) {
                        $coursellm['id'] = $isexists->id;
                        $DB->update_record('block_terusrag', (object)$coursellm);
                    } else {
                        $DB->insert_record('block_terusrag', (object)$coursellm);
                    }
                }
            }
        }

    }

}
