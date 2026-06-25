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
 * Unit tests for the content indexer.
 *
 * @package    block_terusrag
 * @category   test
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

/**
 * Unit tests for the content indexer.
 *
 * @group block_terusrag
 * @covers \block_terusrag\indexer
 */
final class indexer_test extends \advanced_testcase {
    /**
     * Group the indexer output by moduletype => [moduleid => item].
     *
     * @param int $since Timestamp passed to the indexer.
     * @return array
     */
    private function index_by_type(int $since = 0): array {
        $bytype = [];
        foreach (indexer::get_indexable_items($since) as $item) {
            $bytype[$item['moduletype']][$item['moduleid']] = $item;
        }
        return $bytype;
    }

    /**
     * Courses AND supported activities must be indexed (not just courses).
     */
    public function test_indexes_courses_and_activities(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course([
            'fullname' => 'Indexer Course',
            'summary'  => 'Course summary text.',
        ]);
        $page = $generator->create_module('page', [
            'course'  => $course->id,
            'name'    => 'Page One',
            'intro'   => 'Page intro text.',
            'content' => 'Page body content.',
        ]);
        $forum = $generator->create_module('forum', [
            'course' => $course->id,
            'name'   => 'Forum One',
            'intro'  => 'Forum intro text.',
        ]);

        $bytype = $this->index_by_type(0);

        // Course is indexed with its name in the content.
        $this->assertArrayHasKey('course', $bytype);
        $this->assertArrayHasKey($course->id, $bytype['course']);
        $this->assertSame('course', $bytype['course'][$course->id]['moduletype']);
        $this->assertStringContainsString('Indexer Course', $bytype['course'][$course->id]['content']);

        // Page activity is indexed.
        $this->assertArrayHasKey('page', $bytype);
        $this->assertArrayHasKey($page->id, $bytype['page']);
        $this->assertStringContainsString('Page One', $bytype['page'][$page->id]['content']);

        // Forum activity is indexed.
        $this->assertArrayHasKey('forum', $bytype);
        $this->assertArrayHasKey($forum->id, $bytype['forum']);
        $this->assertStringContainsString('Forum One', $bytype['forum'][$forum->id]['content']);
    }

    /**
     * Hidden courses and hidden activities must be excluded from indexing.
     */
    public function test_hidden_content_excluded(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $hiddencourse = $generator->create_course(['visible' => 0]);
        $visiblecourse = $generator->create_course();
        $hiddenpage = $generator->create_module('page', [
            'course'  => $visiblecourse->id,
            'visible' => 0,
        ]);

        $bytype = $this->index_by_type(0);

        $courseids = array_keys($bytype['course'] ?? []);
        $pageids = array_keys($bytype['page'] ?? []);

        $this->assertNotContains((int) $hiddencourse->id, $courseids);
        $this->assertContains((int) $visiblecourse->id, $courseids);
        $this->assertNotContains((int) $hiddenpage->id, $pageids);
    }

    /**
     * The "since" watermark must exclude content not modified after it.
     */
    public function test_incremental_since_filter(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $generator->create_module('page', ['course' => $course->id]);

        // A future watermark should exclude everything modified before it.
        $bytype = $this->index_by_type(time() + DAYSECS);

        $this->assertArrayNotHasKey('course', $bytype);
        $this->assertArrayNotHasKey('page', $bytype);
    }

    /**
     * The front-page site course must never be indexed as a course.
     */
    public function test_site_course_excluded(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_course();

        $bytype = $this->index_by_type(0);

        $this->assertArrayNotHasKey(SITEID, $bytype['course'] ?? []);
    }
}
