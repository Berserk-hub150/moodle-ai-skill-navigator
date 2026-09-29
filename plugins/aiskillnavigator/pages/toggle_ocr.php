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
require_once(__DIR__ . '/../includes/document_ocr_toggle_helper.php');

$courseid = required_param('courseid', PARAM_INT);
$mode = optional_param('mode', 'toggle', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
\local_aiskillnavigator\service\request_access::require_write($context, 'moodle/course:update');

$current = local_aisn_document_ocr_course_enabled($courseid);

if ($mode === 'on') {
    $newvalue = '1';
} else if ($mode === 'off') {
    $newvalue = '0';
} else {
    $newvalue = $current ? '0' : '1';
}

local_aisn_document_ocr_set_course_enabled($courseid, $newvalue === '1');

// Provider availability: today Mistral is the advanced OCR provider.
// This remains generic at course level, so future OCR providers can reuse the same course toggle.
if ($newvalue === '1') {
    // phpcs:ignore moodle.Files.LineLength
    \core\notification::success('OCR attivato per questo corso. Usalo per sincronizzare PDF/PPTX, poi puoi disattivarlo per navigare più velocemente.');
} else {
    \core\notification::success('OCR disattivato per questo corso. Gli altri corsi non vengono modificati.');
}

if ($returnurl !== '') {
    redirect(new moodle_url($returnurl));
}

redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
