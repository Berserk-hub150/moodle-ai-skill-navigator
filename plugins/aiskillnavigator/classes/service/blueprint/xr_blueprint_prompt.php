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

namespace local_aiskillnavigator\service\blueprint;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Builds the JSON prompt for exportable XR blueprints.
/**
 * Xr blueprint prompt implementation.
 */
class xr_blueprint_prompt {
    /**
     * System helper.
     */
    public function system(): string {
        return 'You are a strict JSON generator for educational XR blueprints. Return only valid JSON.';
    }

    /**
     * Build helper.
     */
    public function build(string $topic, string $environment, string $context): string {
        $prompt = "Genera un blueprint XR per un plugin Moodle universitario.\n\n"
            . "Topic/focus: {$topic}\n"
            . "Ambiente virtuale: {$environment}\n\n"
            . "Genera coordinate, oggetti, punti di interesse, task, checkpoint, trigger, dialoghi e obiettivi didattici.\n\n";

        if (trim($context) !== '') {
            $prompt .= "CONTESTO MATERIALI/RAG:\n{$context}\nUsa solo i concetti presenti nel contesto.\n\n";
        }

        return $prompt
            . "Rispondi solo con JSON valido, senza Markdown.\n"
            . "Genera almeno 5 objects, 4 points_of_interest, 5 tasks, 4 checkpoints, 4 triggers e 4 dialogs.\n"
            . "Usa coordinate x/y numeriche da 0 a 100.\n";
    }
}
