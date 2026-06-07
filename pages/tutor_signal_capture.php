<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/tutor_signal_helper.php');

global $USER;

$courseid = required_param('courseid', PARAM_INT);
$question = required_param('question', PARAM_RAW_TRIMMED);
$answer = required_param('answer', PARAM_RAW_TRIMMED);
$sourcemode = optional_param('sourcemode', 'unknown', PARAM_TEXT);
$materialsraw = optional_param('materials', '', PARAM_RAW_TRIMMED);

$course = get_course($courseid);
require_login($course);
require_sesskey();

$context = context_course::instance($courseid);


local_aisn_require_student_area($context);
if (
    !has_capability('local/aiskillnavigator:viewstudent', $context) &&
    !has_capability('local/aiskillnavigator:viewteacher', $context) &&
    !has_capability('moodle/course:view', $context) &&
    !is_siteadmin()
) {
    throw new required_capability_exception($context, 'moodle/course:view', 'nopermissions', '');
}

header('Content-Type: application/json; charset=utf-8');

try {
    $materials = [];

    if ($materialsraw !== '') {
        $decoded = json_decode($materialsraw, true);
        if (is_array($decoded)) {
            $materials = $decoded;
        }
    }

    local_aiskillnavigator_tutor_signal_store(
        (int)$courseid,
        (int)$USER->id,
        $question,
        $sourcemode,
        $materials,
        $answer
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Tutor signal saved.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
