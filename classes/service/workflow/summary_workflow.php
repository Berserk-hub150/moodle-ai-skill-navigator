<?php

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
