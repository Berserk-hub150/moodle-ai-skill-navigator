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

// Splits text by length when no better boundary exists.
/**
 * Length chunker implementation.
 */
class length_chunker {
    public const SIZE = 2000;
    public const OVERLAP = 300;

    /**
     * Split helper.
     */
    public function split(string $text): array {
        $chunks = [];
        $length = \core_text::strlen($text);
        $start = 0;

        while ($start < $length) {
            $chunks[] = trim(\core_text::substr($text, $start, self::SIZE));
            $start += self::SIZE - self::OVERLAP;
        }

        return array_values(array_filter($chunks));
    }
}
