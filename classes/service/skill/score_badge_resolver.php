<?php

namespace local_aiskillnavigator\service\skill;

defined('MOODLE_INTERNAL') || die();

// Maps a numeric score to a Bootstrap badge class.
class score_badge_resolver {
    public function get(int $score): string {
        if ($score >= 75) {
            return 'badge bg-success';
        }

        if ($score >= 50) {
            return 'badge bg-warning text-dark';
        }

        return 'badge bg-danger';
    }
}
