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

namespace local_aiskillnavigator\service\prototype;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Chooses a demo response from the prompt content.
/**
 * Prototype output router implementation.
 */
class prototype_output_router {
    /**
     * Route helper.
     */
    public function route(string $prompt): string {
        $lower = strtolower($prompt);

        if (str_contains($lower, '"questions"') || str_contains($lower, 'micro-test')) {
            return (new prototype_quiz_response())->get();
        }

        if (str_contains($lower, '"branches"') || str_contains($lower, 'mappa mentale')) {
            return (new prototype_mindmap_response())->get();
        }

        if (str_contains($lower, 'scenario') || str_contains($lower, 'virtual worlds')) {
            return (new prototype_xr_response())->get();
        }

        return 'Risposta dimostrativa: il sistema è attivo in modalità prototype.';
    }
}
