<?php

namespace local_aiskillnavigator\service\skill;

defined('MOODLE_INTERNAL') || die();

// Demo student skill data.
class student_profile_data {
    public function get(int $userid): array {
        return [
            'userid' => $userid,
            'skills' => [
                ['name' => 'AI Fundamentals', 'score' => 78, 'status' => 'Good', 'description' => 'The student understands basic AI concepts.', 'nextaction' => 'Try an applied exercise about model evaluation.'],
                ['name' => 'IoT Basics', 'score' => 61, 'status' => 'Medium', 'description' => 'The student should reinforce data flow concepts.', 'nextaction' => 'Review the IoT data acquisition module.'],
                ['name' => 'Digital Twin', 'score' => 43, 'status' => 'Weak', 'description' => 'The student needs work on virtual models and synchronisation.', 'nextaction' => 'Complete a micro-quiz about sensor data.'],
                ['name' => 'Virtual Worlds', 'score' => 69, 'status' => 'Medium', 'description' => 'The student understands immersive learning basics.', 'nextaction' => 'Analyse a smart factory training scenario.'],
            ],
            'main_gap' => 'Digital Twin',
        ];
    }
}
