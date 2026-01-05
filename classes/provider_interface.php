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
 * Provider interface for the terusrag block.
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     khairu@teruselearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

/**
 * Interface for LLM providers that implement embedding and response generation.
 *
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface provider_interface {

    /**
     * Get embedding vector for a query.
     *
     * @param string $query The query to generate embeddings for
     * @return array The embedding vector
     */
    public function get_embedding($query);

    /**
     * Get a response from the LLM.
     *
     * @param string $prompt The prompt to send to the LLM
     * @return string The generated response
     */
    public function get_response($prompt);

        /**
         * Get the top ranked content chunks for a given query.
         *
         * @param string $query The search query
         * @return array The top-ranked content chunks
         */
    public function get_top_ranked_chunks(string $query);

    /**
     * Process a RAG query.
     *
     * @param string $userquery The user query to process
     * @return mixed The processed result
     */
    public function process_rag_query(string $userquery);

    /**
     * Initialize data required for the provider.
     *
     * @return void
     */
    public function data_initialization();
}
