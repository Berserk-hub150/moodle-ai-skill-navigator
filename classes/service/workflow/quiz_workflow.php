<?php

namespace local_aiskillnavigator\service\workflow;

defined('MOODLE_INTERNAL') || die();

// Runs quiz prompts.
class quiz_workflow extends base_workflow {
    public function plain(string $topic, string $difficulty): string {
        return $this->provider->generate($this->prompts->quiz_prompt($topic, $difficulty), 2200);
    }

    public function materials(string $focus, string $difficulty, array $materials): string {
        if (empty($materials)) {
            return $this->plain($this->fallback($focus, 'Course materials'), $difficulty);
        }

        return $this->provider->generate($this->prompts->quiz_from_materials_prompt($focus, $difficulty, $materials), 2400);
    }

    public function rag(string $focus, string $difficulty, string $context): string {
        if (trim($context) === '') {
            return $this->plain($this->fallback($focus, 'Course materials'), $difficulty);
        }

        return $this->provider->generate($this->prompts->quiz_with_rag_prompt($focus, $difficulty, $context), 2400);
    }
}
