<?php

namespace local_aiskillnavigator\service\workflow;

defined('MOODLE_INTERNAL') || die();

// Runs mind map prompts.
class mindmap_workflow extends base_workflow {
    public function plain(string $topic): string {
        return $this->provider->generate($this->prompts->mindmap_prompt($topic), 1500);
    }

    public function materials(string $focus, array $materials): string {
        if (empty($materials)) {
            return $this->plain($this->fallback($focus, 'Course materials'));
        }

        return $this->provider->generate($this->prompts->mindmap_from_materials_prompt($focus, $materials), 1800);
    }

    public function rag(string $focus, string $context): string {
        if (trim($context) === '') {
            return $this->plain($this->fallback($focus, 'Course materials'));
        }

        return $this->provider->generate($this->prompts->mindmap_with_rag_prompt($focus, $context), 1800);
    }
}
