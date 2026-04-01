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
 * External functions for Terus RAG block.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No MOODLE_INTERNAL guard needed here: this file is loaded only via
// the external API classpath mechanism and cannot be accessed directly.
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External functions for the Terus RAG block.
 *
 * Provides AJAX-accessible endpoints for submitting queries to the RAG system
 * and (admin-only) debugging the response parsing pipeline.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_terusrag_external extends external_api {
    // =========================================================================
    // submit_query
    // =========================================================================

    /**
     * Returns description of submit_query parameters.
     *
     * @return external_function_parameters
     */
    public static function submit_query_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query'    => new external_value(PARAM_TEXT, 'The user query'),
            'courseid' => new external_value(
                PARAM_INT,
                'The course ID for contextual awareness',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Submit a natural-language query to the RAG system.
     *
     * Returns AI-generated answer items plus token usage metadata.
     * The 'status' field is one of: 'success', 'no_results', 'warning', 'error'.
     *
     * @param  string $query    The user's natural-language question.
     * @param  int    $courseid Optional course ID for context (reserved for future use).
     * @return array  Response array declared by submit_query_returns().
     * @throws \required_capability_exception
     */
    public static function submit_query(string $query, int $courseid = 0): array {
        global $DB;

        $params = self::validate_parameters(self::submit_query_parameters(), [
            'query'    => $query,
            'courseid' => $courseid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('block/terusrag:addinstance', $context);

        // Guard: content index must be populated before queries are useful.
        $chunkcount = $DB->count_records('block_terusrag');
        if ($chunkcount === 0) {
            debugging(
                'TerusRAG submit_query: block_terusrag table is empty. '
                . 'Run the Data Initializer scheduled task to populate the index.',
                DEBUG_DEVELOPER
            );
            return [
                'answer'             => [],
                'promptTokenCount'   => 0,
                'responseTokenCount' => 0,
                'totalTokenCount'    => 0,
                'status'             => 'warning',
                'statusMessage'      => get_string('no_data_initialized', 'block_terusrag'),
            ];
        }

        $provider = get_config('block_terusrag', 'aiprovider');

        try {
            switch ($provider) {
                case 'gemini':
                    $llmprovider = new \block_terusrag\gemini();
                    break;
                case 'openai':
                    $llmprovider = new \block_terusrag\openai();
                    break;
                case 'ollama':
                    $llmprovider = new \block_terusrag\ollama();
                    break;
                default:
                    throw new \coding_exception('Unsupported AI provider configured: ' . s($provider));
            }

            $response = $llmprovider->process_rag_query($params['query']);

            // Normalise the answer field.
            if (!isset($response['answer']) || !is_array($response['answer'])) {
                $response['answer'] = [];
            }

            // Deduplicate: the indexer splits long content into 512-char chunks,
            // so the same course can appear multiple times in the answer array
            // (same title/URL, different text segments).  Group items by their
            // source URL and merge the content so the user sees each source once.
            $grouped = [];
            $insertionorder = [];

            foreach ($response['answer'] as $item) {
                // Use viewurl as the grouping key; fall back to title for items
                // that have no URL (e.g. unverified / stale chunks).
                $key = !empty($item['viewurl']) ? $item['viewurl'] : ('title::' . $item['title']);

                if (!isset($grouped[$key])) {
                    $grouped[$key]    = $item;
                    $insertionorder[] = $key;
                } else {
                    // Append subsequent chunk content with a separator.
                    $grouped[$key]['content'] .= ' ' . $item['content'];
                }
            }

            $response['answer'] = array_values(
                array_map(static fn($k) => $grouped[$k], $insertionorder)
            );

            // Re-index so external_multiple_structure receives a sequential array.
            $response['answer'] = array_values($response['answer']);

            // Provide default token counts if provider did not return them.
            $response['promptTokenCount']   = (int) ($response['promptTokenCount'] ?? 0);
            $response['responseTokenCount'] = (int) ($response['responseTokenCount'] ?? 0);
            $response['totalTokenCount']    = (int) ($response['totalTokenCount'] ?? 0);

            $response['status']        = empty($response['answer']) ? 'no_results' : 'success';
            $response['statusMessage'] = '';

            return $response;
        } catch (\moodle_exception $e) {
            debugging('TerusRAG Error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'answer'             => [],
                'promptTokenCount'   => 0,
                'responseTokenCount' => 0,
                'totalTokenCount'    => 0,
                'status'             => 'error',
                'statusMessage'      => get_string('general_error', 'block_terusrag'),
            ];
        } catch (\Exception $e) {
            debugging('TerusRAG Unexpected Error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'answer'             => [],
                'promptTokenCount'   => 0,
                'responseTokenCount' => 0,
                'totalTokenCount'    => 0,
                'status'             => 'error',
                'statusMessage'      => get_string('general_error', 'block_terusrag'),
            ];
        }
    }

    /**
     * Returns description of submit_query return value.
     *
     * All new top-level fields (status, statusMessage) MUST be declared here
     * so Moodle's clean_returnvalue() includes them in the JSON response.
     * Fields not declared are silently stripped before reaching the client.
     *
     * @return external_single_structure
     */
    public static function submit_query_returns(): external_single_structure {
        return new external_single_structure([
            'answer' => new external_multiple_structure(
                new external_single_structure([
                    'id'      => new external_value(PARAM_INT, 'Content chunk ID'),
                    'title'   => new external_value(PARAM_TEXT, 'Content source title'),
                    'viewurl' => new external_value(
                        PARAM_TEXT,
                        'URL to view the content source',
                        VALUE_DEFAULT,
                        null
                    ),
                    'content' => new external_value(PARAM_RAW, 'AI-generated response content'),
                ]),
                'List of answer items returned by the RAG pipeline',
                VALUE_DEFAULT,
                []
            ),
            'promptTokenCount'   => new external_value(PARAM_INT, 'Number of tokens in the prompt'),
            'responseTokenCount' => new external_value(PARAM_INT, 'Number of tokens in the LLM response'),
            'totalTokenCount'    => new external_value(PARAM_INT, 'Total tokens consumed by the request'),
            'status'             => new external_value(
                PARAM_TEXT,
                'Response status: success | no_results | warning | error',
                VALUE_DEFAULT,
                'success'
            ),
            'statusMessage'      => new external_value(
                PARAM_TEXT,
                'Human-readable status or error message',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    // Debug parse response — admin-only diagnostic endpoint.
    // =========================================================================

    /**
     * Returns description of debug_parse_response parameters.
     *
     * @return external_function_parameters
     */
    public static function debug_parse_response_parameters(): external_function_parameters {
        return new external_function_parameters([
            'rawresponse' => new external_value(
                PARAM_RAW,
                'Raw LLM response text to feed through the parsing pipeline'
            ),
            'provider'    => new external_value(
                PARAM_ALPHA,
                'Provider to use for parsing: gemini, openai, or ollama',
                VALUE_DEFAULT,
                'gemini'
            ),
        ]);
    }

    /**
     * Parse a raw LLM response string and return diagnostic information.
     *
     * This endpoint lets administrators test the parsing pipeline without
     * making live API calls. It is restricted to users with moodle/site:config.
     *
     * @param  string $rawresponse Raw LLM response text to parse.
     * @param  string $provider    Provider name: gemini | openai | ollama.
     * @return array  Diagnostic data: success flag, item count, parsed items, raw preview.
     * @throws \required_capability_exception
     */
    public static function debug_parse_response(string $rawresponse, string $provider = 'gemini'): array {
        $params = self::validate_parameters(self::debug_parse_response_parameters(), [
            'rawresponse' => $rawresponse,
            'provider'    => $provider,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        // Admin-only: parsing pipeline may expose internal chunk data.
        require_capability('moodle/site:config', $context);

        try {
            switch ($params['provider']) {
                case 'openai':
                    $llmprovider = new \block_terusrag\openai();
                    break;
                case 'ollama':
                    $llmprovider = new \block_terusrag\ollama();
                    break;
                default:
                    $llmprovider = new \block_terusrag\gemini();
            }

            // All providers accept a string in their parse_response() after the fix.
            $parsed = $llmprovider->parse_response($params['rawresponse']);
            $parsed = array_values($parsed);

            // Truncate content previews so the response stays lightweight.
            $itemsummary = array_map(static function (array $item): array {
                return [
                    'id'      => (int)   ($item['id'] ?? 0),
                    'title'   => (string)($item['title'] ?? ''),
                    'viewurl' => isset($item['viewurl']) ? (string)$item['viewurl'] : null,
                    'content' => mb_substr((string)($item['content'] ?? ''), 0, 300),
                ];
            }, $parsed);

            return [
                'success'     => true,
                'parsedcount' => count($parsed),
                'items'       => $itemsummary,
                'rawpreview'  => mb_substr($params['rawresponse'], 0, 400),
                'message'     => 'Parsing completed successfully.',
            ];
        } catch (\Exception $e) {
            return [
                'success'     => false,
                'parsedcount' => 0,
                'items'       => [],
                'rawpreview'  => mb_substr($params['rawresponse'], 0, 400),
                'message'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns description of debug_parse_response return value.
     *
     * @return external_single_structure
     */
    public static function debug_parse_response_returns(): external_single_structure {
        return new external_single_structure([
            'success'     => new external_value(PARAM_BOOL, 'Whether parsing completed without exceptions'),
            'parsedcount' => new external_value(PARAM_INT, 'Number of items returned after filtering'),
            'items'       => new external_multiple_structure(
                new external_single_structure([
                    'id'      => new external_value(PARAM_INT, 'Chunk ID resolved by the parser'),
                    'title'   => new external_value(PARAM_TEXT, 'Resolved source title'),
                    'viewurl' => new external_value(
                        PARAM_TEXT,
                        'Resolved view URL (null if chunk not found)',
                        VALUE_DEFAULT,
                        null
                    ),
                    'content' => new external_value(PARAM_RAW, 'Content preview (first 300 chars)'),
                ]),
                'Parsed and resolved answer items',
                VALUE_DEFAULT,
                []
            ),
            'rawpreview'  => new external_value(PARAM_RAW, 'First 400 characters of the raw input'),
            'message'     => new external_value(PARAM_TEXT, 'Status or error message'),
        ]);
    }
}
