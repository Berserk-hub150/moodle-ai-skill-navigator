<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Central role guards for AI Skill Navigator.
 *
 * Goal:
 * - students use only student tools;
 * - teachers use only teacher tools;
 * - site admins can use both sides for testing/demo;
 * - every check is course-context based.
 */

if (!function_exists('local_aisn_is_course_teacher_like')) {
    function local_aisn_is_course_teacher_like(context_course $context): bool {
        return has_capability('moodle/course:update', $context)
            || has_capability('moodle/course:manageactivities', $context)
            || has_capability('local/aiskillnavigator:viewteacher', $context)
            || has_capability('local/aiskillnavigator:managematerials', $context)
            || has_capability('local/aiskillnavigator:manageassessments', $context);
    }
}

if (!function_exists('local_aisn_require_student_area')) {
    function local_aisn_require_student_area(context_course $context): void {
        if (is_siteadmin()) {
            return;
        }

        require_capability('local/aiskillnavigator:viewstudent', $context);

        if (local_aisn_is_course_teacher_like($context)) {
            throw new required_capability_exception(
                $context,
                'local/aiskillnavigator:viewstudent',
                'nopermissions',
                ''
            );
        }
    }
}

if (!function_exists('local_aisn_require_teacher_area')) {
    function local_aisn_require_teacher_area(context_course $context): void {
        if (is_siteadmin()) {
            return;
        }

        require_capability('local/aiskillnavigator:viewteacher', $context);
    }
}
