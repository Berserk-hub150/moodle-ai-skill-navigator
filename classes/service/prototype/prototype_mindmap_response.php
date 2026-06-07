<?php

namespace local_aiskillnavigator\service\prototype;

defined('MOODLE_INTERNAL') || die();

// Demo mind map JSON used by the prototype provider.
class prototype_mindmap_response {
    public function get(): string {
        return json_encode([
            'title' => 'Mappa AI Skill Navigator',
            'central_topic' => 'AI Skill Navigator',
            'summary' => 'Sintesi dei concetti principali del plugin Moodle.',
            'central_description' => 'Supporta tutor, quiz, RAG e scenari XR.',
            'branches' => [
                $this->branch('Tutor AI', 'Supporta domande dello studente.'),
                $this->branch('Quiz', 'Produce micro-test formativi.'),
                $this->branch('Mind Map', 'Visualizza relazioni tra concetti.'),
                $this->branch('XR', 'Genera scenari per mondi virtuali.'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function branch(string $title, string $description): array {
        return [
            'title' => $title,
            'description' => $description,
            'children' => [
                ['title' => 'Nodo 1', 'description' => 'Primo punto da ripassare.'],
                ['title' => 'Nodo 2', 'description' => 'Secondo punto da ripassare.'],
            ],
        ];
    }
}
