<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/document_ocr_toggle_helper.php');

function local_aiskillnavigator_render_tool_url(string $path, int $courseid): moodle_url {
    $params = [];

    if ($courseid > SITEID) {
        $params['courseid'] = $courseid;
    }

    return new moodle_url($path, $params);
}

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

function local_aiskillnavigator_render_block_tool_button(array $tool, int $courseid): string {
    return html_writer::link(
        local_aiskillnavigator_render_tool_url((string)$tool['path'], $courseid),
        s((string)$tool['label']),
        ['class' => (string)$tool['blockclass']]
    );
}

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



