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
 * Implementation of the BM25 ranking algorithm for text search.
 *
 * This class provides methods to index documents and calculate BM25 relevance scores
 * for search queries. BM25 is a probability-based ranking function that evaluates
 * document relevance based on term frequency and inverse document frequency.
 *
 * @package   block_terusrag
 * @copyright  2025 Terus e-Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bm25 {

    /** @var int Size of the corpus (number of documents) */
    private $corpussize = 0;

    /** @var float Average document length */
    private $avgdoclength = 0;

    /** @var array Document frequencies */
    private $docfrequencies = [];

    /** @var array Document lengths */
    private $doclengths = [];

    /** @var float BM25 parameter k1 */
    private $k1 = 1.2;

    /** @var float BM25 parameter b */
    private $b = 0.75;

    /**
     * Constructor for the BM25 class
     *
     * @param array $documents Array of documents to index
     */
    public function __construct(array $documents) {
        $this->corpussize = count($documents);

        // Handle empty documents array.
        if ($this->corpussize === 0) {
            $this->avgdoclength = 0;
            return;
        }

        foreach ($documents as $docid => $document) {
            $this->doclengths[$docid] = str_word_count($document);
            $this->avgdoclength += $this->doclengths[$docid];
            $tokens = array_count_values(str_word_count($document, 1));
            foreach ($tokens as $token => $count) {
                if (!isset($this->docfrequencies[$token])) {
                    $this->docfrequencies[$token] = [];
                }
                $this->docfrequencies[$token][$docid] = $count;
            }
        }

        // Calculate average document length only if we have documents.
        $this->avgdoclength /= $this->corpussize;
    }

    /**
     * Calculate BM25 score for a document given a query
     *
     * @param string $query  The search query
     * @param string $document The document content
     * @param int    $docid  The document ID
     * @return float The BM25 score
     */
    public function score(string $query, string $document, int $docid): float {
        // Return 0 score if corpus is empty or document doesn't exist.
        if ($this->corpussize === 0 || !isset($this->doclengths[$docid])) {
            return 0.0;
        }

        $score = 0.0;
        $querytokens = str_word_count($query, 1);
        $doclength = $this->doclengths[$docid];

        // Return 0 score if average document length is 0 to prevent division by zero.
        if ($this->avgdoclength === 0) {
            return 0.0;
        }

        foreach (array_unique($querytokens) as $token) {
            $nqi = isset($this->docfrequencies[$token])
                ? count($this->docfrequencies[$token])
                : 0;
            $fqi = isset($this->docfrequencies[$token][$docid])
                ? $this->docfrequencies[$token][$docid]
                : 0;
            $idf = log(($this->corpussize - $nqi + 0.5) / ($nqi + 0.5) + 1);

            // Calculate denominator first to check for zero.
            $denominator = $fqi + $this->k1 * (1 - $this->b + $this->b * ($doclength / $this->avgdoclength));

            // Skip this term if denominator would be zero.
            if ($denominator === 0) {
                continue;
            }

            $score += ($idf * ($fqi * ($this->k1 + 1))) / $denominator;
        }

        return $score;
    }
}
