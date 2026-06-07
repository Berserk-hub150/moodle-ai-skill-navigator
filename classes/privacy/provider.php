<?php

namespace local_aiskillnavigator\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

class provider implements metadata_provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_aiskillnav_material',
            [
                'courseid' => 'Course identifier.',
                'userid' => 'Teacher identifier.',
                'title' => 'Material title.',
                'materialtype' => 'Material type.',
                'content' => 'Extracted or pasted course material content.',
                'timecreated' => 'Creation time.',
                'timemodified' => 'Last modification time.',
            ],
            'Teacher course materials used for RAG and AI-supported learning.'
        );

        $collection->add_database_table(
            'local_aiskillnav_attempt',
            [
                'courseid' => 'Course identifier.',
                'userid' => 'Student identifier.',
                'topic' => 'Quiz topic.',
                'difficulty' => 'Quiz difficulty.',
                'score' => 'Score.',
                'maxscore' => 'Maximum score.',
                'percentage' => 'Percentage score.',
                'quizjson' => 'Generated quiz JSON.',
                'answersjson' => 'Student answers JSON.',
                'timecreated' => 'Attempt time.',
            ],
            'Student AI quiz attempts.'
        );

        $collection->add_database_table(
            'local_aiskillnav_assessment',
            [
                'courseid' => 'Course identifier.',
                'userid' => 'Teacher identifier.',
                'title' => 'Assessment title.',
                'assessmenttype' => 'Initial diagnostic test or final test.',
                'focus' => 'Assessment focus.',
                'difficulty' => 'Difficulty.',
                'quizjson' => 'Generated assessment JSON.',
                'visible' => 'Visibility to students.',
                'timecreated' => 'Creation time.',
                'timemodified' => 'Last modification time.',
            ],
            'Teacher-generated pre-tests and final tests.'
        );

        $collection->add_database_table(
            'local_aiskillnav_ass_att',
            [
                'assessmentid' => 'Assessment identifier.',
                'courseid' => 'Course identifier.',
                'userid' => 'Student identifier.',
                'score' => 'Score.',
                'maxscore' => 'Maximum score.',
                'percentage' => 'Percentage score.',
                'answersjson' => 'Student answers JSON.',
                'timecreated' => 'Submission time.',
            ],
            'Student attempts on teacher-generated diagnostic assessments.'
        );

        $collection->add_external_location_link(
            'configured_ai_provider',
            [
                'prompt' => 'Prompts may include student questions, teacher materials, assessment summaries or learning-gap summaries depending on the selected feature.',
                'api_key' => 'The API key is configured by the administrator and is not stored in code.',
            ],
            'Optional external AI provider configured by the site administrator.'
        );

        return $collection;
    }
}