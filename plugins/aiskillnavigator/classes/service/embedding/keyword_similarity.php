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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiskillnavigator\service\embedding;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Scores text using shared words.
/**
 * Keyword similarity implementation.
 */
class keyword_similarity {
    /**
     * Score helper.
     */
    public function score(string $query, string $text): float {
        $querywords = $this->words($query);
        $textwords = $this->words($text);

        if (empty($querywords) || empty($textwords)) {
            return 0.0;
        }

        $intersection = count(array_intersect($querywords, $textwords));
        $union = count(array_unique(array_merge($querywords, $textwords)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Words helper.
     */
    private function words(string $text): array {
        $text = \core_text::strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', (string) $text);

        return array_values(array_filter($words, function ($word) {
            return \core_text::strlen($word) > 2;
        }));
    }
}
