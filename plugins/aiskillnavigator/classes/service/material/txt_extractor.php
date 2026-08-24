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

namespace local_aiskillnavigator\service\material;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Reads text files uploaded by the teacher.
/**
 * Txt extractor implementation.
 */
class txt_extractor {
    /**
     * Extract helper.
     */
    public function extract(string $path): array {
        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            return ['success' => false, 'content' => '', 'message' => 'The TXT file is empty or unreadable.', 'type' => 'text'];
        }

        // phpcs:ignore moodle.Files.LineLength
        return ['success' => true, 'content' => $this->clean($content), 'message' => 'TXT file extracted successfully.', 'type' => 'text'];
    }

    /**
     * Clean helper.
     */
    private function clean(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text);
    }
}
