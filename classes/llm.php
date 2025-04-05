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

use moodle_exception;

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
            if (isset($vectorb[$key])) {
                $dotproduct += $value * $vectorb[$key];
                $norma += $value ** 2;
                $normb += $vectorb[$key] ** 2;
            }
        }

        $norma = sqrt($norma);
        $normb = sqrt($normb);

        if ($norma == 0 || $normb == 0) {
            return 0;
        }

        return $dotproduct / ($norma * $normb);
    }

    /**
     * Load stop words from file.
     *
     * @return array List of stop words
     * @throws moodle_exception If the stop words file cannot be found
     */
    public function load_stop_words() {
        global $CFG;

        $filepath = $CFG->dirroot . '/blocks/terusrag/utils/stopwords.txt';
        if (!file_exists($filepath)) {
            throw new moodle_exception('stopwords_not_found', 'block_terusrag');
        }

        $stopwords = file_get_contents($filepath);
        $stopwords = explode("\n", $stopwords);
        $stopwords = array_map('trim', $stopwords);
        return array_filter($stopwords, static fn ($word) => !empty($word));
    }

    /**
     * Tokenize a string into an array of individual tokens.
     *
     * @param string $text The text to tokenize
     * @return array Array of tokens
     */
    public function string_tokenize($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s\']/u', ' ', $text);
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values($tokens);
    }

    /**
     * Extract key terms from tokens by removing stop words and counting frequency.
     *
     * @param array $tokens Array of tokens
     * @param array $stopwords Array of stop words to filter out
     * @return array Associative array of terms and their frequencies
     */
    public function extract_key_terms($tokens, $stopwords) {
        $filteredtokens = array_filter($tokens, static fn ($token) => !in_array($token, $stopwords));
        $termfreq = array_count_values($filteredtokens);
        arsort($termfreq);
        return $termfreq;
    }

    /**
     * Identify entities in a text string.
     *
     * @param string $text The text to analyze for entities
     * @return array Associative array of identified entities
     */
    public function identify_entities($text) {
        $entities = [];
        preg_match_all('/\b[A-Z][a-zA-Z]*\b/', $text, $matches);

        if (!empty($matches[0])) {
            $entities['proper_nouns'] = array_unique($matches[0]);
        }

        $techterms = ['api', 'code', 'database', 'function', 'algorithm', 'array',
        'python', 'javascript', 'php', 'sql', 'html', 'css', 'neural network',
        'machine learning', 'ai', 'data', 'class', 'object', 'framework'];

        $lowertext = strtolower($text);
        $foundterms = [];

        foreach ($techterms as $term) {
            if (strpos($lowertext, $term) !== false) {
                $foundterms[] = $term;
            }
        }

        if (!empty($foundterms)) {
            $entities['technical_terms'] = $foundterms;
        }

        return $entities;
    }

    /**
     * Optimize a prompt for better LLM response.
     *
     * @param string $prompt The original prompt text
     * @param array $options Configuration options for optimization
     * @return array Optimized prompt and analysis information
     */
    public function optimize_prompt($prompt, $options = []) {
        $defaults = [
            'preserve_structure' => true,
            'emphasize_key_terms' => true,
            'add_context' => true,
            'format_output' => true,
            'max_length' => 2000,
        ];

        $options = array_merge($defaults, $options);

        $stopwords = $this->load_stop_words();
        $tokens = $this->string_tokenize($prompt);
        $keyterms = $this->extract_key_terms($tokens, $stopwords);
        $topterms = array_slice($keyterms, 0, 5, true);
        $entities = $this->identify_entities($prompt);
        $wordcount = count($tokens);
        $nonstopwordcount = count(array_filter($tokens, static fn ($token) => !in_array($token, $stopwords)));
        $density = $nonstopwordcount / $wordcount;
        $optimizedprompt = $prompt;

        $redundantphrases = [
            'I was wondering if', 'please tell me about', 'I want to know',
            'could you tell me', 'I would like to know', 'can you explain',
            'I need information on', 'I\'m interested in learning about',
        ];

        foreach ($redundantphrases as $phrase) {
            $optimizedprompt = str_ireplace($phrase, '', $optimizedprompt);
        }
        $optimizedprompt = trim($optimizedprompt);

        if ($options['emphasize_key_terms'] && !empty($topterms)) {
            foreach (array_keys($topterms) as $term) {
                if (strlen($term) > 2) { // Avoid very short terms.
                    $optimizedprompt = preg_replace('/\b' . preg_quote($term, '/') . '\b/i', '**\$0**', $optimizedprompt);
                }
            }
        }

        // 3. Add context if needed and option is enabled.
        if ($options['add_context'] && $density < 0.5) {
            // The prompt has many stopwords, so add context hints.
            $keytermsstring = implode(', ', array_keys(array_slice($keyterms, 0, 5, true)));
            $optimizedprompt .= "Focus on these key concepts: $keytermsstring.";
        }

        // 4. Format the output if option is enabled.
        if ($options['format_output']) {
            if (strlen($optimizedprompt) > 200 && strpos($optimizedprompt, "") === false) {
                $optimizedprompt = wordwrap($optimizedprompt, 100, "");
            }
            if ($wordcount > 50 && !preg_match('/^(explain|describe|what|how|why)/i', $optimizedprompt)) {
                $optimizedprompt = "Please provide a detailed explanation about the following:" . $optimizedprompt;
            }
        }

        // 5. Truncate if too long.
        if (strlen($optimizedprompt) > $options['max_length']) {
            $optimizedprompt = substr($optimizedprompt, 0, $options['max_length']) . "... (truncated)";
        }

        // Return the optimized prompt and analysis.
        return [
            'original_prompt' => $prompt,
            'optimized_prompt' => $optimizedprompt,
            'analysis' => [
                'word_count' => $wordcount,
                'content_density' => round($density, 2),
                'key_terms' => $topterms,
                'entities' => $entities,
            ],
        ];
    }
}
