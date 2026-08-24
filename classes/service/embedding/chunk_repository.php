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

namespace local_aiskillnavigator\service\embedding;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Reads and writes RAG chunks.
/**
 * Chunk repository implementation.
 */
class chunk_repository {
    /**
     * Delete material helper.
     */
    public function delete_material(int $materialid): void {
        global $DB;
        $DB->delete_records('local_aiskillnav_chunk', ['materialid' => $materialid]);
    }

    /**
     * Count helper.
     */
    public function count(int $courseid, int $materialid = 0): int {
        global $DB;
        $conditions = ['courseid' => $courseid];

        if ($materialid > 0) {
            $conditions['materialid'] = $materialid;
        }

        return $DB->count_records('local_aiskillnav_chunk', $conditions);
    }

    /**
     * Load helper.
     */
    public function load(int $courseid, int $materialid = 0): array {
        global $DB;
        $conditions = ['courseid' => $courseid];

        if ($materialid > 0) {
            $conditions['materialid'] = $materialid;
        }

        return $DB->get_records('local_aiskillnav_chunk', $conditions);
    }

    /**
     * Insert helper.
     */
    public function insert(\stdClass $record): void {
        global $DB;
        $DB->insert_record('local_aiskillnav_chunk', $record);
    }
}
