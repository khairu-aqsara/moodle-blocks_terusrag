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
 * @covers \block_terusrag_external
 */
final class external_test extends \externallib_advanced_testcase {
    /**
     * An empty content index returns a 'warning' status, not an exception.
     */
    public function test_submit_query_empty_index_warns(): void {
        $this->resetAfterTest();

        // A plain authenticated user must be allowed to query (block/terusrag:view).
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $response = \block_terusrag_external::submit_query('Tell me about anything', 0);

        $this->assertSame('warning', $response['status']);
        $this->assertSame([], $response['answer']);
        $this->assertSame(0, $response['totalTokenCount']);
        $this->assertNotEmpty($response['statusMessage']);

        // The empty-index path emits a DEBUG_DEVELOPER notice; clear it.
        $this->resetDebugging();
    }

    /**
     * A misconfigured / unsupported provider is caught and surfaced as an
     * 'error' status rather than bubbling an uncaught exception to the client.
     */
    public function test_submit_query_unsupported_provider_errors(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Populate the index so the empty-index guard passes.
        $course = $this->getDataGenerator()->create_course(['fullname' => 'AI Ethics 101']);
        $content = 'This course covers the ethical implications of artificial intelligence.';
        $DB->insert_record('block_terusrag', [
            'moduleid'     => $course->id,
            'moduletype'   => 'course',
            'title'        => 'AI Ethics 101',
            'content'      => $content,
            'contenthash'  => sha1($content),
            'embedding'    => serialize(array_fill(0, 8, 0.1)),
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        // Deliberately invalid provider -> coding_exception caught internally.
        set_config('aiprovider', 'bogus_provider', 'block_terusrag');

        $response = \block_terusrag_external::submit_query('Tell me about AI Ethics', $course->id);

        $this->assertSame('error', $response['status']);
        $this->assertSame([], $response['answer']);
        $this->assertNotEmpty($response['statusMessage']);

        // The error path logs the underlying exception via debugging(); clear it.
        $this->resetDebugging();
    }

    /**
     * The "[0] could not find" sentinel is dropped when real answers exist,
     * but kept when it is the only thing the model returned.
     */
    public function test_strip_placeholder_answers(): void {
        $real = ['id' => 7, 'title' => 'PHP For Beginner', 'content' => 'PHP is...', 'viewurl' => 'x'];
        $sentinel = ['id' => 0, 'title' => 'Unknown course', 'content' => 'I could not find...', 'viewurl' => null];

        // Real answer + sentinel -> only the real answer survives.
        $result = \block_terusrag_external::strip_placeholder_answers([$real, $sentinel]);
        $this->assertCount(1, $result);
        $this->assertSame(7, $result[0]['id']);

        // Sentinel only (genuine "nothing found") -> kept.
        $result = \block_terusrag_external::strip_placeholder_answers([$sentinel]);
        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['id']);

        // Multiple real answers -> all kept.
        $result = \block_terusrag_external::strip_placeholder_answers([$real, ['id' => 9] + $real]);
        $this->assertCount(2, $result);

        // Empty -> empty.
        $this->assertSame([], \block_terusrag_external::strip_placeholder_answers([]));
    }

    /**
     * Users without the view capability are rejected before any processing.
     */
    public function test_submit_query_requires_view_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Prohibit the view capability for this user at the system context.
        $context = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('block/terusrag:view', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);

        $this->expectException(\required_capability_exception::class);
        \block_terusrag_external::submit_query('Tell me about anything', 0);
    }
}
