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

if (!function_exists('local_aisn_document_ocr_current_courseid')) {
    /**
     * Local aisn document ocr current courseid helper.
     */
    function local_aisn_document_ocr_current_courseid(): int {
        global $PAGE, $COURSE;

        $courseid = optional_param('courseid', 0, PARAM_INT);

        if ($courseid > 0) {
            return $courseid;
        }

        if (isset($PAGE) && isset($PAGE->course) && !empty($PAGE->course->id) && (int)$PAGE->course->id > SITEID) {
            return (int)$PAGE->course->id;
        }

        if (isset($COURSE) && !empty($COURSE->id) && (int)$COURSE->id > SITEID) {
            return (int)$COURSE->id;
        }

        return 0;
    }
}

if (!function_exists('local_aisn_document_ocr_config_key')) {
    /**
     * Local aisn document ocr config key helper.
     */
    function local_aisn_document_ocr_config_key(int $courseid): string {
        return 'document_ocr_enabled_course_' . $courseid;
    }
}

if (!function_exists('local_aisn_document_ocr_course_enabled')) {
    /**
     * Local aisn document ocr course enabled helper.
     */
    function local_aisn_document_ocr_course_enabled(int $courseid): bool {
        if ($courseid <= SITEID) {
            return false;
        }

        return (string)get_config('local_aiskillnavigator', local_aisn_document_ocr_config_key($courseid)) === '1';
    }
}

if (!function_exists('local_aisn_document_ocr_cmid_enabled')) {
    /**
     * Local aisn document ocr cmid enabled helper.
     */
    function local_aisn_document_ocr_cmid_enabled(int $cmid): bool {
        if ($cmid <= 0) {
            return false;
        }

        $cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);

        if (!$cm || empty($cm->course)) {
            return false;
        }

        return local_aisn_document_ocr_course_enabled((int)$cm->course);
    }
}

if (!function_exists('local_aisn_document_ocr_user_can_toggle')) {
    /**
     * Local aisn document ocr user can toggle helper.
     */
    function local_aisn_document_ocr_user_can_toggle(int $courseid): bool {
        if ($courseid <= SITEID || !isloggedin() || isguestuser()) {
            return false;
        }

        $context = context_course::instance($courseid, IGNORE_MISSING);

        if (!$context) {
            return false;
        }

        return has_capability('moodle/course:update', $context);
    }
}

if (!function_exists('local_aisn_document_ocr_toggle_url')) {
    /**
     * Local aisn document ocr toggle url helper.
     */
    function local_aisn_document_ocr_toggle_url(int $courseid, bool $enabled): moodle_url {
        $returnurl = qualified_me();
        $mode = $enabled ? 'off' : 'on';

        return new moodle_url('/local/aiskillnavigator/pages/toggle_ocr.php', [
            'courseid' => $courseid,
            'mode' => $mode,
            'returnurl' => $returnurl,
            'sesskey' => sesskey(),
        ]);
    }
}

if (!function_exists('local_aisn_render_sidebar_ocr_toggle_button')) {
    /**
     * Local aisn render sidebar ocr toggle button helper.
     */
    function local_aisn_render_sidebar_ocr_toggle_button(int $courseid = 0): string {
        if ($courseid <= 0) {
            $courseid = local_aisn_document_ocr_current_courseid();
        }

        if (!local_aisn_document_ocr_user_can_toggle($courseid)) {
            return '';
        }

        $enabled = local_aisn_document_ocr_course_enabled($courseid);
        $url = local_aisn_document_ocr_toggle_url($courseid, $enabled);

        $label = $enabled ? 'Disattiva OCR' : 'Attiva OCR';
        $status = $enabled ? 'OCR attivo per questo corso' : 'OCR disattivato per questo corso';

        $buttonstyle = $enabled
            ? 'border-color:#dc2626;color:#991b1b;background:#fff5f5;'
            : 'border-color:#15803d;color:#166534;background:#f0fdf4;';

        $html = '';
        $html .= '<div id="aisn-ocr-sidebar-toggle" style="margin-top:14px;">';
        // phpcs:ignore moodle.Files.LineLength
        $html .= '<div class="aisn-ocr-title" style="font-weight:800;font-size:12px;color:#111827;margin-bottom:7px;text-transform:uppercase;">DOCUMENT OCR</div>';
        // phpcs:ignore moodle.Files.LineLength
        $html .= '<a class="aisn-ocr-btn" href="' . $url->out(false) . '" style="display:block;text-align:center;padding:9px 12px;border:1px solid;border-radius:8px;text-decoration:none;font-size:14px;' . $buttonstyle . '">' . s($label) . '</a>';
        // phpcs:ignore moodle.Files.LineLength
        $html .= '<div class="aisn-ocr-status" style="font-size:11px;color:#64748b;margin-top:6px;line-height:1.35;">' . s($status) . '</div>';
        $html .= '</div>';

        return $html;
    }
}
