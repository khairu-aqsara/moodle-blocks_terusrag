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
 * Content indexer for the TerusRAG block.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     khairu@teruselearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

/**
 * Collects the Moodle content that should be embedded and stored in the index.
 *
 * Previously only course names/descriptions were indexed.  This class also
 * walks supported activity modules (resources and activities) so that the RAG
 * pipeline can answer questions about course content, not just the course
 * overview.  The module types listed here mirror the ones resolved back into
 * titles/URLs by each provider's build_course_response_from_chunk().
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class indexer {
    /**
     * Supported activity module types and the text fields to pull from each.
     *
     * The 'name' field is always indexed; the listed fields are appended.
     * Every table referenced here also exposes 'course' and 'timemodified'
     * columns, which the collector relies on for visibility joins and
     * incremental indexing.
     *
     * @var array<string, string[]>
     */
    const ACTIVITY_FIELDS = [
        'resource' => ['intro'],
        'page'     => ['intro', 'content'],
        'assign'   => ['intro'],
        'forum'    => ['intro'],
        'book'     => ['intro'],
    ];

    /**
     * Yield every indexable item modified since the given timestamp.
     *
     * Each yielded item is an associative array with keys:
     *  - moduletype   (string) e.g. 'course', 'resource', 'page'...
     *  - moduleid     (int)    instance id of the course/module
     *  - title        (string) human-readable title
     *  - content      (string) plain-text content to embed
     *  - timemodified (int)    source record's last modification time
     *
     * Items are produced lazily so the caller can batch them without loading
     * the whole site into memory.
     *
     * @param  int $since Only return items whose timemodified is greater than this.
     * @return \Generator
     */
    public static function get_indexable_items(int $since): \Generator {
        global $DB;

        // 1. Courses (excluding the front-page site course).
        $rs = $DB->get_recordset_select(
            'course',
            'visible = :visible AND timemodified > :since AND id <> :siteid',
            ['visible' => 1, 'since' => $since, 'siteid' => SITEID],
            'id ASC',
            'id, fullname, summary, timemodified'
        );
        foreach ($rs as $course) {
            $summary = (string) ($course->summary ?? '');
            $content = self::clean_text($course->fullname . '. ' . $summary);
            if ($content === '') {
                continue;
            }
            yield [
                'moduletype'   => 'course',
                'moduleid'     => (int) $course->id,
                'title'        => (string) $course->fullname,
                'content'      => $content,
                'timemodified' => (int) $course->timemodified,
            ];
        }
        $rs->close();

        // 2. Supported activity modules in visible courses.
        foreach (self::ACTIVITY_FIELDS as $modname => $textfields) {
            // Skip module types that are not installed on this site.
            $moduleid = $DB->get_field('modules', 'id', ['name' => $modname]);
            if (!$moduleid) {
                continue;
            }

            $sql = "SELECT m.*
                      FROM {" . $modname . "} m
                      JOIN {course} c ON c.id = m.course
                      JOIN {course_modules} cm
                        ON cm.instance = m.id AND cm.course = m.course AND cm.module = :moduleid
                     WHERE c.visible = :coursevisible
                       AND cm.visible = :cmvisible
                       AND m.timemodified > :since
                  ORDER BY m.id ASC";

            $params = [
                'moduleid'      => $moduleid,
                'coursevisible' => 1,
                'cmvisible'     => 1,
                'since'         => $since,
            ];

            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $record) {
                $parts = [(string) ($record->name ?? '')];
                foreach ($textfields as $field) {
                    if (isset($record->$field) && $record->$field !== '') {
                        $parts[] = (string) $record->$field;
                    }
                }
                $content = self::clean_text(implode('. ', $parts));
                if ($content === '') {
                    continue;
                }
                yield [
                    'moduletype'   => $modname,
                    'moduleid'     => (int) $record->id,
                    'title'        => (string) ($record->name ?? ''),
                    'content'      => $content,
                    'timemodified' => (int) ($record->timemodified ?? time()),
                ];
            }
            $rs->close();
        }
    }

    /**
     * Reduce HTML/markup content to a normalised plain-text string.
     *
     * @param  string $content Raw content (may contain HTML).
     * @return string          Trimmed plain text with collapsed whitespace.
     */
    public static function clean_text(string $content): string {
        // Decode entities first so things like &amp; do not survive strip_tags.
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse all runs of whitespace (including newlines) to single spaces.
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }
}
