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
 * Form for editing TerusRAG block instance configuration.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class for editing TerusRAG block instance settings.
 *
 * This class extends block_edit_form to provide configuration options
 * specific to the TerusRAG block.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_terusrag_edit_form extends block_edit_form {
    /**
     * Form definition
     *
     * @param MoodleQuickForm $mform The form being built
     */
    protected function specific_definition($mform) {
        // Section header title.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Title field.
        $mform->addElement('text', 'config_title', get_string('blocktitle', 'block_terusrag'));
        $mform->setDefault('config_title', get_string('pluginname', 'block_terusrag'));
        $mform->setType('config_title', PARAM_TEXT);
    }
}
