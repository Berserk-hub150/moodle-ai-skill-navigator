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

namespace local_aiskillnavigator\service\skill;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Demo student skill data.
/**
 * Student profile data implementation.
 */
class student_profile_data {
    /**
     * Get helper.
     */
    public function get(int $userid): array {
        return [
            'userid' => $userid,
            'skills' => [
                // phpcs:ignore moodle.Files.LineLength
                ['name' => 'AI Fundamentals', 'score' => 78, 'status' => 'Good', 'description' => 'The student understands basic AI concepts.', 'nextaction' => 'Try an applied exercise about model evaluation.'],
                // phpcs:ignore moodle.Files.LineLength
                ['name' => 'IoT Basics', 'score' => 61, 'status' => 'Medium', 'description' => 'The student should reinforce data flow concepts.', 'nextaction' => 'Review the IoT data acquisition module.'],
                // phpcs:ignore moodle.Files.LineLength
                ['name' => 'Digital Twin', 'score' => 43, 'status' => 'Weak', 'description' => 'The student needs work on virtual models and synchronisation.', 'nextaction' => 'Complete a micro-quiz about sensor data.'],
                // phpcs:ignore moodle.Files.LineLength
                ['name' => 'Virtual Worlds', 'score' => 69, 'status' => 'Medium', 'description' => 'The student understands immersive learning basics.', 'nextaction' => 'Analyse a smart factory training scenario.'],
            ],
            'main_gap' => 'Digital Twin',
        ];
    }
}
