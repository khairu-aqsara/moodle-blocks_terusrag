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

namespace block_terusrag;

/**
 * Language model utility class for vector operations.
 *
 * This class provides utilities for working with language model vector embeddings,
 * including similarity calculations and other vector operations needed for
 * retrieval augmented generation (RAG).
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class llm {

    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param array $vectora First vector
     * @param array $vectorb Second vector
     * @return float The cosine similarity value
     */
    public function cosine_similarity(array $vectora, array $vectorb) {
        $dotproduct = 0;
        $norma = 0;
        $normb = 0;

        foreach ($vectora as $key => $value) {
            $dotproduct += $value * $vectorb[$key];
            $norma += $value ** 2;
            $normb += $vectorb[$key] ** 2;
        }

        $norma = sqrt($norma);
        $normb = sqrt($normb);

        if ($norma == 0 || $normb == 0) {
            return 0;
        }

        return $dotproduct / ($norma * $normb);
    }
}
