<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/document_ocr_toggle_helper.php');

$courseid = required_param('courseid', PARAM_INT);
$mode = optional_param('mode', 'toggle', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

require_sesskey();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

$current = local_aisn_document_ocr_course_enabled($courseid);

if ($mode === 'on') {
    $newvalue = '1';
} else if ($mode === 'off') {
    $newvalue = '0';
} else {
    $newvalue = $current ? '0' : '1';
}

set_config(local_aisn_document_ocr_config_key($courseid), $newvalue, 'local_aiskillnavigator');

// Provider availability: today Mistral is the advanced OCR provider.
// This remains generic at course level, so future OCR providers can reuse the same course toggle.
if ($newvalue === '1') {
    set_config('mistral_ocr_enabled', '1', 'local_aiskillnavigator');
    set_config('mistral_ocr_timeout', '120', 'local_aiskillnavigator');
    \core\notification::success('OCR attivato per questo corso. Usalo per sincronizzare PDF/PPTX, poi puoi disattivarlo per navigare più velocemente.');
} else {
    set_config('mistral_ocr_timeout', '30', 'local_aiskillnavigator');
    \core\notification::success('OCR disattivato per questo corso. Gli altri corsi non vengono modificati.');
}

if ($returnurl !== '') {
    redirect(new moodle_url($returnurl));
}

redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
