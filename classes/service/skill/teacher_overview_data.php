<?php

namespace local_aiskillnavigator\service\skill;

defined('MOODLE_INTERNAL') || die();

// Demo teacher overview data.
class teacher_overview_data {
    public function get(): array {
        return [
            'weakestskills' => [
                ['name' => 'Digital Twin', 'average' => 46, 'studentsatrisk' => 7, 'suggestion' => 'Generate a recovery quiz about synchronisation.'],
                ['name' => 'IoT Sensor Integration', 'average' => 54, 'studentsatrisk' => 5, 'suggestion' => 'Create an exercise on sensor data acquisition.'],
                ['name' => 'AI Model Evaluation', 'average' => 59, 'studentsatrisk' => 4, 'suggestion' => 'Assign an activity about accuracy, precision and recall.'],
            ],
            'suggestedactions' => [
                'Generate a recovery quiz for Digital Twin concepts.',
                'Create a Virtual Worlds scenario about IoT sensors.',
                'Recommend Digital Twin resources to students below 50%.',
            ],
        ];
    }
}
