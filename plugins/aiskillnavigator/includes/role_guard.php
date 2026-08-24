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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Central role guards for AI Skill Navigator.
 *
 * Goal:
 * - students use only student tools;
 * - teachers use only teacher tools;
 * - site admins can use both sides for testing/demo;
 * - every check is course-context based.
 */

if (!function_exists('local_aisn_is_course_teacher_like')) {
    /**
     * Local aisn is course teacher like helper.
     */
    function local_aisn_is_course_teacher_like(context_course $context): bool {
        return has_capability('moodle/course:update', $context)
            || has_capability('moodle/course:manageactivities', $context)
            || has_capability('local/aiskillnavigator:viewteacher', $context)
            || has_capability('local/aiskillnavigator:managematerials', $context)
            || has_capability('local/aiskillnavigator:manageassessments', $context);
    }
}

if (!function_exists('local_aisn_require_student_area')) {
    /**
     * Local aisn require student area helper.
     */
    function local_aisn_require_student_area(context_course $context): void {
        if (is_siteadmin()) {
            return;
        }

        require_capability('local/aiskillnavigator:viewstudent', $context);

        if (local_aisn_is_course_teacher_like($context)) {
            throw new required_capability_exception(
                $context,
                'local/aiskillnavigator:viewstudent',
                'nopermissions',
                ''
            );
        }
    }
}

if (!function_exists('local_aisn_require_teacher_area')) {
    /**
     * Local aisn require teacher area helper.
     */
    function local_aisn_require_teacher_area(context_course $context): void {
        if (is_siteadmin()) {
            return;
        }

        require_capability('local/aiskillnavigator:viewteacher', $context);
    }
}
