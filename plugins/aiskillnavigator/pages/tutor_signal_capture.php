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
