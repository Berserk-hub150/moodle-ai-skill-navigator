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

// Splits long text around sentence boundaries.
/**
 * Sentence chunker implementation.
 */
class sentence_chunker {
    /**
     * Split helper.
     */
    public function split(string $text): array {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text));

        if (!$sentences || count($sentences) <= 1) {
            return (new length_chunker())->split($text);
        }

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $toobig = $current !== ''
                && \core_text::strlen($current) + \core_text::strlen($sentence) + 1 > length_chunker::SIZE;

            if ($toobig) {
                $chunks[] = trim($current);
                $overlap = \core_text::substr($current, -length_chunker::OVERLAP);
                $current = trim($overlap . ' ' . $sentence);
            } else {
                $current .= ($current !== '' ? ' ' : '') . $sentence;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
