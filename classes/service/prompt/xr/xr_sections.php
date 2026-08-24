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
// Returns the Markdown sections used by the XR page.
/**
 * Xr sections implementation.
 */
class xr_sections {
    /**
     * Get helper.
     */
    public function get(bool $usesources): string {
        $sections = "# Titolo\n"
            . "## Obiettivo didattico\n"
            . "## Ambiente virtuale\n"
            . "## Storia dello scenario\n"
            . "## Task dello studente\n"
            . "## Criteri di valutazione\n"
            . "## Competenze coinvolte\n";

        return $usesources ? $sections . "## Fonti usate\n" : $sections . "## Estensioni possibili\n";
    }
}
