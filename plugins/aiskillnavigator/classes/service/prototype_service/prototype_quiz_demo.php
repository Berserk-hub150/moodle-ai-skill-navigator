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

// Demo quiz data for the old prototype service.
/**
 * Prototype quiz demo implementation.
 */
class prototype_quiz_demo {
    /**
     * Get helper.
     */
    public function get(string $topic, string $difficulty): array {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin';
        $difficulty = trim($difficulty) !== '' ? trim($difficulty) : 'medium';

        return [
            'topic' => $topic,
            'difficulty' => $difficulty,
            'questions' => [
                // phpcs:ignore moodle.Files.LineLength
                $this->mcq('What is the main purpose of a Digital Twin?', 'To create a virtual model connected to a physical system'),
                $this->mcq('Which technology feeds real-time data into a Digital Twin?', 'IoT sensors'),
                // phpcs:ignore moodle.Files.LineLength
                ['type' => 'Open question', 'question' => 'Explain how a Virtual Worlds scenario helps students understand synchronisation.', 'expectedanswer' => 'Mention simulation, real-time data and sensor feedback.'],
            ],
        ];
    }

    /**
     * Mcq helper.
     */
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
