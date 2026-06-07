<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/privacy_guard.php');


class real_ai_service {
    private ai_workflow_facade $facade;

    public function __construct(?ai_provider_interface $provider = null, ?ai_prompt_builder $prompts = null) {
        $this->facade = new ai_workflow_facade($provider ?? ai_provider_factory::create_from_config(), $prompts ?? new ai_prompt_builder());
    }

    private function teacher_materials_blocked_response(): string {
        return privacy_guard::teacher_materials_external_block_message();
    }

    private function can_use_teacher_materials(): bool {
        return privacy_guard::can_use_teacher_materials_with_current_provider();
    }

    public function ask_tutor(string $q): string {
        return $this->facade->ask_tutor($q);
    }

    public function ask_with_course_materials(string $q, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->ask_with_course_materials($q, $m)
            : $this->teacher_materials_blocked_response();
    }

    public function ask_with_rag_context(string $q, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->ask_with_rag_context($q, $c)
            : $this->teacher_materials_blocked_response();
    }

    public function generate_quiz(string $t, string $d): string {
        return $this->facade->generate_quiz($t, $d);
    }

    public function generate_quiz_from_course_materials(string $f, string $d, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_quiz_from_course_materials($f, $d, $m)
            : $this->teacher_materials_blocked_response();
    }

    public function generate_quiz_with_rag_context(string $f, string $d, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_quiz_with_rag_context($f, $d, $c)
            : $this->teacher_materials_blocked_response();
    }

    public function generate_mindmap(string $t): string {
        return $this->facade->generate_mindmap($t);
    }

    public function generate_mindmap_from_course_materials(string $f, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_mindmap_from_course_materials($f, $m)
            : $this->teacher_materials_blocked_response();
    }

    public function generate_mindmap_with_rag_context(string $f, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_mindmap_with_rag_context($f, $c)
            : $this->teacher_materials_blocked_response();
    }

    public function generate_xr_scenario(string $t, string $e): string {
        return $this->facade->generate_xr_scenario($t, $e);
    }

    public function generate_xr_scenario_from_course_materials(string $f, string $e, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_xr_scenario_from_course_materials($f, $e, $m)
            : $this->teacher_materials_blocked_response();
    }

    public function generate_xr_scenario_with_rag_context(string $f, string $e, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->generate_xr_scenario_with_rag_context($f, $e, $c)
            : $this->teacher_materials_blocked_response();
    }

    public function summarize_course_materials(string $f, array $m): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->summarize_course_materials($f, $m)
            : $this->teacher_materials_blocked_response();
    }

    public function summarize_with_rag_context(string $f, string $c): string {
        return $this->can_use_teacher_materials()
            ? $this->facade->summarize_with_rag_context($f, $c)
            : $this->teacher_materials_blocked_response();
    }
}