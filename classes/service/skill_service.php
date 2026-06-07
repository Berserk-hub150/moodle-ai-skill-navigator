<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/skill/*.php') as $file) {
    require_once($file);
}

// Provides demo skill data for student and teacher pages.
class skill_service {
    public function get_student_skill_profile(int $userid): array {
        return (new skill\student_profile_data())->get($userid);
    }

    public function get_teacher_skill_overview(): array {
        return (new skill\teacher_overview_data())->get();
    }

    public function get_score_badge_class(int $score): string {
        return (new skill\score_badge_resolver())->get($score);
    }
}
