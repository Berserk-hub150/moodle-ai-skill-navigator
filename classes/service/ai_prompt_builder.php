<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();
foreach (['shared/text_tools.php', 'shared/material_context_builder.php', 'shared/style_notes.php', 'base_prompt_helper.php'] as $file) {
    require_once(__DIR__ . '/prompt/' . $file);
}

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/prompt'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        require_once((string) $file);
    }
}

// Keeps the old prompt methods while the code is split into smaller files.
class ai_prompt_builder {
    private array $builders;

    public function __construct() {
        $ns = '\\local_aiskillnavigator\\service\\prompt\\';
        $this->builders = [
            'tutor' => new ($ns . 'tutor_prompt_builder')(),
            'quiz' => new ($ns . 'quiz_prompt_builder')(),
            'mindmap' => new ($ns . 'mindmap_prompt_builder')(),
            'xr' => new ($ns . 'xr_prompt_builder')(),
            'summary' => new ($ns . 'summary_prompt_builder')(),
        ];
    }

    public function tutor_prompt(string $question): string { return $this->builders['tutor']->plain($question); }
    public function tutor_with_materials_prompt(string $question, array $materials): string { return $this->builders['tutor']->with_materials($question, $materials); }
    public function tutor_with_rag_prompt(string $question, string $ragcontext): string { return $this->builders['tutor']->with_rag($question, $ragcontext); }
    public function quiz_prompt(string $topic, string $difficulty): string { return $this->builders['quiz']->plain($topic, $difficulty); }
    public function quiz_from_materials_prompt(string $focus, string $difficulty, array $materials): string { return $this->builders['quiz']->from_materials($focus, $difficulty, $materials); }
    public function quiz_with_rag_prompt(string $focus, string $difficulty, string $ragcontext): string { return $this->builders['quiz']->with_rag($focus, $difficulty, $ragcontext); }
    public function mindmap_prompt(string $topic): string { return $this->builders['mindmap']->plain($topic); }
    public function mindmap_from_materials_prompt(string $focus, array $materials): string { return $this->builders['mindmap']->from_materials($focus, $materials); }
    public function mindmap_with_rag_prompt(string $focus, string $ragcontext): string { return $this->builders['mindmap']->with_rag($focus, $ragcontext); }
    public function xr_scenario_prompt(string $topic, string $environment): string { return $this->builders['xr']->plain($topic, $environment); }
    public function xr_scenario_from_materials_prompt(string $focus, string $environment, array $materials): string { return $this->builders['xr']->from_materials($focus, $environment, $materials); }
    public function xr_scenario_with_rag_prompt(string $focus, string $environment, string $ragcontext): string { return $this->builders['xr']->with_rag($focus, $environment, $ragcontext); }
    public function summarize_materials_prompt(string $focus, array $materials): string { return $this->builders['summary']->from_materials($focus, $materials); }
    public function summarize_rag_prompt(string $focus, string $ragcontext): string { return $this->builders['summary']->with_rag($focus, $ragcontext); }
}
