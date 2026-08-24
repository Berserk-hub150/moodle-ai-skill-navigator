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
require_once(__DIR__ . '/../includes/tutor_signal_helper.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/tutor_analytics.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('page_tutor_analytics_title', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('page_tutor_analytics_heading', 'local_aiskillnavigator'));

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid aisn-tutor-analytics-page');

echo html_writer::tag('h2', 'Tutor analyst');
echo html_writer::tag(
    'p',
    // phpcs:ignore moodle.Files.LineLength
    'Le domande fatte dagli studenti al tutor vengono raccolte come segnali didattici: ability richieste, dubbi ricorrenti e argomenti da rinforzare.',
    ['class' => 'lead']
);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher.php', ['courseid' => $courseid]),
        'Back to teacher dashboard',
        ['class' => 'btn btn-secondary']
    ) . ' ' .
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        'Back to course',
        ['class' => 'btn btn-outline-secondary']
    ),
    'mb-4'
);

echo local_aiskillnavigator_tutor_signal_teacher_panel((int)$courseid);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher.php', ['courseid' => $courseid]),
        'Back to teacher dashboard',
        ['class' => 'btn btn-secondary']
    ),
    'mt-4'
);

echo html_writer::end_div();

if (function_exists('local_aisn_ai_output_formatter_assets')) {
    echo local_aisn_ai_output_formatter_assets();
}

echo $OUTPUT->footer();
