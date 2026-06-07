<?php

namespace local_aiskillnavigator\service\workflow;

defined('MOODLE_INTERNAL') || die();

// Runs tutor prompts.
class tutor_workflow extends base_workflow {
    public function ask(string $question): string {
        $question = trim($question);
        return $question === '' ? 'Scrivi una domanda prima di inviarla al tutor AI.'
            : $this->provider->generate($this->prompts->tutor_prompt($question), 1000);
    }

    public function materials(string $question, array $materials): string {
        $question = trim($question);
        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor del corso.';
        }

        return empty($materials) ? 'Non sono stati trovati materiali del docente rilevanti per rispondere alla domanda.'
            : $this->provider->generate($this->prompts->tutor_with_materials_prompt($question, $materials), 1400);
    }

    public function rag(string $question, string $context): string {
        $question = trim($question);
        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor del corso.';
        }

        return trim($context) === '' ? 'Non sono stati trovati materiali rilevanti nel RAG index per rispondere alla domanda.'
            : $this->provider->generate($this->prompts->tutor_with_rag_prompt($question, $context), 1400);
    }
}
