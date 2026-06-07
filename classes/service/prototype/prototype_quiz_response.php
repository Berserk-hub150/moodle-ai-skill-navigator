<?php

namespace local_aiskillnavigator\service\prototype;

defined('MOODLE_INTERNAL') || die();

// Demo quiz JSON used when no external model is configured.
class prototype_quiz_response {
    public function get(): string {
        return json_encode([
            'title' => 'Micro-test dimostrativo',
            'topic' => 'AI, IoT e Digital Twin',
            'difficulty' => 'medium',
            'questions' => [
                $this->question('Qual è il ruolo principale dell IoT in un Digital Twin?', 'Fornire dati dal sistema fisico', 'IoT e Digital Twin'),
                $this->question('Perché un tutor AI può essere utile in un LMS?', 'Supportare studio e recupero personalizzato', 'AI per apprendimento'),
                $this->question('Che vantaggio dà il RAG rispetto a una risposta generica?', 'Usa materiali del corso come contesto', 'RAG'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function question(string $text, string $answer, string $skill): array {
        return [
            'question' => $text,
            'options' => [$answer, 'Sostituire il docente', 'Ignorare i materiali', 'Disabilitare la valutazione'],
            'correct_index' => 0,
            'explanation' => 'La risposta corretta collega il concetto al corso.',
            'skill' => $skill,
        ];
    }
}
