<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Scheduled task for initializing data for Terus RAG block
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Scheduled task for initializing data for the Terus RAG block.
 *
 * This task processes course content, generates embeddings, and stores them in the database
 * to support retrieval augmented generation features.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datainitializer extends \core\task\scheduled_task {

    /**
     * Returns the name of the scheduled task.
     *
     * @return string The name of the task
     */
    public function get_name() {
        return get_string('datainitializer', 'block_terusrag');
    }

    /**
     * Executes the scheduled task to initialize data for the Terus RAG block.
     *
     * This method processes courses, chunks their content, generates embeddings,
     * and stores the data in the database for later retrieval.
     *
     * @return void
     */
    public function execute() {
        mtrace('Initializing data for Terus Rag block');
        $provider = get_config('block_terusrag', 'aiprovider');
        if ($provider === 'gemini') {
            $geminiprovider = new \block_terusrag\gemini();
            $geminiprovider->data_initialization();
        } else if ($provider === 'openai') {
            $openaiprovider = new \block_terusrag\openai();
            $openaiprovider->data_initialization();
        } else if ($provider === 'ollama') {
            $ollamaprovider = new \block_terusrag\ollama();
            $ollamaprovider->data_initialization();
        } else {
            mtrace('Unsupported AI provider: ' . $provider);
        }
        mtrace('Data initialization complete');
    }
}
