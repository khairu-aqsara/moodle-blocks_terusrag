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
 * External functions for Terus RAG block
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * External functions for the Terus RAG block
 */
class block_terusrag_external extends external_api {

    /**
     * Returns description of submit_query parameters
     *
     * @return external_function_parameters
     */
    public static function submit_query_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'The user query'),
        ]);
    }

    /**
     * Submit a query to the RAG system
     *
     * @param string $query The user's query
     * @return array RAG response data
     */
    public static function submit_query($query): array {
        global $CFG;

        // Parameter validation.
        $params = self::validate_parameters(self::submit_query_parameters(), [
            'query' => $query,
        ]);

        // Context validation.
        $context = context_course::instance(1);
        self::validate_context($context);
        require_capability('block/terusrag:addinstance', $context);

        $provider = get_config('block_terusrag', 'aiprovider');
        if ($provider === 'gemini') {
            $gemini = new \block_terusrag\gemini();
            $response = $gemini->process_rag_query($params['query']);
        } else if ($provider === 'openai') {
            $openai = new \block_terusrag\openai();
            $response = $openai->process_rag_query($params['query']);
        } else if ($provider === 'ollama') {
            $ollama = new \block_terusrag\ollama();
            $response = $ollama->process_rag_query($params['query']);
        } else {
            throw new coding_exception('Unsupported AI provider: ' . $provider);
        }

        if (!isset($response['answer'])) {
            $response['answer'] = [];
        } else {

            if (!is_array($response['answer'])) {
                $response['answer'] = [$response['answer']];
            }
        }

        return $response;
    }

    /**
     * Returns description of submit_query return value
     *
     * @return external_description
     */
    public static function submit_query_returns() {
        return new external_single_structure([
            'answer' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Content ID'),
                    'title' => new external_value(PARAM_TEXT, 'Content title'),
                    'viewurl' => new external_value(PARAM_TEXT, 'Content URL'),
                    'content' => new external_value(PARAM_RAW, 'Response content'),
                ])
            ),
            'promptTokenCount' => new external_value(PARAM_INT, 'Number of tokens in prompt'),
            'responseTokenCount' => new external_value(PARAM_INT, 'Number of tokens in response'),
            'totalTokenCount' => new external_value(PARAM_INT, 'Total number of tokens used'),
        ]);
    }
}
