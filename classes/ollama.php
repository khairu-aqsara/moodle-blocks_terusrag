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
 * Ollama provider implementation.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     khairu@teruselearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

use curl;
use moodle_exception;

/**
 * Ollama API provider implementation for the TerusRAG block.
 */
class ollama implements provider_interface {

    /** @var string API key for Ollama services */
    protected string $apikey;

    /** @var string Base URL for the Ollama API */
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

    /** @var bool Whether to prompt for optimization */
    protected bool $promptoptimization = false;

    /**
     * Constructor initializes the Ollama API client.
     */
    public function __construct() {
        $apikey = get_config("block_terusrag", "ollama_api_key");
        $host = get_config("block_terusrag", "ollama_endpoint");
        $embeddingmodels = get_config(
            "block_terusrag",
            "ollama_model_embedding"
        );
        $chatmodels = get_config("block_terusrag", "ollama_model_chat");
        $systemprompt = get_config("block_terusrag", "system_prompt");
        $promptoptimization = get_config("block_terusrag", "optimizeprompt");

        $this->systemprompt = $systemprompt;
        $this->apikey = $apikey;
        $this->host = $host;
        $this->chatmodel = $chatmodels;
        $this->embeddingmodel = $embeddingmodels;
        $this->promptoptimization = $promptoptimization === 'yes' ? true : false;
        $this->headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->apikey,
        ];
        $this->httpclient = new curl([
            "cache" => true,
            "module_cache" => "terusrag",
            "debug" => false,
        ]);
        $this->httpclient->setHeader($this->headers);
        $this->httpclient->setopt([
            "CURLOPT_SSL_VERIFYPEER" => false,
            "CURLOPT_SSL_VERIFYHOST" => false,
            "CURLOPT_TIMEOUT" => 30,
            "CURLOPT_CONNECTTIMEOUT" => 30,
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
            "model" => $this->embeddingmodel,
            "input" => $query,
        ];

        $response = $this->httpclient->post(
            $this->host . "/api/embed/",
            json_encode($payload)
        );

        if ($this->httpclient->get_errno()) {
            $error = $this->httpclient->error;
            throw new moodle_exception("Curl error: " . $error);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception(
                "JSON decode error: " . json_last_error_msg()
            );
        }

        if (isset($data["embeddings"]) && is_array($data["embeddings"])) {
            $embeddingsdata = $data["embeddings"];
            return is_array($query)
                ? $embeddingsdata
                : $embeddingsdata[0];
        } else {
            debugging("Ollama API: Invalid response format: " . $response);
            throw new moodle_exception("Invalid response from Ollama API");
        }
    }

    /**
     * Get a response from the Ollama chat model.
     *
     * @param string $prompt The prompt to send to the model
     * @return array The response data from the API
     * @throws moodle_exception If the API request fails
     */
    public function get_response($prompt) {
        $payload = [
            "model" => $this->chatmodel,
            "prompt" => $prompt,
            "stream" => false,
        ];

        $response = $this->httpclient->post(
            $this->host .
                "/api/generate",
            json_encode($payload)
        );

        if ($this->httpclient->get_errno()) {
            $error = $this->httpclient->error;
            debugging("Curl error: " . $error);
            throw new moodle_exception("Curl error: " . $error);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception(
                "JSON decode error: " . json_last_error_msg()
            );
        }

        return $data;
    }

    /**
     * Process a RAG query with the Ollama model.
     *
     * @param string $userquery The user's query
     * @return array The processed response
     */
    public function process_rag_query(string $userquery) {
        global $DB;

        // 1. System Prompt (Define role and behavior).
        $systemprompt = $this->systemprompt;

        // 1.1 Optimize User Prompt.
        if ($this->promptoptimization) {
            $llm = new llm();
            $userquery = $llm->optimize_prompt($userquery);
            $userquery = (isset($userquery["optimized_prompt"]) && !empty($userquery["optimized_prompt"]))
                ? $userquery["optimized_prompt"]
                : $userquery;
        }

        // 2. Retrieve relevant chunks.
        $toprankchunks = $this->get_top_ranked_chunks($userquery);

        // 3. Context Injection.
        $contextinjection = "Context:\n" . json_encode($toprankchunks) . "\n\n";

        // 4. User Query.
        $prompt =
            $systemprompt .
            "\n" .
            $contextinjection .
            "Question: " .
            $userquery .
            "\nAnswer:";

        // 5. API Call to Ollama.
        $answer = $this->get_response($prompt);
        $response = [
            "answer" => isset($answer["response"])
                ? $this->parse_response($answer["response"])
                : [],
            "promptTokenCount" => isset($answer["prompt_eval_count"])
                ? $answer["prompt_eval_count"]
                : 0,
            "responseTokenCount" => isset($answer["eval_count"])
                ? $answer["eval_count"]
                : 0,
            "totalTokenCount" => isset($answer["prompt_eval_count"]) && isset($answer["eval_count"])
                ? $answer["prompt_eval_count"] + $answer["eval_count"]
                : 0,
        ];

        // Log if unexpected response structure is received.
        if (!isset($answer["response"]) || !isset($answer["usageMetadata"])) {
            debugging(
                "Ollama API returned unexpected response structure: " .
                    json_encode($answer),
                DEBUG_DEVELOPER
            );
        }

        return $response;
    }

    /**
     * Extract all text from a nested response array.
     *
     * @param array $array The response array to process
     * @param string $result The accumulated result string
     * @return string The extracted text
     */
    public function extract_all_text_response($array, &$result = "") {
        foreach ($array as $key => $value) {
            if ($key === "text" && is_string($value)) {
                $result .= $value . " ";
            } else if (is_array($value)) {
                $this->extract_all_text_response($value, $result);
            }
        }
        return $result;
    }

    /**
     * Parse the response from the Ollama API.
     *
     * @param string $response The response from the API
     * @return array Parsed response as an array of lines
     */
    public function parse_response($response) {
        $text = trim($response);
        // Split by newline and clean up each line.
        $lines = explode("\n", $text);
        $cleanlines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $cleanlines[] = $this->get_course_from_proper_answer(
                    $this->get_proper_answer($line)
                );
            }
        }

        // Filter out items where id is 0 or not set.
        return array_filter($cleanlines, function ($item) {
            return isset($item["id"]) && $item["id"] != 0 && !is_null($item["viewurl"]);
        });
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
            if (isset($response["id"])) {
                $course = $DB->get_record("course", ["id" => $response["id"]]);
                $viewurl = $course
                    ? new \moodle_url("/course/view.php", [
                        "id" => $response["id"],
                    ])
                    : null;
                return [
                    "id" => $response["id"],
                    "title" => $course ? $course->fullname : "Unknown Course",
                    "content" => $response["content"],
                    "viewurl" => !is_null($viewurl) ? $viewurl->out() : null,
                ];
            }
        }
        return [
            "id" => 0,
            "title" => "Unknown Course",
            "content" => "Unknown Course",
            "viewurl" => null,
        ];
    }

    /**
     * Format a string answer into a structured response.
     *
     * @param string $originalstring The original response string
     * @return array Structured response with ID and content
     */
    public function get_proper_answer($originalstring) {
        preg_match("/(\d+)/", $originalstring, $matches);
        $id = isset($matches[1]) ? (int) $matches[1] : null;
        $cleanstring = preg_replace("/^\[\d+\]\s*/", "", $originalstring);
        return ["id" => $id, "content" => $cleanstring];
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
        $contentchunks = $DB->get_records(
            "block_terusrag",
            [],
            "",
            "id, content, embedding"
        );

        // 3. Calculate cosine similarity between the query embedding and each content chunk embedding.
        $chunkscores = [];
        foreach ($contentchunks as $chunk) {
            $chunkembedding = unserialize($chunk->embedding); // Unserialize the embedding.
            if ($chunkembedding) {
                $llm = new llm();
                $similarity = $llm->cosine_similarity(
                    $queryembedding,
                    $chunkembedding
                );
                $chunkscores[$chunk->id] = $similarity;
            } else {
                $chunkscores[$chunk->id] = 0; // If embedding is null, assign a score of 0.
            }
        }

        // 4. Sort the content chunks by cosine similarity.
        arsort($chunkscores);

        // 5. BM25 Re-ranking (Example implementation - adjust as needed).
        $bm25 = new bm25(array_column((array) $contentchunks, "content", "id"));
        $bm25scores = [];
        foreach ($contentchunks as $chunk) {
            $bm25scores[$chunk->id] = $bm25->score(
                $query,
                $chunk->content,
                $chunk->id
            );
        }

        // 6. Hybrid Scoring and Re-ranking.
        $hybridscores = [];
        foreach ($chunkscores as $chunkid => $cosinesimilarity) {
            $bm25score = isset($bm25scores[$chunkid])
                ? $bm25scores[$chunkid]
                : 0;
            $hybridscores[$chunkid] =
                0.7 * $cosinesimilarity + 0.3 * $bm25score;
        }
        arsort($hybridscores);

        // 7. Select top N chunks.
        $topnchunkids = array_slice(array_keys($hybridscores), 0, 5, true);
        $topnchunks = [];

        foreach ($topnchunkids as $chunkid) {
            $topnchunks[] = [
                "content" => $contentchunks[$chunkid]->content,
                "id" => $chunkid,
            ];
        }

        return $topnchunks;
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
        $courses = $DB->get_records(
            "course",
            ["visible" => 1],
            "id",
            "id, fullname, shortname, summary"
        );

        $chunksize = 1024;
        $chunk = [];
        $coursesindex = [];
        foreach ($courses as $j => $course) {
            $coursecontent = !empty($course->summary)
                ? $course->summary
                : $course->fullname;
            $string = strip_tags($coursecontent);
            $contenthash = sha1($string);

            $coursesindex[$j] = [
                "title" => $course->fullname,
                "moduletype" => "course",
                "moduleid" => $course->id,
                "content" => $string,
                "contenthash" => $contenthash,
                "embedding" => "",
                "chunkindex" => [],
                "timecreated" => time(),
                "timemodified" => time(),
            ];

            $stringlength = mb_strlen($string);
            for ($i = 0; $i < $stringlength; $i += $chunksize) {
                $chunkindex = count($chunk);
                $chunk[] = mb_substr($string, $i, $chunksize);
                $coursesindex[$j]["chunkindex"][] = $chunkindex;
            }
        }

        $embeddingsdata = $this->get_embedding($chunk);
        if (count($embeddingsdata) > 0) {
            foreach ($embeddingsdata as $i => $embedding) {
                // Extract values or use the embedding directly if it's already the values array.
                $values =
                    is_array($embedding) && isset($embedding["values"])
                        ? $embedding["values"]
                        : $embedding;
                // Find which course this embedding belongs to.
                foreach ($coursesindex as $coursekey => &$coursedata) {
                    if (in_array($i, $coursedata["chunkindex"])) {
                        // Add this specific embedding to the course data with its chunk index as key.
                        if (!isset($coursedata["embeddings"])) {
                            $coursedata["embeddings"] = [];
                        }
                        // Store embedding with its index as key.
                        $coursedata["embedding"] = serialize($values);
                        break; // Once found, no need to check other courses.
                    }
                }
            }

            foreach ($coursesindex as $coursellm) {
                $hash = $coursellm["contenthash"];
                $isexists = $DB->get_record("block_terusrag", [
                    "contenthash" => $hash,
                    "moduleid" => $coursellm["moduleid"],
                ]);
                if ($isexists) {
                    $coursellm["id"] = $isexists->id;
                    unset($coursellm["chunkindex"]);
                    $DB->update_record("block_terusrag", (object) $coursellm);
                } else {
                    unset($coursellm["chunkindex"]);
                    $DB->insert_record("block_terusrag", (object) $coursellm);
                }
            }
        }
    }
}
