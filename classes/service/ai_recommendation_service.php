<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class ai_recommendation_service {

    public function generate_student_recommendation(array $profile): string {
        $gap = $profile['main_gap'] ?? 'the weakest skill';

        return 'The main detected gap is "' . $gap . '". '
            . 'The student should review the related material, complete a short adaptive quiz, '
            . 'and then work on a practical scenario connected to AI, IoT and Digital Twin concepts.';
    }

    public function generate_teacher_recommendation(array $overview): string {
        if (empty($overview['weakestskills'])) {
            return 'No relevant skill gap detected yet.';
        }

        $weakest = $overview['weakestskills'][0];

        return 'The weakest course-level skill is "' . $weakest['name'] . '" with an average score of '
            . $weakest['average'] . '%. A recovery activity and a targeted micro-quiz are recommended.';
    }
}