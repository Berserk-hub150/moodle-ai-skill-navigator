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

namespace local_aiskillnavigator\service\prompt;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();
// Returns the JSON example used by mind map prompts.
/**
 * Mindmap schema implementation.
 */
class mindmap_schema {
    /**
     * Get helper.
     */
    public function get(string $topic): string {
        return "Schema JSON:\n"
            . "{\n"
            . "\"title\":\"Titolo corto\",\n"
            . "\"central_topic\":\"{$topic}\",\n"
            . "\"summary\":\"Sintesi breve\",\n"
            . "\"central_description\":\"Descrizione centrale\",\n"
            // phpcs:ignore moodle.Files.LineLength
            . "\"branches\":[{\"title\":\"Ramo\",\"description\":\"Descrizione\",\"children\":[{\"title\":\"Nodo 1\",\"description\":\"Descrizione\"},{\"title\":\"Nodo 2\",\"description\":\"Descrizione\"}]}]\n"
            . "}";
    }
}
