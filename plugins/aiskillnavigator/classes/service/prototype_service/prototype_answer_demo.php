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

// Demo answer data for the old prototype service.
/**
 * Prototype answer demo implementation.
 */
class prototype_answer_demo {
    /**
     * Get helper.
     */
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
