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
// Builds summary prompts for uploaded materials or RAG text.
/**
 * Summary prompt builder implementation.
 */
class summary_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 3000;

    /**
     * From materials helper.
     */
    public function from_materials(string $focus, array $materials): string {
        return $this->base($focus)
            . "\nMateriali:\n"
            . $this->material_context($materials, self::MATERIAL_LIMIT);
    }

    /**
     * With rag helper.
     */
    public function with_rag(string $focus, string $ragcontext): string {
        return $this->base($focus) . "\nMateriali:\n" . trim($ragcontext);
    }

    /**
     * Base helper.
     */
    private function base(string $focus): string {
        $prompt = "Riassumi questi materiali in italiano.\n"
            . "Usa solo il contenuto fornito.\n"
            . "Scrivi come appunti per studiare: chiari, pratici, senza frasi decorative.\n";

        return trim($focus) !== '' ? $prompt . "Focus: " . trim($focus) . "\n" : $prompt;
    }
}
