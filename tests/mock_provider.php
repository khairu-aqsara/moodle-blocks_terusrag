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

namespace block_terusrag;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/terusrag/classes/provider_interface.php');

/**
 * Mock provider implementation for testing.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_provider implements provider_interface {
    /** @var array Mock embeddings storage */
    private array $mockembeddings;

    /** @var array Mock responses storage */
    private array $mockresponses;

    /**
     * Constructor initializes mock data.
     */
    public function __construct() {
        $this->mockembeddings = [];
        $this->mockresponses = [];
    }

    /**
     * Set mock embedding for a specific input.
     *
     * @param string|array $input The input text
     * @param array $embedding The embedding to return
     */
    public function set_mock_embedding($input, array $embedding) {
        $key = is_array($input) ? json_encode($input) : $input;
        $this->mockembeddings[$key] = $embedding;
    }

    /**
     * Set mock response for a specific prompt.
     *
     * @param string $prompt The input prompt
     * @param array $response The response to return
     */
    public function set_mock_response(string $prompt, array $response) {
        $this->mockresponses[$prompt] = $response;
    }

    /**
     * Get embedding vectors for the given text.
     *
     * @param string|array $query Text to generate embeddings for
     * @return array Array of embedding values
     */
    public function get_embedding($query) {
        $key = is_array($query) ? json_encode($query) : $query;
        return $this->mockembeddings[$key] ?? array_fill(0, 384, 0.0);
    }

    /**
     * Get a response from the mock provider.
     *
     * @param mixed $prompt The prompt to send
     * @return array The mock response
     */
    public function get_response($prompt): array {
        return $this->mockresponses[$prompt] ?? [
            'content' => 'Mock response',
            'promptTokenCount' => 10,
            'responseTokenCount' => 20,
            'totalTokenCount' => 30,
        ];
    }

    /**
     * Process a RAG query with mock data.
     *
     * @param string $userquery The user's query
     * @return array The processed response
     */
    public function process_rag_query(string $userquery) {
        return [
            'answer' => [
                [
                    'id' => 1,
                    'title' => 'Mock Course',
                    'content' => 'Mock content for testing',
                    'viewurl' => new \moodle_url('/course/view.php', ['id' => 1]),
                ],
            ],
            'promptTokenCount' => 10,
            'responseTokenCount' => 20,
            'totalTokenCount' => 30,
        ];
    }

    /**
     * Initialize mock data.
     */
    public function data_initialization() {
        global $DB;

        // Create a test course with mock data.
        $course = (object)[
            'fullname' => 'Test Course',
            'shortname' => 'TEST101',
            'summary' => 'This is a test course for unit testing',
            'visible' => 1,
        ];

        $courseid = $DB->insert_record('course', $course);

        // Create mock embeddings for the course.
        $content = $course->summary;
        $embedding = array_fill(0, 384, 0.1);

        $record = (object)[
            'contenthash' => sha1($content),
            'content' => $content,
            'embedding' => serialize($embedding),
            'moduleid' => $courseid,
            'moduletype' => 'course',
            'title' => $course->fullname,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $DB->insert_record('block_terusrag', $record);
    }

    /**
     * Get mock top ranked chunks.
     *
     * @param string $query The search query
     * @return array The top-ranked content chunks
     */
    public function get_top_ranked_chunks(string $query): array {
        return [
            [
                'content' => 'Mock chunk content for testing',
                'id' => 1,
            ],
        ];
    }
}
