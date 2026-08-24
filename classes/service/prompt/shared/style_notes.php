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
// Stores the short writing note reused by prose prompts.
/**
 * Style notes implementation.
 */
class style_notes {
    /**
     * Plain helper.
     */
    public function plain(): string {
        return "Stile:\n"
            . "- Scrivi in modo normale, non solenne.\n"
            . "- Vai al punto senza introduzioni lunghe.\n"
            . "- Evita frasi da brochure o da comunicato.\n"
            . "- Evita parole come cruciale, fondamentale, significativo, rivoluzionario, innovativo, panorama, sinergia.\n"
            . "- Non usare emoji.\n"
            . "- Non chiudere con frasi vaghe o celebrative.\n\n";
    }
}
