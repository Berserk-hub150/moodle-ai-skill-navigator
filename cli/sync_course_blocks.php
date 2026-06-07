<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/aiskillnavigator/classes/observer.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'courseid' => 0,
    ],
    [
        'h' => 'help',
        'c' => 'courseid',
    ]
);

if (!empty($options['help'])) {
    echo "Adds the AI Skill Navigator block to existing courses.\n";
    echo "Usage:\n";
    echo "  php local/aiskillnavigator/cli/sync_course_blocks.php\n";
    echo "  php local/aiskillnavigator/cli/sync_course_blocks.php --courseid=2\n";
    exit(0);
}

global $DB;

$courseid = (int)($options['courseid'] ?? 0);

if ($courseid > SITEID) {
    if (!$DB->record_exists('course', ['id' => $courseid])) {
        echo "Course {$courseid} not found.\n";
        exit(1);
    }

    \local_aiskillnavigator\observer::ensure_course_block($courseid);
    echo "AI Skill Navigator block checked for course {$courseid}.\n";
    exit(0);
}

$courseids = $DB->get_fieldset_select('course', 'id', 'id <> ?', [SITEID]);
$count = 0;

foreach ($courseids as $id) {
    \local_aiskillnavigator\observer::ensure_course_block((int)$id);
    $count++;
}

echo "AI Skill Navigator block checked for {$count} courses.\n";
