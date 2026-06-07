<?php

namespace local_aiskillnavigator\service\prototype_service;

defined('MOODLE_INTERNAL') || die();

// Demo quiz data for the old prototype service.
class prototype_quiz_demo {
    public function get(string $topic, string $difficulty): array {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin';
        $difficulty = trim($difficulty) !== '' ? trim($difficulty) : 'medium';

        return [
            'topic' => $topic,
            'difficulty' => $difficulty,
            'questions' => [
                $this->mcq('What is the main purpose of a Digital Twin?', 'To create a virtual model connected to a physical system'),
                $this->mcq('Which technology feeds real-time data into a Digital Twin?', 'IoT sensors'),
                ['type' => 'Open question', 'question' => 'Explain how a Virtual Worlds scenario helps students understand synchronisation.', 'expectedanswer' => 'Mention simulation, real-time data and sensor feedback.'],
            ],
        ];
    }

    private function mcq(string $question, string $correct): array {
        return [
            'type' => 'Multiple choice',
            'question' => $question,
            'options' => [$correct, 'Static PDFs only', 'Manual paper forms', 'Offline spreadsheets only'],
            'correct' => $correct,
            'explanation' => 'The correct option is the one tied to Digital Twin data flow.',
        ];
    }
}
