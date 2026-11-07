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
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/flashcard_deck_helper.php');

global $PAGE, $OUTPUT;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$deckname = optional_param('deck', '', PARAM_FILE);

$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

$pageurl = new moodle_url('/local/aiskillnavigator/pages/flashcards.php', ['courseid' => $courseid]);

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('page_flashcards_title', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('page_flashcards_heading', 'local_aiskillnavigator'));
$PAGE->requires->js(new moodle_url('/local/aiskillnavigator/assets/aisn_flashcards.js'));

/**
 * Local aiskillnavigator flashcards problem helper.
 *
 * Resolves a deck error code reported by the deck helper.
 *
 * @param array $result Result returned by the deck helper.
 */
function local_aiskillnavigator_flashcards_problem(array $result): string {
    $key = 'flashcards_error_' . $result['errorcode'];

    if (!get_string_manager()->string_exists($key, 'local_aiskillnavigator')) {
        return get_string('flashcards_deckempty', 'local_aiskillnavigator');
    }

    return get_string($key, 'local_aiskillnavigator', $result['errorcontext']);
}

$deckfiles = local_aiskillnavigator_flashcard_list_deck_files();
$decknames = array_map('basename', $deckfiles);

$selected = in_array($deckname, $decknames, true) ? $deckname : reset($decknames);
$result = $selected === false ? null : local_aiskillnavigator_flashcard_load_deck_file(
    $deckfiles[array_search($selected, $decknames, true)]
);

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('p', get_string('flashcards_intro', 'local_aiskillnavigator'), ['class' => 'lead']);
echo html_writer::tag(
    'p',
    get_string('flashcards_course', 'local_aiskillnavigator', format_string($course->fullname)),
    ['class' => 'text-muted']
);

if ($result === null) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', get_string('flashcards_nodecks', 'local_aiskillnavigator'));
    echo html_writer::tag('p', get_string('flashcards_nodeckshelp', 'local_aiskillnavigator'), ['class' => 'text-muted']);
    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    if (count($decknames) > 1) {
        echo html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl->out_omit_querystring(), 'class' => 'mb-4']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        echo html_writer::tag(
            'label',
            get_string('flashcards_decklabel', 'local_aiskillnavigator'),
            ['for' => 'aisn-flashcards-deck', 'class' => 'mr-2']
        );
        echo html_writer::select(
            array_combine($decknames, $decknames),
            'deck',
            $selected,
            false,
            ['id' => 'aisn-flashcards-deck', 'class' => 'custom-select mr-2']
        );
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-secondary',
            'value' => get_string('flashcards_deckopen', 'local_aiskillnavigator'),
        ]);
        echo html_writer::end_tag('form');
    }

    if ($result['errorcode'] !== null) {
        echo $OUTPUT->notification(
            get_string('flashcards_deckproblem', 'local_aiskillnavigator', (object)[
                'deck' => s($selected),
                'problem' => local_aiskillnavigator_flashcards_problem($result),
            ]),
            \core\output\notification::NOTIFY_ERROR
        );
    } else {
        $deck = $result['deck'];
        $total = count($deck['cards']);

        echo html_writer::start_tag('section', [
            'class' => 'aisn-flashcards',
            'data-aisn-flashcards' => '',
            'aria-label' => $deck['title'],
        ]);

        echo html_writer::tag('h3', s($deck['title']));

        if ($deck['description'] !== '') {
            echo html_writer::tag('p', s($deck['description']), ['class' => 'text-muted']);
        }

        echo html_writer::tag(
            'p',
            get_string('flashcards_progress', 'local_aiskillnavigator', (object)['position' => 1, 'total' => $total]),
            [
                'class' => 'aisn-flashcards-progress font-weight-bold',
                'data-flashcards-progress' => '',
                'data-progress-template' => get_string(
                    'flashcards_progress',
                    'local_aiskillnavigator',
                    (object)['position' => '%position%', 'total' => '%total%']
                ),
                'role' => 'status',
                'aria-live' => 'polite',
            ]
        );

        echo html_writer::start_tag('ol', ['class' => 'aisn-flashcards-list list-unstyled', 'data-flashcards-list' => '']);

        foreach ($deck['cards'] as $index => $card) {
            $answerid = 'aisn-flashcard-answer-' . $index;

            echo html_writer::start_tag('li', ['class' => 'aisn-flashcard mb-3', 'data-flashcard' => '']);
            echo html_writer::start_div('aisn-flashcard-scene');
            echo html_writer::start_div('aisn-flashcard-pane', ['data-flashcard-pane' => '']);

            echo html_writer::start_div('aisn-flashcard-face aisn-flashcard-front', ['data-flashcard-front' => '']);
            echo html_writer::start_div('aisn-flashcard-body');
            echo html_writer::tag(
                'h4',
                get_string('flashcards_question', 'local_aiskillnavigator'),
                ['class' => 'aisn-flashcard-label']
            );
            echo html_writer::tag('p', s($card['question']), ['class' => 'aisn-flashcard-question']);
            echo html_writer::end_div();
            echo html_writer::end_div();

            echo html_writer::start_div('aisn-flashcard-face aisn-flashcard-back', [
                'id' => $answerid,
                'data-flashcard-answer' => '',
            ]);
            echo html_writer::start_div('aisn-flashcard-body');
            echo html_writer::tag(
                'h4',
                get_string('flashcards_answer', 'local_aiskillnavigator'),
                ['class' => 'aisn-flashcard-label']
            );
            echo html_writer::tag('p', s($card['answer']), ['class' => 'aisn-flashcard-answer-text']);
            echo html_writer::end_div();
            echo html_writer::end_div();

            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_tag('li');
        }

        echo html_writer::end_tag('ol');

        echo html_writer::start_div('aisn-flashcards-controls', ['data-flashcards-controls' => '', 'hidden' => 'hidden']);

        echo html_writer::start_div('aisn-flashcards-row');
        echo html_writer::tag(
            'button',
            $OUTPUT->pix_icon('t/left', '') . html_writer::span(get_string('flashcards_previous', 'local_aiskillnavigator')),
            [
                'type' => 'button',
                'class' => 'btn btn-outline-secondary aisn-flashcards-step',
                'data-flashcards-previous' => '',
            ]
        );
        echo html_writer::tag(
            'button',
            $OUTPUT->pix_icon('t/reload', '') . html_writer::span(
                get_string('flashcards_showanswer', 'local_aiskillnavigator'),
                '',
                ['data-flashcards-flip-label' => '']
            ),
            [
                'type' => 'button',
                'class' => 'btn btn-primary aisn-flashcards-flip',
                'data-flashcards-flip' => '',
                'data-label-show' => get_string('flashcards_showanswer', 'local_aiskillnavigator'),
                'data-label-hide' => get_string('flashcards_hideanswer', 'local_aiskillnavigator'),
                'aria-expanded' => 'false',
                'aria-controls' => 'aisn-flashcard-answer-0',
            ]
        );
        echo html_writer::tag(
            'button',
            html_writer::span(get_string('flashcards_next', 'local_aiskillnavigator')) . $OUTPUT->pix_icon('t/right', ''),
            [
                'type' => 'button',
                'class' => 'btn btn-outline-secondary aisn-flashcards-step',
                'data-flashcards-next' => '',
            ]
        );
        echo html_writer::end_div();

        echo html_writer::start_div('aisn-flashcards-row aisn-flashcards-row-secondary');
        echo html_writer::tag(
            'button',
            $OUTPUT->pix_icon('a/refresh', '') . html_writer::span(get_string('flashcards_shuffle', 'local_aiskillnavigator')),
            [
                'type' => 'button',
                'class' => 'btn btn-link aisn-flashcards-shuffle',
                'data-flashcards-shuffle' => '',
            ]
        );
        echo html_writer::end_div();

        echo html_writer::end_div();

        echo html_writer::tag('p', get_string('flashcards_keyboardhint', 'local_aiskillnavigator'), ['class' => 'text-muted']);

        echo html_writer::end_tag('section');
    }
}

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('flashcards_backtocourse', 'local_aiskillnavigator'),
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_div();

echo local_aisn_back_to_course_autofix((int)$courseid);
echo $OUTPUT->footer();
