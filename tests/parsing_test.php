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

/**
 * Parsing logic unit tests for TerusRAG.
 *
 * @group block_terusrag
 * @covers \block_terusrag\gemini
 */
final class parsing_test extends \advanced_testcase {
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
     * Test deduplication / filtering across the parsing pipeline.
     */
    public function test_parse_response_filtering(): void {
        $this->resetAfterTest();
        $gemini = new \block_terusrag\gemini();

        global $DB;
        $DB->insert_record('block_terusrag', [
            'moduleid' => 1,
            'moduletype' => 'course',
            'title' => 'T1',
            'content' => 'C1',
            'contenthash' => sha1('C1'),
        ]);

        // One valid line, one unknown chunk id but with content (kept as
        // fallback), and one line with only an ID and no text (filtered).
        $raw = "[1] Valid content.\n\n[9999] Fallback content.\n(0) ";

        $result = $gemini->parse_response($raw);

        // Expect 2 items: 1 resolved, 1 unverified-but-has-content. The line
        // carrying only an ID (no text) is dropped.
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('Valid content.', $result[0]['content']);
        $this->assertEquals('Fallback content.', $result[1]['content']);

        // Several DEBUG_DEVELOPER messages are emitted along the way; clear them
        // so the test does not fail on unexpected debugging at teardown.
        $this->resetDebugging();
    }

    /**
     * Gemini 2.5 "thinking" parts must be ignored so only the answer is parsed.
     */
    public function test_thought_parts_are_skipped(): void {
        $this->resetAfterTest();
        global $DB;

        $gemini = new \block_terusrag\gemini();

        // Index a chunk that maps to the site course so the id resolves cleanly.
        $chunkid = $DB->insert_record('block_terusrag', [
            'moduleid' => 1,
            'moduletype' => 'course',
            'title' => 'Site',
            'content' => 'Site content',
            'contenthash' => sha1('Site content'),
        ]);

        // Mimic a Gemini response where the model's reasoning is returned as a
        // separate part flagged thought=true, followed by the real answer.
        $candidates = [
            [
                'content' => [
                    'parts' => [
                        ['text' => "Reasoning line one.\nMore reasoning 42.", 'thought' => true],
                        ['text' => "[{$chunkid}] The real answer."],
                    ],
                ],
            ],
        ];

        $result = $gemini->parse_response($candidates);

        // If thought text leaked in, the reasoning lines would create extra
        // items (and a wrong id from "42"). Exactly one clean item proves the
        // thought part was skipped.
        $this->assertCount(1, $result);
        $this->assertEquals((int) $chunkid, $result[0]['id']);
        $this->assertEquals('The real answer.', $result[0]['content']);

        $this->resetDebugging();
    }

    /**
     * When the model output carries no usable answer text per the [id] format,
     * the raw output is surfaced as a single fallback item (never empty).
     */
    public function test_raw_fallback_when_no_formatted_content(): void {
        $this->resetAfterTest();
        $gemini = new \block_terusrag\gemini();

        // Two lines that are only chunk IDs with no answer text — every parsed
        // line is filtered for empty content, so the fallback must kick in.
        $raw = "[5]\n[6]";

        $result = $gemini->parse_response($raw);

        $this->assertCount(1, $result);
        $this->assertEquals(0, $result[0]['id']);
        $this->assertEquals(get_string('airesponse', 'block_terusrag'), $result[0]['title']);
        $this->assertEquals($raw, $result[0]['content']);
        $this->assertNull($result[0]['viewurl']);

        $this->resetDebugging();
    }
}
