<?php

namespace local_aiskillnavigator\service\prototype;

defined('MOODLE_INTERNAL') || die();

// Chooses a demo response from the prompt content.
class prototype_output_router {
    public function route(string $prompt): string {
        $lower = strtolower($prompt);

        if (str_contains($lower, '"questions"') || str_contains($lower, 'micro-test')) {
            return (new prototype_quiz_response())->get();
        }

        if (str_contains($lower, '"branches"') || str_contains($lower, 'mappa mentale')) {
            return (new prototype_mindmap_response())->get();
        }

        if (str_contains($lower, 'scenario') || str_contains($lower, 'virtual worlds')) {
            return (new prototype_xr_response())->get();
        }

        return 'Risposta dimostrativa: il sistema è attivo in modalità prototype.';
    }
}
