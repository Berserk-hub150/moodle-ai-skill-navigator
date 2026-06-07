<?php
defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aiskillnavigator_tool_registry')) {
    function local_aiskillnavigator_tool_registry(): array {
        return [
            [
                'section' => 'student',
                'label' => 'AI Assessments',
                'title' => 'AI Assessments',
                'description' => 'Run AI assessments.',
                'button' => 'Open AI Assessments',
                'path' => '/local/aiskillnavigator/pages/assessment.php',
                'cardclass' => 'btn btn-success',
                'blockclass' => 'btn btn-outline-success btn-block mb-2',
            ],
            [
                'section' => 'student',
                'label' => 'AI Tutor',
                'title' => 'AI Tutor',
                'description' => 'Ask course-aware questions.',
                'button' => 'Open AI Tutor',
                'path' => '/local/aiskillnavigator/pages/tutor.php',
                'cardclass' => 'btn btn-primary',
                'blockclass' => 'btn btn-outline-primary btn-block mb-2',
            ],
            [
                'section' => 'student',
                'label' => 'Adaptive review',
                'title' => 'Adaptive review',
                'description' => 'Review weak areas.',
                'button' => 'Open adaptive review',
                'path' => '/local/aiskillnavigator/pages/adaptive_review.php',
                'cardclass' => 'btn btn-warning',
                'blockclass' => 'btn btn-outline-warning btn-block mb-2',
            ],
            [
                'section' => 'student',
                'label' => 'AI Quiz',
                'title' => 'AI Quiz',
                'description' => 'Generate a micro quiz.',
                'button' => 'Open AI Quiz',
                'path' => '/local/aiskillnavigator/pages/quizgenerator.php',
                'cardclass' => 'btn btn-primary',
                'blockclass' => 'btn btn-outline-primary btn-block mb-2',
            ],
            [
                'section' => 'student',
                'label' => 'AI Mind Map',
                'title' => 'AI Mind Map',
                'description' => 'Generate a mind map.',
                'button' => 'Open AI Mind Map',
                'path' => '/local/aiskillnavigator/pages/mindmapgenerator.php',
                'cardclass' => 'btn btn-primary',
                'blockclass' => 'btn btn-outline-primary btn-block mb-2',
            ],
            [
                'section' => 'teacher',
                'label' => 'Teacher dashboard',
                'title' => 'Teacher dashboard',
                'description' => 'Open the teacher dashboard.',
                'button' => 'Open teacher dashboard',
                'path' => '/local/aiskillnavigator/pages/teacher.php',
                'cardclass' => 'btn btn-secondary',
                'blockclass' => 'btn btn-outline-secondary btn-block mb-2',
            ],
            [
                'section' => 'teacher',
                'label' => 'Tutor analyst',
                'title' => 'Tutor analyst',
                'description' => 'Tutor-as-sensor analytics.',
                'button' => 'Open Tutor analyst',
                'path' => '/local/aiskillnavigator/pages/tutor_analytics.php',
                'cardclass' => 'btn btn-info',
                'blockclass' => 'btn btn-outline-info btn-block mb-2',
            ],
            [
                'section' => 'teacher',
                'label' => 'Initial/final tests',
                'title' => 'Initial/final tests',
                'description' => 'Create and edit initial/final tests.',
                'button' => 'Open initial/final tests',
                'path' => '/local/aiskillnavigator/pages/teacher_assessments.php',
                'cardclass' => 'btn btn-success',
                'blockclass' => 'btn btn-outline-success btn-block mb-2',
            ],
            [
                'section' => 'teacher',
                'label' => 'Learning-gap analysis',
                'title' => 'Learning-gap analysis',
                'description' => 'Analyse learning gaps.',
                'button' => 'Open learning-gap analysis',
                'path' => '/local/aiskillnavigator/pages/gap_analysis.php',
                'cardclass' => 'btn btn-warning',
                'blockclass' => 'btn btn-outline-warning btn-block mb-2',
            ],
            [
                'section' => 'teacher',
                'label' => 'AI Course Builder',
                'title' => 'AI Course Builder',
                'description' => 'Build course resources with AI.',
                'button' => 'Open AI Course Builder',
                'path' => '/local/aiskillnavigator/pages/course_builder.php',
                'cardclass' => 'btn btn-info',
                'blockclass' => 'btn btn-outline-info btn-block mb-2',
            ],
            [
                'section' => 'teacher',
                'label' => 'AI Simulator Finder',
                'title' => 'AI Simulator Finder',
                'description' => 'Find simulator tools.',
                'button' => 'Open AI Simulator Finder',
                'path' => '/local/aiskillnavigator/pages/simulator_finder.php',
                'cardclass' => 'btn btn-info',
                'blockclass' => 'btn btn-outline-info btn-block mb-2',
            ],
            [
                'section' => 'knowledge',
                'label' => 'Course materials / RAG',
                'title' => 'Course materials / RAG',
                'description' => 'Manage synchronized course materials, RAG chunks and AI/OCR policy.',
                'button' => 'Open course materials / RAG',
                'path' => '/local/aiskillnavigator/pages/teacher_materials.php',
                'cardclass' => 'btn btn-success',
                'blockclass' => 'btn btn-outline-success btn-block mb-2',
            ],
        ];
    }
}

if (!function_exists('local_aiskillnavigator_get_tools')) {
    function local_aiskillnavigator_get_tools(?string $section = null): array {
        $tools = local_aiskillnavigator_tool_registry();

        if ($section === null || $section === '') {
            return $tools;
        }

        return array_values(array_filter($tools, static function(array $tool) use ($section): bool {
            return ($tool['section'] ?? '') === $section;
        }));
    }
}

if (!function_exists('local_aiskillnavigator_get_tool_sections')) {
    function local_aiskillnavigator_get_tool_sections(): array {
        return [
            'student' => 'STUDENT TOOLS',
            'teacher' => 'TEACHER TOOLS',
            'knowledge' => 'KNOWLEDGE BASE',
        ];
    }
}

if (!function_exists('local_aiskillnavigator_tool_url')) {
    function local_aiskillnavigator_tool_url(array $tool, int $courseid): moodle_url {
        return new moodle_url((string)($tool['path'] ?? '/local/aiskillnavigator/pages/index.php'), ['courseid' => $courseid]);
    }
}

if (!function_exists('local_aiskillnavigator_tools')) {
    function local_aiskillnavigator_tools(?string $section = null): array {
        return local_aiskillnavigator_get_tools($section);
    }
}

if (!function_exists('local_aisn_tool_registry')) {
    function local_aisn_tool_registry(): array {
        return local_aiskillnavigator_tool_registry();
    }
}

if (!function_exists('local_aisn_get_tools')) {
    function local_aisn_get_tools(?string $section = null): array {
        return local_aiskillnavigator_get_tools($section);
    }
}