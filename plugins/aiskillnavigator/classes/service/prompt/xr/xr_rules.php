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
// Keeps XR scenarios practical enough for a demo.
/**
 * Xr rules implementation.
 */
class xr_rules {
    /**
     * Get helper.
     */
    public function get(bool $usesources): string {
        $rules = "Scenario:\n"
            . "- Deve essere concreto.\n"
            . "- I task dello studente devono essere almeno 5.\n"
            . "- I criteri di valutazione devono essere almeno 4.\n"
            . "- Ogni task deve descrivere qualcosa che lo studente fa davvero.\n"
            . "- Descrivi cosa vede, cosa controlla e quale decisione prende.\n"
            . "- Evita frasi generiche tipo esperienza immersiva innovativa.\n\n";

        return $usesources ? $rules . "- Alla fine indica le fonti usate.\n\n" : $rules;
    }
}
