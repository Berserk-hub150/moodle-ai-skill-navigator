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
// Builds quiz prompts from a topic, materials or RAG text.
/**
 * Quiz prompt builder implementation.
 */
class quiz_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 2600;
    /** @var quiz_rules Rules. */
    private quiz_rules $rules;
    /** @var quiz_schema Schema. */
    private quiz_schema $schema;

    /**
     * Construct helper.
     */
    public function __construct() {
        parent::__construct();
        $this->rules = new quiz_rules();
        $this->schema = new quiz_schema();
    }

    /**
     * Plain helper.
     */
    public function plain(string $topic, string $difficulty): string {
        $topic = $this->default_if_empty($topic, 'Digital Twin');
        $difficulty = $this->default_if_empty($difficulty, 'medium');

        return "Prepara un breve test universitario in italiano.\n"
            . "Argomento: {$topic}\nDifficoltà: {$difficulty}\n\n"
            . $this->rules->format() . $this->rules->quality()
            . $this->schema->get($topic, $difficulty);
    }

    /**
     * From materials helper.
     */
    public function from_materials(string $focus, string $difficulty, array $materials): string {
        $topic = $this->default_if_empty($focus, 'Materiali del docente');
        $difficulty = $this->default_if_empty($difficulty, 'medium');

        return "Prepara un breve test usando solo i materiali qui sotto.\n"
            . "Focus: {$topic}\nDifficoltà: {$difficulty}\n\n"
            . "Materiali:\n" . $this->material_context($materials, self::MATERIAL_LIMIT) . "\n"
            . $this->rules->format() . $this->rules->quality()
            . "Nel campo skill scrivi il concetto valutato.\n\n"
            . $this->schema->get($topic, $difficulty);
    }

    /**
     * With rag helper.
     */
    public function with_rag(string $focus, string $difficulty, string $ragcontext): string {
        return $this->from_materials($focus, $difficulty, [(object) ['content' => $ragcontext]]);
    }
}
