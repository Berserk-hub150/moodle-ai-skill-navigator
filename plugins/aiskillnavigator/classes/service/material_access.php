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
 * Resolve material access from the live activity, including availability rules.
 */
class material_access {
    /**
     * Check the current user's access to a material's source activity.
     *
     * @param \stdClass $material Stored material.
     * @return bool Whether the source is available to this user.
     */
    public static function can_read(\stdClass $material): bool {
        require_once(__DIR__ . '/../../includes/material_exclusion_helper.php');
        $courseid = (int)($material->courseid ?? 0);
        if ($courseid <= SITEID) {
            return false;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (
            !$context || (!has_capability('local/aiskillnavigator:viewstudent', $context)
                && !has_capability('local/aiskillnavigator:viewteacher', $context)
                && !has_capability('local/aiskillnavigator:managematerials', $context))
        ) {
            return false;
        }
        $cmid = (int)($material->sourcecmid ?? 0);
        if (!$cmid && preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', (string)($material->title ?? ''), $matches)) {
            $cmid = (int)$matches[1];
        }
        if (!$cmid || local_aisn_course_material_is_excluded($courseid, $cmid)) {
            return false;
        }
        $modinfo = get_fast_modinfo($courseid);
        $cm = $modinfo->cms[$cmid] ?? null;
        return $cm && $cm->uservisible;
    }
}
