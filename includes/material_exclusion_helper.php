<?php

defined('MOODLE_INTERNAL') || die();

function local_aisn_course_cm_id_from_material_title(string $title): int {
    if (preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', $title, $m)) {
        return (int)$m[1];
    }

    return 0;
}

function local_aisn_course_material_is_excluded(int $courseid, int $cmid): bool {
    if ($courseid <= 1 || $cmid <= 0) {
        return false;
    }

    return (string)get_config('local_aiskillnavigator', 'cm_ai_excluded_' . $cmid) === '1';
}

function local_aisn_course_material_set_excluded(int $courseid, int $cmid, bool $excluded): void {
    if ($courseid <= 1 || $cmid <= 0) {
        return;
    }

    set_config(
        'cm_ai_excluded_' . $cmid,
        $excluded ? '1' : '0',
        'local_aiskillnavigator'
    );
}
