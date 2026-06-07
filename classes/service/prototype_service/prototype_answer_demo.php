<?php

namespace local_aiskillnavigator\service\prototype_service;

defined('MOODLE_INTERNAL') || die();

// Demo answer data for the old prototype service.
class prototype_answer_demo {
    public function get(string $question): array {
        $question = trim($question) !== '' ? trim($question) : 'What is the relationship between IoT and Digital Twin?';

        return [
            'question' => $question,
            'answer' => 'A Digital Twin is a virtual model of a physical system. IoT devices feed it with sensor data.',
            'grounding' => [
                'Based on the course skill map: AI, IoT, Digital Twin and Virtual Worlds.',
                'The final version can show citations from Moodle materials.',
            ],
            'nextsteps' => [
                'Review the Digital Twin Architecture module.',
                'Complete a micro-quiz about IoT data acquisition.',
                'Try a smart factory scenario.',
            ],
        ];
    }
}
