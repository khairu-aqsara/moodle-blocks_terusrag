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
 * Terusrag block for Moodle.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Terusrag block class
 *
 * @package    block_terusrag
 * @copyright  2025 Khairu Aqsara
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_terusrag extends block_base {
    /**
     * Initialize block
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_terusrag');
    }

    /**
     * This block has global configuration
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Multiple instances allowed or not. Only allows 1 instance on Dashboard
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_instance_config() {
        return true;
    }

    /**
     * Get block content
     *
     * @return stdClass|string Block content
     */
    public function get_content() {
        global $CFG, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // Prepare template context.
        $templatecontext = [
            'courseid' => $COURSE->id,
            'hasresponse' => false,
        ];

        // Render the template.
        $this->content->text = $this->page->get_renderer('core')->render_from_template(
            'block_terusrag/block_content',
            $templatecontext
        );
        $this->content->footer = '';

        return $this->content;
    }

    /**
     * This function is called on your subclass right after an instance is loaded
     */
    public function specialization() {
        if (isset($this->config)) {
            if (empty($this->config->title)) {
                $this->title = get_string('pluginname', 'block_terusrag');
            } else {
                $this->title = $this->config->title;
            }
        }
    }
}
