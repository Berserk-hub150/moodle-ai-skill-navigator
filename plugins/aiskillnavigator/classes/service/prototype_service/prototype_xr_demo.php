<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiskillnavigator\service\prototype_service;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Demo XR scenario data for the old prototype service.
/**
 * Prototype xr demo implementation.
 */
class prototype_xr_demo {
    /**
     * Get helper.
     */
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
