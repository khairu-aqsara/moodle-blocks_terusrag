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
 * This file keeps track of upgrades to the block_terusrag block
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade code for the Tag Youtube block.
 *
 * @param int $oldversion
 */
function xmldb_block_terusrag_upgrade($oldversion) {
    global $DB, $CFG, $OUTPUT;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025031102) {
        $table = new xmldb_table('block_terusrag');

        // Adding fields to table block_terusrag.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('embedding', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table block_terusrag.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('contenthash', XMLDB_KEY_UNIQUE, ['contenthash']);

        // Conditionally launch create table for block_terusrag.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2025031102, 'terusrag', false);
    }

    if ($oldversion < 2025031103) {
        $table = new xmldb_table('block_terusrag');
        $moduletype = new xmldb_field('moduletype', XMLDB_TYPE_CHAR, '64', null, null, null, null, null);
        $moduleid = new xmldb_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);

        if (!$dbman->field_exists($table, $moduletype)) {
            $dbman->add_field($table, $moduletype);
        }

        if (!$dbman->field_exists($table, $moduleid)) {
            $dbman->add_field($table, $moduleid);
        }

        upgrade_block_savepoint(true, 2025031103, 'terusrag');
    }

    if ($oldversion < 2025031104) {
        // Define field title to be added to block_terusrag.
        $table = new xmldb_table('block_terusrag');
        $field = new xmldb_field('title', XMLDB_TYPE_CHAR, '255', null, null, null, null, null);

        // Conditionally launch add field title.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Terusrag savepoint reached.
        upgrade_block_savepoint(true, 2025031104, 'terusrag');
    }

    if ($oldversion < 2025040512) {
        // Get manager.
        $table = new xmldb_table('block_terusrag');

        // Remove existing unique key.
        $key = new xmldb_key('contenthash', XMLDB_KEY_UNIQUE, ['contenthash']);
        $dbman->drop_key($table, $key);

        // Add new composite unique key.
        $key = new xmldb_key('contenthash_moduleid', XMLDB_KEY_UNIQUE, ['contenthash', 'moduleid']);
        $dbman->add_key($table, $key);

        // Terusrag savepoint reached.
        upgrade_block_savepoint(true, 2025040512, 'terusrag');
    }

    return true;
}
