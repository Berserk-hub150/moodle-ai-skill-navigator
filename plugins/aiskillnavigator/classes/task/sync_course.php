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

namespace local_aiskillnavigator\task;

/**
 * Extract and index course materials outside page requests.
 */
class sync_course extends \core\task\adhoc_task {
    /**
     * Queue one pending job per course and requesting user.
     *
     * @param int $courseid Course to synchronise.
     * @param int $userid Requesting teacher.
     */
    public static function queue(int $courseid, int $userid): void {
        $task = new self();
        $task->set_component('local_aiskillnavigator');
        $task->set_custom_data(['courseid' => $courseid]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Execute after checking that the requesting teacher still has access.
     */
    public function execute(): void {
        global $DB;
        $courseid = (int)$this->get_custom_data()->courseid;
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return;
        }
        $context = \context_course::instance($courseid);
        if (!has_capability('local/aiskillnavigator:managematerials', $context, $this->get_userid())) {
            mtrace('Course material synchronisation skipped: permission has been revoked.');
            return;
        }
        require_once(__DIR__ . '/../../includes/course_resource_sync.php');
        $result = local_aiskillnavigator_sync_course_resources($courseid, (int)$this->get_userid(), true);
        mtrace('Course material synchronisation completed: ' . json_encode($result));
    }
}
