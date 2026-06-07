<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$syncfile = $CFG->dirroot . '/local/aiskillnavigator/includes/course_resource_sync.php';

if (!file_exists($syncfile)) {
    echo "Sync file not found: {$syncfile}\n";
    exit(1);
}

require_once($syncfile);

if (!function_exists('local_aiskillnavigator_sync_course_resources')) {
    echo "Function local_aiskillnavigator_sync_course_resources not found.\n";
    exit(1);
}

list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'courseid' => 0,
        'userid' => 2,
        'force' => false,
        'all' => false,
    ],
    [
        'h' => 'help',
        'c' => 'courseid',
        'u' => 'userid',
        'f' => 'force',
        'a' => 'all',
    ]
);

if (!empty($options['help'])) {
    echo "Synchronises Moodle course resources into AI Skill Navigator materials.\n";
    echo "Usage:\n";
    echo "  php local/aiskillnavigator/cli/sync_course_resources.php --courseid=2 [--userid=2] [--force]\n";
    echo "  php local/aiskillnavigator/cli/sync_course_resources.php --all [--userid=2] [--force]\n";
    exit(0);
}

global $DB;

$userid = max(0, (int)($options['userid'] ?? 2));
$force = !empty($options['force']);

function local_aisn_cli_print_sync_result(int $courseid, array $result): void {
    echo "Course resource sync completed for course {$courseid}\n";
    echo "Created: " . (int)($result['created'] ?? 0) . "\n";
    echo "Updated: " . (int)($result['updated'] ?? 0) . "\n";
    echo "Skipped: " . (int)($result['skipped'] ?? 0) . "\n";

    if (array_key_exists('deletedduplicates', $result)) {
        echo "Deleted duplicates: " . (int)$result['deletedduplicates'] . "\n";
    }
}

if (!empty($options['all'])) {
    $courseids = $DB->get_fieldset_select('course', 'id', 'id <> ?', [SITEID]);
    $total = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'deletedduplicates' => 0];

    foreach ($courseids as $id) {
        $courseid = (int)$id;
        $result = local_aiskillnavigator_sync_course_resources($courseid, $userid, $force);
        local_aisn_cli_print_sync_result($courseid, is_array($result) ? $result : []);

        foreach ($total as $key => $value) {
            $total[$key] += (int)($result[$key] ?? 0);
        }
    }

    echo "All courses completed.\n";
    echo "Total created: {$total['created']}\n";
    echo "Total updated: {$total['updated']}\n";
    echo "Total skipped: {$total['skipped']}\n";
    echo "Total deleted duplicates: {$total['deletedduplicates']}\n";
    exit(0);
}

$courseid = (int)($options['courseid'] ?? 0);

if ($courseid <= SITEID) {
    echo "Usage: php local/aiskillnavigator/cli/sync_course_resources.php --courseid=2 [--userid=2] [--force]\n";
    echo "Or:    php local/aiskillnavigator/cli/sync_course_resources.php --all [--userid=2] [--force]\n";
    exit(1);
}

if (!$DB->record_exists('course', ['id' => $courseid])) {
    echo "Course {$courseid} not found.\n";
    exit(1);
}

$result = local_aiskillnavigator_sync_course_resources($courseid, $userid, $force);
local_aisn_cli_print_sync_result($courseid, is_array($result) ? $result : []);
