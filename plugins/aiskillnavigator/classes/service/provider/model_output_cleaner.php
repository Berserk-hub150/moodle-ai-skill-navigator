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

namespace local_aiskillnavigator\service\provider;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Removes simple Markdown fences from model output.
/**
 * Model output cleaner implementation.
 */
class model_output_cleaner {
    /**
     * Clean helper.
     */
    public function clean(string $text): string {
        $text = trim($text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
        if (preg_match('/^```[a-zA-Z0-9_-]*\s*(.*?)\s*```$/s', $text, $matches)) {
            return trim((string) $matches[1]);
        }

        // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
        if (substr($text, 0, 3) === '```') {
            $text = trim(substr($text, 3));
            $text = preg_replace('/^[a-zA-Z0-9_-]+\s*\n/', '', $text);
        }

        // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
        if (substr($text, -3) === '```') {
            $text = trim(substr($text, 0, -3));
        }

        return trim($text);
    }
}
