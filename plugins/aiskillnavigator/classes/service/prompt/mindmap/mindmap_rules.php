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
// Keeps the mind map output strict enough for JSON parsing.
/**
 * Mindmap rules implementation.
 */
class mindmap_rules {
    /**
     * Format helper.
     */
    public function format(): string {
        return "Formato:\n"
            . "- Rispondi solo con JSON valido.\n"
            . "- Niente Markdown.\n"
            . "- Esattamente 4 rami principali.\n"
            . "- Ogni ramo deve avere 2 sotto-nodi.\n"
            . "- Titoli brevi, massimo 4 parole.\n\n";
    }

    /**
     * Quality helper.
     */
    public function quality(): string {
        return "Nodi:\n"
            . "- Usa titoli concreti.\n"
            . "- Le descrizioni devono sembrare appunti da ripasso.\n"
            . "- Ogni nodo deve aggiungere qualcosa.\n\n";
    }
}
