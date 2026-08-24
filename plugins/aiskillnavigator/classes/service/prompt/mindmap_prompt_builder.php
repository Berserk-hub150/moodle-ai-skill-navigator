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
// Builds mind map prompts from a topic, materials or RAG text.
/**
 * Mindmap prompt builder implementation.
 */
class mindmap_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 2600;
    /** @var mindmap_rules Rules. */
    private mindmap_rules $rules;
    /** @var mindmap_schema Schema. */
    private mindmap_schema $schema;

    /**
     * Construct helper.
     */
    public function __construct() {
        parent::__construct();
        $this->rules = new mindmap_rules();
        $this->schema = new mindmap_schema();
    }

    /**
     * Plain helper.
     */
    public function plain(string $topic): string {
        $topic = $this->default_if_empty($topic, 'Digital Twin');

        return "Crea una mappa mentale in italiano.\n"
            . "Tema centrale: {$topic}\n\n"
            . $this->rules->format() . $this->rules->quality()
            . $this->schema->get($topic);
    }

    /**
     * From materials helper.
     */
    public function from_materials(string $focus, array $materials): string {
        $topic = $this->default_if_empty($focus, 'Materiali del docente');

        return "Crea una mappa mentale usando solo i materiali qui sotto.\n"
            . "Tema centrale: {$topic}\n\n"
            . "Materiali:\n" . $this->material_context($materials, self::MATERIAL_LIMIT) . "\n"
            . $this->rules->format() . $this->rules->quality()
            . $this->schema->get($topic);
    }

    /**
     * With rag helper.
     */
    public function with_rag(string $focus, string $ragcontext): string {
        return $this->from_materials($focus, [(object) ['content' => $ragcontext]]);
    }
}
