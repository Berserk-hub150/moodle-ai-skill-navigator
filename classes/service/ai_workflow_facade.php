<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/workflow/*.php') as $file) {
    require_once($file);
}

// Keeps page code small while workflows stay split by feature.
class ai_workflow_facade {
    private workflow\tutor_workflow $tutor; private workflow\quiz_workflow $quiz;
    private workflow\mindmap_workflow $mindmap; private workflow\xr_workflow $xr;
    private workflow\summary_workflow $summary;

    public function __construct(ai_provider_interface $provider, ai_prompt_builder $prompts) {
        $this->tutor = new workflow\tutor_workflow($provider, $prompts);
        $this->quiz = new workflow\quiz_workflow($provider, $prompts);
        $this->mindmap = new workflow\mindmap_workflow($provider, $prompts);
        $this->xr = new workflow\xr_workflow($provider, $prompts);
        $this->summary = new workflow\summary_workflow($provider, $prompts);
    }

    public function ask_tutor(string $q): string { return $this->tutor->ask($q); }
    public function ask_with_course_materials(string $q, array $m): string { return $this->tutor->materials($q, $m); }
    public function ask_with_rag_context(string $q, string $c): string { return $this->tutor->rag($q, $c); }
    public function generate_quiz(string $t, string $d): string { return $this->quiz->plain($t, $d); }
    public function generate_quiz_from_course_materials(string $f, string $d, array $m): string { return $this->quiz->materials($f, $d, $m); }
    public function generate_quiz_with_rag_context(string $f, string $d, string $c): string { return $this->quiz->rag($f, $d, $c); }
    public function generate_mindmap(string $t): string { return $this->mindmap->plain($t); }
    public function generate_mindmap_from_course_materials(string $f, array $m): string { return $this->mindmap->materials($f, $m); }
    public function generate_mindmap_with_rag_context(string $f, string $c): string { return $this->mindmap->rag($f, $c); }
    public function generate_xr_scenario(string $t, string $e): string { return $this->xr->plain($t, $e); }
    public function generate_xr_scenario_from_course_materials(string $f, string $e, array $m): string { return $this->xr->materials($f, $e, $m); }
    public function generate_xr_scenario_with_rag_context(string $f, string $e, string $c): string { return $this->xr->rag($f, $e, $c); }
    public function summarize_course_materials(string $f, array $m): string { return $this->summary->materials($f, $m); }
    public function summarize_with_rag_context(string $f, string $c): string { return $this->summary->rag($f, $c); }
}
