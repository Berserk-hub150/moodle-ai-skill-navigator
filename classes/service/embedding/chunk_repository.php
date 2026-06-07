<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Reads and writes RAG chunks.
class chunk_repository {
    public function delete_material(int $materialid): void {
        global $DB;
        $DB->delete_records('local_aiskillnav_chunk', ['materialid' => $materialid]);
    }

    public function count(int $courseid, int $materialid = 0): int {
        global $DB;
        $conditions = ['courseid' => $courseid];

        if ($materialid > 0) {
            $conditions['materialid'] = $materialid;
        }

        return $DB->count_records('local_aiskillnav_chunk', $conditions);
    }

    public function load(int $courseid, int $materialid = 0): array {
        global $DB;
        $conditions = ['courseid' => $courseid];

        if ($materialid > 0) {
            $conditions['materialid'] = $materialid;
        }

        return $DB->get_records('local_aiskillnav_chunk', $conditions);
    }

    public function insert(\stdClass $record): void {
        global $DB;
        $DB->insert_record('local_aiskillnav_chunk', $record);
    }
}
