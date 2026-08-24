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
// Keeps the quiz output strict enough for JSON parsing.
/**
 * Quiz rules implementation.
 */
class quiz_rules {
    /**
     * Format helper.
     */
    public function format(): string {
        return "Formato:\n"
            . "- Rispondi solo con JSON valido.\n"
            . "- Niente Markdown.\n"
            . "- Niente testo prima o dopo il JSON.\n"
            . "- Esattamente 3 domande.\n"
            . "- Ogni domanda deve avere 4 opzioni.\n"
            . "- Spiegazioni sotto i 180 caratteri.\n\n";
    }

    /**
     * Quality helper.
     */
    public function quality(): string {
        return "Domande:\n"
            . "- Evita domande troppo ovvie.\n"
            . "- Le opzioni sbagliate devono sembrare credibili.\n"
            . "- Verifica comprensione, confronto o applicazione.\n"
            . "- La spiegazione deve chiarire la risposta corretta.\n\n";
    }
}
