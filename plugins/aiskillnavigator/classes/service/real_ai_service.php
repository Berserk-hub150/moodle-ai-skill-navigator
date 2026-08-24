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

namespace local_aiskillnavigator\service;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/privacy_guard.php');


/**
 * Real ai service implementation.
 */
class real_ai_service {
    /** @var ai_workflow_facade Facade. */
    private ai_workflow_facade $facade;

    /**
     * Construct helper.
     */
    public function __construct(?ai_provider_interface $provider = null, ?ai_prompt_builder $prompts = null) {
        // phpcs:ignore moodle.Files.LineLength
        $this->facade = new ai_workflow_facade($provider ?? ai_provider_factory::create_from_config(), $prompts ?? new ai_prompt_builder());
    }

    /**
     * Teacher materials blocked response helper.
     */
    private function teacher_materials_blocked_response(): string {
        return privacy_guard::teacher_materials_external_block_message();
    }

    /**
     * Can use teacher materials helper.
     */
    private function can_use_teacher_materials(): bool {
        return privacy_guard::can_use_teacher_materials_with_current_provider();
    }

    /**
     * Ask tutor helper.
     */
    public function ask_tutor(string $q): string {
        return $this->facade->ask_tutor($q);
    }

    /**
     * Ask with course materials helper.
     */
    public function ask_with_course_materials(string $q, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->ask_with_course_materials($q, $m)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Ask with rag context helper.
     */
    public function ask_with_rag_context(string $q, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->ask_with_rag_context($q, $c)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Generate quiz helper.
     */
    public function generate_quiz(string $t, string $d): string {
        return $this->facade->generate_quiz($t, $d);
    }

    /**
     * Generate quiz from course materials helper.
     */
    public function generate_quiz_from_course_materials(string $f, string $d, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_quiz_from_course_materials($f, $d, $m)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Generate quiz with rag context helper.
     */
    public function generate_quiz_with_rag_context(string $f, string $d, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_quiz_with_rag_context($f, $d, $c)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Generate mindmap helper.
     */
    public function generate_mindmap(string $t): string {
        return $this->facade->generate_mindmap($t);
    }

    /**
     * Generate mindmap from course materials helper.
     */
    public function generate_mindmap_from_course_materials(string $f, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_mindmap_from_course_materials($f, $m)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Generate mindmap with rag context helper.
     */
    public function generate_mindmap_with_rag_context(string $f, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_mindmap_with_rag_context($f, $c)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Generate xr scenario helper.
     */
    public function generate_xr_scenario(string $t, string $e): string {
        return $this->facade->generate_xr_scenario($t, $e);
    }

    /**
     * Generate xr scenario from course materials helper.
     */
    public function generate_xr_scenario_from_course_materials(string $f, string $e, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_xr_scenario_from_course_materials($f, $e, $m)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Generate xr scenario with rag context helper.
     */
    public function generate_xr_scenario_with_rag_context(string $f, string $e, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_xr_scenario_with_rag_context($f, $e, $c)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Summarize course materials helper.
     */
    public function summarize_course_materials(string $f, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->summarize_course_materials($f, $m)
            : $this->teacher_materials_blocked_response();
    }

    /**
     * Summarize with rag context helper.
     */
    public function summarize_with_rag_context(string $f, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->summarize_with_rag_context($f, $c)
            : $this->teacher_materials_blocked_response();
    }
}
