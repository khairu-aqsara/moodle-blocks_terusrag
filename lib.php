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
 * Library functions for terusrag block
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Load block settings
 *
 * @return admin_settingpage The settings page
 */
function block_terusrag_load_settings() {
    global $CFG;
    require_once($CFG->libdir . '/adminlib.php');

    $settings = new admin_settingpage('block_terusrag', get_string('pluginname', 'block_terusrag'));
    return $settings;
}

/**
 * Get vector database type
 *
 * @return string The configured vector database type or default 'flatfile'
 */
function block_terusrag_get_vectordb_type() {
    return get_config('block_terusrag', 'vector_database') ?: 'flatfile';
}
