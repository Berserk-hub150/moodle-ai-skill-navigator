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

// Runs summary prompts.
class summary_workflow extends base_workflow {
    public function materials(string $focus, array $materials): string {
        return empty($materials) ? 'Non sono stati trovati materiali leggibili del docente da riassumere.'
            : $this->provider->generate($this->prompts->summarize_materials_prompt($focus, $materials), 1600);
    }

    public function rag(string $focus, string $context): string {
        return trim($context) === '' ? 'Non sono stati trovati materiali rilevanti nel RAG index da riassumere.'
            : $this->provider->generate($this->prompts->summarize_rag_prompt($focus, $context), 1600);
    }
}
