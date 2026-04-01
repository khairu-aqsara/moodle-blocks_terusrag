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
 * External API unit tests for TerusRAG.
 *
 * @package    block_terusrag
 * @category   test
 * @copyright  2025 Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/blocks/terusrag/classes/external.php');

/**
 * External API unit tests for TerusRAG.
 *
 * @group block_terusrag
 */
class external_test extends \externallib_advanced_testcase {

    /**
     * Test the submit_query method.
     */
    public function test_submit_query(): void {
        global $DB, $USER;
        $this->resetAfterTest();

        // 1. Setup user and context.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // 2. Setup mock data in block_terusrag table.
        $course = $this->getDataGenerator()->create_course(['fullname' => 'AI Ethics 101']);
        $DB->insert_record('block_terusrag', [
            'moduleid' => $course->id,
            'moduletype' => 'course',
            'title' => 'AI Ethics 101',
            'content' => 'This course covers the ethical implications of artificial intelligence.',
            'contenthash' => sha1('This course covers the ethical implications of artificial intelligence.'),
            'embedding' => serialize(array_fill(0, 3072, 0.1)),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        set_config('provider', 'gemini', 'block_terusrag');
        set_config('gemini_api_key', 'mock_key', 'block_terusrag');

        $query = "Tell me about AI Ethics";
        
        // We expect a moodle_exception because 'mock_key' is not a real API key.
        $this->expectException(\moodle_exception::class);
        \block_terusrag_external::submit_query($query, $course->id);
    }
}
