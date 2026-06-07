<?php

namespace local_aiskillnavigator\service\prototype_service;

defined('MOODLE_INTERNAL') || die();

// Demo XR scenario data for the old prototype service.
class prototype_xr_demo {
    public function get(string $topic, string $environment): array {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin and IoT';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return [
            'title' => $environment . ' Training Scenario: ' . $topic,
            'learningobjective' => 'Understand how IoT data updates a Digital Twin.',
            'environment' => $environment,
            'story' => 'The learner enters a smart factory with abnormal sensor data.',
            'tasks' => [
                'Inspect the virtual machine.',
                'Compare sensor values with the dashboard.',
                'Detect the inconsistent stream.',
                'Apply a correction strategy.',
                'Answer a short reflection quiz.',
            ],
            'assessment' => [
                'Correct sensor identification.',
                'Correct data-flow explanation.',
                'Justified correction.',
                'Completed reflection quiz.',
            ],
            'extensions' => ['Add anomaly detection.', 'Add collaboration.', 'Connect results to Moodle skills.'],
        ];
    }
}
