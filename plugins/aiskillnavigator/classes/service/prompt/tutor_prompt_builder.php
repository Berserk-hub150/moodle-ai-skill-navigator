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
// Builds tutor prompts for questions, materials and RAG text.
/**
 * Tutor prompt builder implementation.
 */
class tutor_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 2200;

    /**
     * Plain helper.
     */
    public function plain(string $question): string {
        return "Rispondi come tutor di un corso universitario.\n"
            . "Lingua: italiano.\n"
            . "Non fare una premessa lunga. Rispondi alla domanda.\n"
            . "Se la domanda è poco chiara, dichiara l'interpretazione scelta.\n"
            . "Se un dettaglio non lo sai, non inventarlo.\n\n"
            . "Domanda:\n" . trim($question);
    }

    /**
     * With materials helper.
     */
    public function with_materials(string $question, array $materials): string {
        return "Rispondi come tutor di un corso universitario.\n"
            . "Usa solo i materiali del docente riportati qui sotto.\n"
            . "Se nei materiali manca qualcosa, dillo chiaramente.\n"
            . "Alla fine aggiungi 'Fonti usate' con i titoli citati.\n\n"
            . "Materiali:\n" . $this->material_context($materials, self::MATERIAL_LIMIT)
            . "Domanda:\n" . trim($question);
    }

    /**
     * With rag helper.
     */
    public function with_rag(string $question, string $ragcontext): string {
        return "Rispondi come tutor di un corso universitario.\n"
            . "Usa solo i materiali recuperati qui sotto.\n"
            . "Se non bastano, scrivilo senza inventare il resto.\n"
            . "Alla fine aggiungi 'Fonti usate' con i titoli presenti nel contesto.\n\n"
            . "Materiali recuperati:\n" . trim($ragcontext)
            . "\n\nDomanda:\n" . trim($question);
    }
}
