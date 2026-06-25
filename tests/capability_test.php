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
 * Capability tests for the TerusRAG block.
 *
 * @package    block_terusrag
 * @category   test
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

/**
 * Capability tests for the TerusRAG block.
 *
 * @group block_terusrag
 * @coversNothing
 */
final class capability_test extends \advanced_testcase {
    /**
     * A plain authenticated user (e.g. a learner) must be able to query the
     * RAG block. Previously the query endpoint required :addinstance, which
     * blocked everyone except editing teachers and managers.
     */
    public function test_authenticated_user_has_view_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertTrue(
            has_capability('block/terusrag:view', \context_system::instance())
        );
    }

    /**
     * A guest (not logged in) must NOT hold the view capability.
     */
    public function test_guest_lacks_view_capability(): void {
        $this->resetAfterTest();

        $this->setGuestUser();

        $this->assertFalse(
            has_capability('block/terusrag:view', \context_system::instance())
        );
    }
}
