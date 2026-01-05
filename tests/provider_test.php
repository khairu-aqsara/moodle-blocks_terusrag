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

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot . '/blocks/terusrag/tests/mock_provider.php');

/**
 * Provider test case.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_terusrag\provider
 */
class provider_test extends \advanced_testcase {

    /** @var mock_provider */
    private $provider;

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->provider = new mock_provider();
    }

    /**
     * Test embedding generation.
     *
     * @covers ::get_embedding
     */
    public function test_get_embedding() {
        // Test single input.
        $query = "Test query";
        $mockembedding = array_fill(0, 384, 0.5);
        $this->provider->set_mock_embedding($query, $mockembedding);
        $result = $this->provider->get_embedding($query);
        $this->assertEquals($mockembedding, $result);

        // Test batch input.
        $queries = ["Query 1", "Query 2"];
        $mockembeddings = [
            array_fill(0, 384, 0.3),
            array_fill(0, 384, 0.7),
        ];
        $this->provider->set_mock_embedding($queries, $mockembeddings);
        $result = $this->provider->get_embedding($queries);
        $this->assertEquals($mockembeddings, $result);

        // Test default embedding.
        $result = $this->provider->get_embedding("Unknown query");
        $this->assertEquals(array_fill(0, 384, 0.0), $result);
    }

    /**
     * Test LLM response generation.
     *
     * @covers ::get_response
     */
    public function test_get_response() {
        // Test with mock response.
        $prompt = "Test prompt";
        $mockresponse = [
            'content' => 'Custom mock response',
            'promptTokenCount' => 15,
            'responseTokenCount' => 25,
            'totalTokenCount' => 40,
        ];
        $this->provider->set_mock_response($prompt, $mockresponse);
        $result = $this->provider->get_response($prompt);
        $this->assertEquals($mockresponse, $result);

        // Test default response.
        $result = $this->provider->get_response("Unknown prompt");
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('promptTokenCount', $result);
        $this->assertArrayHasKey('responseTokenCount', $result);
        $this->assertArrayHasKey('totalTokenCount', $result);
    }

    /**
     * Test RAG query processing.
     *
     * @covers ::process_rag_query
     */
    public function test_process_rag_query() {
        $result = $this->provider->process_rag_query("What courses are available?");

        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('promptTokenCount', $result);
        $this->assertArrayHasKey('responseTokenCount', $result);
        $this->assertArrayHasKey('totalTokenCount', $result);

        $answer = $result['answer'][0];
        $this->assertArrayHasKey('id', $answer);
        $this->assertArrayHasKey('title', $answer);
        $this->assertArrayHasKey('content', $answer);
        $this->assertArrayHasKey('viewurl', $answer);
    }

    /**
     * Test data initialization.
     *
     * @covers ::data_initialization
     */
    public function test_data_initialization() {
        global $DB;

        // Run initialization.
        $this->provider->data_initialization();

        // Verify course was created.
        $courses = $DB->get_records('course');
        $this->assertCount(2, $courses); // Default site course + our test course.

        // Find our test course.
        $testcourse = $DB->get_record('course', ['shortname' => 'TEST101']);
        $this->assertNotFalse($testcourse);

        // Verify embedding record was created.
        $embedding = $DB->get_record('block_terusrag', ['moduleid' => $testcourse->id]);
        $this->assertNotFalse($embedding);
        $this->assertEquals('course', $embedding->moduletype);
        $this->assertEquals($testcourse->fullname, $embedding->title);

        // Verify embedding data.
        $embeddingdata = unserialize($embedding->embedding);
        $this->assertIsArray($embeddingdata);
        $this->assertCount(384, $embeddingdata);
    }

    /**
     * Test getting top ranked chunks.
     *
     * @covers ::get_top_ranked_chunks
     */
    public function test_get_top_ranked_chunks() {
        $result = $this->provider->get_top_ranked_chunks("test query");

        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $chunk = $result[0];
        $this->assertArrayHasKey('content', $chunk);
        $this->assertArrayHasKey('id', $chunk);
        $this->assertEquals('Mock chunk content for testing', $chunk['content']);
        $this->assertEquals(1, $chunk['id']);
    }
}
