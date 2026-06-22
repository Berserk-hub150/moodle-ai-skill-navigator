<?php

defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aisn_document_ocr_current_courseid')) {
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
    function local_aisn_document_ocr_config_key(int $courseid): string {
        return 'document_ocr_enabled_course_' . $courseid;
    }
}

if (!function_exists('local_aisn_document_ocr_course_enabled')) {
    function local_aisn_document_ocr_course_enabled(int $courseid): bool {
        if ($courseid <= SITEID) {
            return false;
        }

        return (string)get_config('local_aiskillnavigator', local_aisn_document_ocr_config_key($courseid)) === '1';
    }
}

if (!function_exists('local_aisn_document_ocr_cmid_enabled')) {
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
        $html .= '<div class="aisn-ocr-title" style="font-weight:800;font-size:12px;color:#111827;margin-bottom:7px;text-transform:uppercase;">DOCUMENT OCR</div>';
        $html .= '<a class="aisn-ocr-btn" href="' . $url->out(false) . '" style="display:block;text-align:center;padding:9px 12px;border:1px solid;border-radius:8px;text-decoration:none;font-size:14px;' . $buttonstyle . '">' . s($label) . '</a>';
        $html .= '<div class="aisn-ocr-status" style="font-size:11px;color:#64748b;margin-top:6px;line-height:1.35;">' . s($status) . '</div>';
        $html .= '</div>';

        return $html;
    }
}
