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
 * Parsing logic unit tests for TerusRAG.
 *
 * @package    block_terusrag
 * @category   test
 * @copyright  2025 Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

defined('MOODLE_INTERNAL') || die();

/**
 * Parsing logic unit tests for TerusRAG.
 *
 * @group block_terusrag
 */
class parsing_test extends \advanced_testcase {

    /**
     * Test the get_proper_answer logic for different formats.
     */
    public function test_id_extraction_formats(): void {
        $this->resetAfterTest();
        $gemini = new \block_terusrag\gemini();

        // 1. Bracket format [ID]
        $res = $gemini->get_proper_answer("[101] This is a test.");
        $this->assertEquals(101, $res['id']);
        $this->assertEquals("This is a test.", $res['content']);

        // 2. Parentheses format (ID)
        $res = $gemini->get_proper_answer("(55) Testing parentheses.");
        $this->assertEquals(55, $res['id']);
        $this->assertEquals("Testing parentheses.", $res['content']);

        // 3. ID label format ID: 
        $res = $gemini->get_proper_answer("id: 42 - Label test.");
        $this->assertEquals(42, $res['id']);
        $this->assertEquals("- Label test.", $res['content']);

        // 4. Chunk label format Chunk:
        $res = $gemini->get_proper_answer("Chunk 789 - Chunk test.");
        $this->assertEquals(789, $res['id']);
        $this->assertEquals("- Chunk test.", $res['content']);

        // 5. Bare number format - expect debugging message.
        $res = $gemini->get_proper_answer("123 Bare number test.");
        $this->assertDebuggingCalled();
        $this->assertEquals(123, $res['id']);
        $this->assertEquals("Bare number test.", $res['content']);
    }

    /**
     * Test deduplication across all providers.
     */
    public function test_parse_response_filtering(): void {
        $this->resetAfterTest();
        $gemini = new \block_terusrag\gemini();

        // One valid line, one invalid chunk id but has content, one empty line with only ID (filtered)
        $raw = "[1] Valid content.\n\n[9999] Fallback content.\n(0) ";
        
        global $DB;
        $DB->insert_record('block_terusrag', ['id' => 1, 'moduleid' => 1, 'moduletype' => 'course', 'title' => 'T1', 'content' => 'C1']);

        $result = $gemini->parse_response($raw);
        $this->assertDebuggingCalledCount(4);

        // Expect 2 items: 1 found, 1 fallback. The line with no content text should be filtered.
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('Valid content.', $result[0]['content']);
    }
}
