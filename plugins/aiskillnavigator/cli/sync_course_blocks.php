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

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/aiskillnavigator/classes/observer.php');

[$options, $unrecognized] = cli_get_params(
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
