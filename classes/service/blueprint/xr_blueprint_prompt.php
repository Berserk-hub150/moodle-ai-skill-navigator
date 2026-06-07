<?php

namespace local_aiskillnavigator\service\blueprint;

defined('MOODLE_INTERNAL') || die();

// Builds the JSON prompt for exportable XR blueprints.
class xr_blueprint_prompt {
    public function system(): string {
        return 'You are a strict JSON generator for educational XR blueprints. Return only valid JSON.';
    }

    public function build(string $topic, string $environment, string $context): string {
        $prompt = "Genera un blueprint XR per un plugin Moodle universitario.\n\n"
            . "Topic/focus: {$topic}\n"
            . "Ambiente virtuale: {$environment}\n\n"
            . "Genera coordinate, oggetti, punti di interesse, task, checkpoint, trigger, dialoghi e obiettivi didattici.\n\n";

        if (trim($context) !== '') {
            $prompt .= "CONTESTO MATERIALI/RAG:\n{$context}\nUsa solo i concetti presenti nel contesto.\n\n";
        }

        return $prompt
            . "Rispondi solo con JSON valido, senza Markdown.\n"
            . "Genera almeno 5 objects, 4 points_of_interest, 5 tasks, 4 checkpoints, 4 triggers e 4 dialogs.\n"
            . "Usa coordinate x/y numeriche da 0 a 100.\n";
    }
}
