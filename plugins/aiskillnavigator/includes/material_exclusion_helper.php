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
 * Local aisn course cm id from material title helper.
 */
function local_aisn_course_cm_id_from_material_title(string $title): int {
    if (preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', $title, $m)) {
        return (int)$m[1];
    }

    return 0;
}

/**
 * Local aisn course material is excluded helper.
 */
function local_aisn_course_material_is_excluded(int $courseid, int $cmid): bool {
    if ($courseid <= 1 || $cmid <= 0) {
        return false;
    }

    return (string)get_config('local_aiskillnavigator', 'cm_ai_excluded_' . $cmid) === '1';
}

/**
 * Local aisn course material set excluded helper.
 */
function local_aisn_course_material_set_excluded(int $courseid, int $cmid, bool $excluded): void {
    if ($courseid <= 1 || $cmid <= 0) {
        return;
    }

    set_config(
        'cm_ai_excluded_' . $cmid,
        $excluded ? '1' : '0',
        'local_aiskillnavigator'
    );
}
