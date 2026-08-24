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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_aiskillnavigator\service\workflow;

defined('MOODLE_INTERNAL') || die();

// Runs tutor prompts.
class tutor_workflow extends base_workflow {
    public function ask(string $question): string {
        $question = trim($question);
        return $question === '' ? 'Scrivi una domanda prima di inviarla al tutor AI.'
            : $this->provider->generate($this->prompts->tutor_prompt($question), 1000);
    }

    public function materials(string $question, array $materials): string {
        $question = trim($question);
        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor del corso.';
        }

        return empty($materials) ? 'Non sono stati trovati materiali del docente rilevanti per rispondere alla domanda.'
            : $this->provider->generate($this->prompts->tutor_with_materials_prompt($question, $materials), 1400);
    }

    public function rag(string $question, string $context): string {
        $question = trim($question);
        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor del corso.';
        }

        return trim($context) === '' ? 'Non sono stati trovati materiali rilevanti nel RAG index per rispondere alla domanda.'
            : $this->provider->generate($this->prompts->tutor_with_rag_prompt($question, $context), 1400);
    }
}
