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

require_once(__DIR__ . '/document_ocr_toggle_helper.php');

/**
 * Local aiskillnavigator render tool url helper.
 */
function local_aiskillnavigator_render_tool_url(string $path, int $courseid): moodle_url {
    $params = [];

    if ($courseid > SITEID) {
        $params['courseid'] = $courseid;
    }

    return new moodle_url($path, $params);
}

/**
 * Local aiskillnavigator render tool card helper.
 */
function local_aiskillnavigator_render_tool_card(array $tool, int $courseid): string {
    $html = html_writer::start_div('card mb-3');
    $html .= html_writer::start_div('card-body');

    $html .= html_writer::tag('h3', s((string)$tool['title']));
    $html .= html_writer::tag('p', s((string)$tool['description']), ['class' => 'text-muted']);

    $html .= html_writer::link(
        local_aiskillnavigator_render_tool_url((string)$tool['path'], $courseid),
        s((string)$tool['button']),
        ['class' => (string)$tool['cardclass']]
    );

    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}

/**
 * Local aiskillnavigator render block tool button helper.
 */
function local_aiskillnavigator_render_block_tool_button(array $tool, int $courseid): string {
    return html_writer::link(
        local_aiskillnavigator_render_tool_url((string)$tool['path'], $courseid),
        s((string)$tool['label']),
        ['class' => (string)$tool['blockclass']]
    );
}

/**
 * Local aiskillnavigator render block section helper.
 */
function local_aiskillnavigator_render_block_section(string $title, array $tools, int $courseid): string {
    if (empty($tools)) {
        return '';
    }

    $html = html_writer::tag(
        'div',
        $title,
        ['class' => 'small font-weight-bold text-uppercase mb-2']
    );

    foreach ($tools as $tool) {
        $html .= local_aiskillnavigator_render_block_tool_button($tool, $courseid);
    }

    return $html;
}
