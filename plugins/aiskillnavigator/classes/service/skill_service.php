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

foreach (glob(__DIR__ . '/skill/*.php') as $file) {
    require_once($file);
}

// Provides demo skill data for student and teacher pages.
/**
 * Skill service implementation.
 */
class skill_service {
    /**
     * Get student skill profile helper.
     */
    public function get_student_skill_profile(int $userid): array {
        return (new skill\student_profile_data())->get($userid);
    }

    /**
     * Get teacher skill overview helper.
     */
    public function get_teacher_skill_overview(): array {
        return (new skill\teacher_overview_data())->get();
    }

    /**
     * Get score badge class helper.
     */
    public function get_score_badge_class(int $score): string {
        return (new skill\score_badge_resolver())->get($score);
    }
}
