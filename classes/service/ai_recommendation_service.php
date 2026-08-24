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

namespace local_aiskillnavigator\service;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Ai recommendation service implementation.
 */
class ai_recommendation_service {
    /**
     * Generate student recommendation helper.
     */
    public function generate_student_recommendation(array $profile): string {
        $gap = $profile['main_gap'] ?? 'the weakest skill';

        return 'The main detected gap is "' . $gap . '". '
            . 'The student should review the related material, complete a short adaptive quiz, '
            . 'and then work on a practical scenario connected to AI, IoT and Digital Twin concepts.';
    }

    /**
     * Generate teacher recommendation helper.
     */
    public function generate_teacher_recommendation(array $overview): string {
        if (empty($overview['weakestskills'])) {
            return 'No relevant skill gap detected yet.';
        }

        $weakest = $overview['weakestskills'][0];

        return 'The weakest course-level skill is "' . $weakest['name'] . '" with an average score of '
            . $weakest['average'] . '%. A recovery activity and a targeted micro-quiz are recommended.';
    }
}
