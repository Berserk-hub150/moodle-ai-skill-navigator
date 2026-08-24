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

// Splits material text into overlapping chunks.
/**
 * Paragraph chunker implementation.
 */
class paragraph_chunker {
    /**
     * Split helper.
     */
    public function split(string $text): array {
        $text = preg_replace("/\r\n|\r/", "\n", trim($text));

        if ($text === '') {
            return [];
        }

        if (\core_text::strlen($text) <= length_chunker::SIZE) {
            return [$text];
        }

        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = array_values(array_filter(array_map('trim', (array) $paragraphs)));

        if (empty($paragraphs)) {
            return (new sentence_chunker())->split($text);
        }

        return $this->merge($paragraphs);
    }

    /**
     * Merge helper.
     */
    private function merge(array $paragraphs): array {
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $toobig = $current !== '' && \core_text::strlen($current . $paragraph) > length_chunker::SIZE;
            if ($toobig) {
                $chunks[] = trim($current);
                $current = trim(\core_text::substr($current, -length_chunker::OVERLAP)) . "\n\n" . $paragraph;
            } else {
                $current .= ($current !== '' ? "\n\n" : '') . $paragraph;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
