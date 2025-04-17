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

        // Generate embedding for the query.
        $queryembeddingresponse = $this->get_embedding($query);
        $queryembedding = $queryembeddingresponse;

        // Process chunks in batches to manage memory.
        $batchsize = 500;
        $chunkscores = [];
        $bm25scores = [];
        $contentarray = [];
        $sql = "SELECT id, content, embedding FROM {block_terusrag}";
        $rs = $DB->get_recordset_sql($sql);

        try {
            $batch = [];
            $llm = new llm();
            $documents = [];

            // First pass - collect documents for BM25 indexing.
            foreach ($rs as $record) {
                $documents[$record->id] = $record->content;
                $contentarray[$record->id] = $record->content;
            }

            // Initialize BM25 with collected documents.
            $bm25 = new bm25($documents);

            // Reset recordset for second pass.
            $rs->close();
            $rs = $DB->get_recordset_sql($sql);

            // Second pass - process chunks.
            foreach ($rs as $record) {
                $batch[] = $record;
                $contentarray[$record->id] = $record->content;

                if (count($batch) >= $batchsize) {
                    $this->process_chunk_batch($batch, $queryembedding, $query, $llm, $bm25, $chunkscores, $bm25scores);
                    $batch = [];
                    gc_collect_cycles();
                }
            }

            // Process remaining chunks.
            if (!empty($batch)) {
                $this->process_chunk_batch($batch, $queryembedding, $query, $llm, $bm25, $chunkscores, $bm25scores);
            }

            // Hybrid scoring and ranking.
            $hybridscores = [];
            foreach ($chunkscores as $chunkid => $cosinesimilarity) {
                $bm25score = $bm25scores[$chunkid] ?? 0;
                $hybridscores[$chunkid] = 0.7 * $cosinesimilarity + 0.3 * $bm25score;
            }
            arsort($hybridscores);

            // Select top 5 chunks.
            $topnchunkids = array_slice(array_keys($hybridscores), 0, 5, true);
            $topnchunks = [];

            foreach ($topnchunkids as $chunkid) {
                $topnchunks[] = [
                    "content" => $contentarray[$chunkid],
                    "id" => $chunkid,
                ];
            }

            return $topnchunks;

        } finally {
            $rs->close();
        }
    }

    /**
     * Process a batch of chunks for similarity scoring and ranking.
     *
     * @param array $batch Array of database records containing chunks to process
     * @param array $queryembedding Query embedding vector for similarity comparison as a numeric array
     * @param string $query Original query string for BM25 scoring
     * @param \block_terusrag\llm $llm LLM helper instance for similarity calculations
     * @param \block_terusrag\bm25 $bm25 BM25 ranking instance for text relevancy scoring
     * @param array &$chunkscores Reference to array storing chunk similarity scores, indexed by chunk ID
     * @param array &$bm25scores Reference to array storing BM25 scores, indexed by chunk ID
     * @return void
     */
    protected function process_chunk_batch($batch, $queryembedding, $query, $llm, $bm25, &$chunkscores, &$bm25scores) {
        foreach ($batch as $chunk) {
            $chunkembedding = unserialize($chunk->embedding);
            if ($chunkembedding) {
                $chunkscores[$chunk->id] = $llm->cosine_similarity($queryembedding, $chunkembedding);
                $bm25scores[$chunk->id] = $bm25->score($query, $chunk->content, $chunk->id);
            } else {
                $chunkscores[$chunk->id] = 0;
                $bm25scores[$chunk->id] = 0;
            }
        }
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

        // Process courses in chunks of 100 to manage memory.
        $batchsize = 100;
        $lastprocessedtime = get_config('block_terusrag', 'last_processed_time') ?? 0;
        $currenttime = time();
        $chunksize = 1024;

        $sql = "SELECT c.id, c.fullname, c.shortname, c.summary, c.timemodified"
             . " FROM {course} c"
             . " WHERE c.visible = 1"
             . " AND c.timemodified > ?"
             . " ORDER BY c.id";

        $rs = $DB->get_recordset_sql($sql, [$lastprocessedtime]);

        $coursebatch = [];
        $processedcount = 0;

        try {
            foreach ($rs as $course) {
                $coursecontent = !empty($course->summary) ? $course->summary : $course->fullname;
                $string = strip_tags($coursecontent);

                // Process in batches to optimize API calls and memory usage.
                $coursebatch[] = [
                    'content' => $string,
                    'title' => $course->fullname,
                    'moduleid' => $course->id,
                    'timemodified' => $course->timemodified,
                ];

                if (count($coursebatch) >= $batchsize) {
                    $this->process_course_batch($coursebatch, $chunksize);
                    $coursebatch = [];
                    $processedcount += $batchsize;

                    // Free up memory.
                    gc_collect_cycles();
                }
            }

            // Process any remaining courses.
            if (!empty($coursebatch)) {
                $this->process_course_batch($coursebatch, $chunksize);
                $processedcount += count($coursebatch);
            }

            // Update last processed time.
            set_config('last_processed_time', $currenttime, 'block_terusrag');

        } finally {
            $rs->close();
        }
    }

    /**
     * Process a batch of courses for embedding generation
     *
     * @param array $coursebatch Array of courses to process
     * @param int $chunksize Size of content chunks
     * @return void
     */
    protected function process_course_batch($coursebatch, $chunksize) {
        global $DB;

        $chunks = [];
        $chunkmap = [];  // Maps chunk index to course data.

        // Prepare chunks for batch embedding.
        foreach ($coursebatch as $index => $coursedata) {
            $string = $coursedata['content'];
            $stringlength = mb_strlen($string);

            for ($i = 0; $i < $stringlength; $i += $chunksize) {
                $chunkindex = count($chunks);
                $chunk = mb_substr($string, $i, $chunksize);
                $chunks[] = $chunk;
                $chunkmap[$chunkindex] = [
                    'title' => $coursedata['title'],
                    'moduleid' => $coursedata['moduleid'],
                    'content' => $chunk,
                    'contenthash' => sha1($chunk),
                ];
            }
        }

        // Get embeddings for all chunks in batch.
        if (!empty($chunks)) {
            $embeddingsdata = $this->get_embedding($chunks);

            // Process each embedding and update database.
            foreach ($embeddingsdata as $i => $embedding) {
                if (!isset($chunkmap[$i])) {
                    continue;
                }

                $coursellm = $chunkmap[$i];
                $coursellm['embedding'] = serialize($embedding);
                $coursellm['timecreated'] = time();
                $coursellm['timemodified'] = time();
                $coursellm['moduletype'] = 'course';

                // Check if record exists and update/insert accordingly.
                $record = $DB->get_record('block_terusrag', [
                    'contenthash' => $coursellm['contenthash'],
                    'moduleid' => $coursellm['moduleid'],
                ]);

                if ($record) {
                    $coursellm['id'] = $record->id;
                    $DB->update_record('block_terusrag', (object)$coursellm);
                } else {
                    $DB->insert_record('block_terusrag', (object)$coursellm);
                }
            }
        }
    }
}
