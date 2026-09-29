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

/**
 * Scope plugin-owned student reports to the viewer's separate groups.
 */
class report_access {
    /**
     * Build a parameterised restriction usable by attempts and tutor analytics.
     *
     * @param int $courseid Report course.
     * @param string $userfield Trusted SQL user ID column, never request input.
     * @return array SQL condition and named parameters.
     */
    public static function condition(int $courseid, string $userfield = 'userid'): array {
        global $DB, $USER;
        $context = \context_course::instance($courseid);
        if (!has_capability('local/aiskillnavigator:viewteacher', $context)) {
            return ['1 = 0', []];
        }
        $course = get_course($courseid);
        if (
            groups_get_course_groupmode($course) != SEPARATEGROUPS
                || has_capability('moodle/site:accessallgroups', $context)
        ) {
            return ['1 = 1', []];
        }
        $groups = groups_get_all_groups($courseid, $USER->id, $course->defaultgroupingid, 'g.id');
        if (!$groups) {
            return ['1 = 0', []];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED, 'reportgroup');
        return ["$userfield IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid $insql)", $params];
    }

    /**
     * Load scoped diagnostic assessment attempts.
     *
     * @param int $courseid Report course.
     * @param int $assessmentid Assessment ID.
     * @param string $sort Trusted sort expression.
     * @return array Visible attempts.
     */
    public static function assessment_attempts(int $courseid, int $assessmentid, string $sort = 'timecreated ASC'): array {
        global $DB;
        [$scope, $params] = self::condition($courseid);
        return $DB->get_records_select(
            'local_aiskillnavigator_ass_att',
            "courseid = :reportcourse AND assessmentid = :reportassessment AND ($scope)",
            $params + ['reportcourse' => $courseid, 'reportassessment' => $assessmentid],
            $sort
        );
    }
}
