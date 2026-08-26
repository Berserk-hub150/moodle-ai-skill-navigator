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
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/flashcards.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Flashcards');
$PAGE->set_heading('AI Flashcards');

// Load decks.
$decksdir = __DIR__ . '/../../../community/flashcards/decks';
$decks = [];
if (is_dir($decksdir)) {
    foreach (glob($decksdir . '/*.json') as $filepath) {
        $filename = basename($filepath);
        $json = file_get_contents($filepath);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (isset($data['title']) && isset($data['cards'])) {
                $decks[$filename] = [
                    'title' => $data['title'],
                    'description' => $data['description'] ?? '',
                    'cards' => $data['cards']
                ];
            }
        }
    }
}

// Fallback if no decks are found.
if (empty($decks)) {
    $decks['starter.json'] = [
        'title' => 'AI Basics Starter',
        'description' => 'A starter deck for testing AI terms.',
        'cards' => [
            [
                'question' => 'What is a Large Language Model (LLM)?',
                'answer' => 'A machine learning model trained on vast text data to process and generate natural language.'
            ],
            [
                'question' => 'What is a Prompt?',
                'answer' => 'The instruction or input provided to an AI model to guide its response.'
            ]
        ]
    ];
}

// Pass data to JS.
$PAGE->requires->js(new moodle_url('/local/aiskillnavigator/js/flashcards.js'));
$PAGE->requires->js_init_call('local_aiskillnavigator_init_flashcards', [array_values($decks)]);

echo $OUTPUT->header();

if (function_exists('local_aiskillnavigator_print_inline_styles')) {
    local_aiskillnavigator_print_inline_styles();
}

// Print inline CSS for stunning 3D flip card aesthetics.
echo html_writer::tag('style', '
.aisn-flashcards-layout {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px 0;
}
.aisn-flashcards-header {
    background: linear-gradient(135deg, #4f46e5, #06b6d4);
    color: #fff;
    border-radius: 24px;
    padding: 30px;
    margin-bottom: 24px;
    box-shadow: 0 10px 30px rgba(79, 70, 229, 0.15);
}
.aisn-flashcards-header h2 { margin: 0 0 10px; font-weight: 800; }
.aisn-flashcards-deck-select {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
}
/* 3D Flashcard Container */
.aisn-card-scene {
    width: 100%;
    height: 350px;
    perspective: 1000px;
    margin: 30px 0;
}
.aisn-card-flip-container {
    width: 100%;
    height: 100%;
    position: relative;
    transform-style: preserve-3d;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}
.aisn-card-flip-container.is-flipped {
    transform: rotateY(180deg);
}
.aisn-card-face {
    position: absolute;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    border-radius: 24px;
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.8);
}
.aisn-card-front {
    background: linear-gradient(135deg, #ffffff, #f9fafb);
    color: #1f2937;
}
.aisn-card-back {
    background: linear-gradient(135deg, #f0fdfa, #ccfbf1);
    color: #115e59;
    transform: rotateY(180deg);
}
.aisn-card-face-label {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 20px;
    color: #9ca3af;
}
.aisn-card-back .aisn-card-face-label {
    color: #0d9488;
}
.aisn-card-text {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.5;
}
/* Controls */
.aisn-flashcards-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-bottom: 24px;
}
.aisn-flashcards-progress {
    text-align: center;
    font-weight: 700;
    color: #4b5563;
    margin-bottom: 20px;
}
/* Screen Reader Only Utility */
.aisn-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
/* Keyboard Focus Styling */
.aisn-card-flip-container:focus-visible {
    outline: 3px solid #4f46e5;
    outline-offset: 6px;
    border-radius: 24px;
}
');

echo html_writer::start_div('container-fluid');
echo html_writer::start_div('aisn-flashcards-layout');

// Header.
echo html_writer::start_div('aisn-flashcards-header');
echo html_writer::tag('h2', 'AI Flashcards');
echo html_writer::tag('p', 'Rafforza il tuo apprendimento con le flashcards generate e curate dall\'AI.');
echo html_writer::end_div();

// Deck Selector.
echo html_writer::start_div('aisn-flashcards-deck-select');
echo html_writer::tag('label', 'Seleziona un mazzo di Flashcards:', ['for' => 'deck-selector', 'class' => 'form-label fw-bold']);
echo html_writer::start_tag('select', ['id' => 'deck-selector', 'class' => 'form-select form-control mb-2']);
foreach ($decks as $filename => $deck) {
    echo html_writer::tag('option', s($deck['title']), ['value' => s($deck['title'])]);
}
echo html_writer::end_tag('select');
echo html_writer::tag('p', '', ['id' => 'deck-description', 'class' => 'text-muted mb-0']);
echo html_writer::end_div();

// Accessibility ARIA Live Region for Screen Readers.
// It is visually hidden from mouse users but active for screen readers.
echo html_writer::tag('div', '', [
    'id' => 'aisn-aria-live-region',
    'class' => 'aisn-sr-only',
    'aria-live' => 'polite',
    'aria-atomic' => 'true'
]);

// Main study arena.
echo html_writer::start_div('aisn-card-scene');
echo html_writer::start_div('aisn-card-flip-container', [
    'id' => 'flashcard',
    'tabindex' => '0',
    'role' => 'button',
    'aria-label' => 'Flashcard. Premi Invio o Spazio per girare.',
    'aria-describedby' => 'card-progress-info'
]);

echo html_writer::start_div('aisn-card-face aisn-card-front');
echo html_writer::tag('span', 'Domanda', ['class' => 'aisn-card-face-label']);
echo html_writer::tag('div', '', ['id' => 'card-question', 'class' => 'aisn-card-text']);
echo html_writer::end_div();

echo html_writer::start_div('aisn-card-face aisn-card-back');
echo html_writer::tag('span', 'Risposta', ['class' => 'aisn-card-face-label']);
echo html_writer::tag('div', '', ['id' => 'card-answer', 'class' => 'aisn-card-text']);
echo html_writer::end_div();

echo html_writer::end_div(); // container
echo html_writer::end_div(); // scene

// Progress bar.
echo html_writer::tag('div', '', ['id' => 'card-progress-info', 'class' => 'aisn-flashcards-progress']);

// Controls.
echo html_writer::start_div('aisn-flashcards-controls');
echo html_writer::tag('button', 'Precedente', ['id' => 'btn-prev', 'class' => 'btn btn-outline-secondary']);
echo html_writer::tag('button', 'Gira Carta', ['id' => 'btn-flip', 'class' => 'btn btn-primary']);
echo html_writer::tag('button', 'Successiva', ['id' => 'btn-next', 'class' => 'btn btn-outline-secondary']);
echo html_writer::tag('button', 'Mescola', ['id' => 'btn-shuffle', 'class' => 'btn btn-outline-info']);
echo html_writer::end_div();

echo html_writer::start_div('text-center mt-4');
echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Torna al corso', ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo html_writer::end_div(); // layout
echo html_writer::end_div(); // container-fluid

echo $OUTPUT->footer();
