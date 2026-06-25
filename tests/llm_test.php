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
 * Unit tests for the llm vector helper.
 *
 * @package    block_terusrag
 * @category   test
 * @copyright  2025 Terus e-Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_terusrag;

/**
 * Unit tests for the llm vector helper.
 *
 * @group block_terusrag
 * @covers \block_terusrag\llm::cosine_similarity
 */
final class llm_test extends \advanced_testcase {
    /**
     * Cosine similarity must be mathematically correct for the common cases.
     */
    public function test_cosine_similarity_basic(): void {
        $llm = new llm();

        // Identical vectors -> 1.0.
        $this->assertEqualsWithDelta(1.0, $llm->cosine_similarity([1.0, 2.0, 3.0], [1.0, 2.0, 3.0]), 1e-9);

        // Orthogonal vectors -> 0.0.
        $this->assertEqualsWithDelta(0.0, $llm->cosine_similarity([1.0, 0.0], [0.0, 1.0]), 1e-9);

        // Opposite vectors -> -1.0.
        $this->assertEqualsWithDelta(-1.0, $llm->cosine_similarity([1.0, 0.0], [-1.0, 0.0]), 1e-9);

        // 45-degree angle: [1,1] vs [1,0] -> 1 / sqrt(2).
        $this->assertEqualsWithDelta(0.70710678, $llm->cosine_similarity([1.0, 1.0], [1.0, 0.0]), 1e-6);
    }

    /**
     * A zero-magnitude vector must return 0 rather than dividing by zero.
     */
    public function test_cosine_similarity_zero_vector(): void {
        $llm = new llm();
        $this->assertSame(0.0, $llm->cosine_similarity([0.0, 0.0], [1.0, 2.0]));
        $this->assertSame(0.0, $llm->cosine_similarity([1.0, 2.0], [0.0, 0.0]));
    }

    /**
     * Each magnitude must be computed over its own full vector.
     *
     * This is the regression guard for the previous bug where the second
     * vector's norm was accumulated only over keys shared with the first,
     * which inflated the score. With a=[1,0,0] and b=[1,0,0,5] the correct
     * similarity is 1/sqrt(26); the old code returned 1.0.
     */
    public function test_cosine_similarity_uneven_lengths(): void {
        $llm = new llm();
        $expected = 1.0 / sqrt(26.0);
        $this->assertEqualsWithDelta(
            $expected,
            $llm->cosine_similarity([1.0, 0.0, 0.0], [1.0, 0.0, 0.0, 5.0]),
            1e-9
        );
        $this->assertLessThan(1.0, $llm->cosine_similarity([1.0, 0.0, 0.0], [1.0, 0.0, 0.0, 5.0]));
    }
}
