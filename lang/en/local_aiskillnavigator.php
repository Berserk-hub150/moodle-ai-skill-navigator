<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Skill Navigator';

$string['studentdashboard'] = 'Student dashboard';
$string['teacherdashboard'] = 'Teacher dashboard';
$string['skills'] = 'Skills';
$string['recommendations'] = 'Recommendations';
$string['main_gap'] = 'Main skill gap';
$string['ai_recommendation'] = 'AI recommendation prototype';

$string['aitutor'] = 'AI Tutor';
$string['quizgenerator'] = 'AI Quiz Generator';
$string['mindmapgenerator'] = 'AI Mind Map Generator';

$string['tutor_question'] = 'Ask a question';
$string['quiz_topic'] = 'Quiz topic';
$string['mindmap_topic'] = 'Mind map topic';

$string['settings'] = 'AI Skill Navigator settings';
$string['provider'] = 'AI provider';
$string['provider_desc'] = 'Select the AI provider used by the plugin.';
$string['apikey'] = 'AI API key';
$string['apikey_desc'] = 'API key for the external AI provider.';
$string['embeddingmodel'] = 'Embedding model';
$string['embeddingmodel_desc'] = 'Model used for generating RAG embeddings. For Ollama: nomic-embed-text. For OpenAI: text-embedding-3-small.';
$string['local/aiskillnavigator:viewstudent'] = 'Use student AI tools';
$string['local/aiskillnavigator:viewteacher'] = 'Use teacher AI tools';
$string['local/aiskillnavigator:managematerials'] = 'Manage teacher AI materials';
$string['privacy:metadata:configured_ai_provider'] = 'Optional external AI provider configured by the site administrator.';
$string['privacy:metadata:local_aiskillnav_material'] = 'Course materials stored for AI-assisted learning.';
$string['privacy:metadata:local_aiskillnav_attempt'] = 'Student AI quiz attempts.';
$string['privacy:metadata:local_aiskillnav_chunk'] = 'Search chunks generated from course materials.';
$string['privacy:metadata:local_aiskillnav_assessment'] = 'Teacher-generated initial and final assessments.';
$string['privacy:metadata:local_aiskillnav_ass_att'] = 'Student attempts on teacher-generated assessments.';
$string['privacy:metadata:local_aiskillnav_sim'] = 'Saved simulator suggestions and activities.';
$string['privacy:metadata:local_aiskillnav_tutor_sig'] = 'Tutor questions and interaction signals.';
$string['privacy:metadata:userid'] = 'The user identifier.';
$string['privacy:metadata:courseid'] = 'The course identifier.';
$string['privacy:metadata:content'] = 'User-provided or extracted content.';
$string['privacy:metadata:timecreated'] = 'The time the record was created.';
$string['privacy:metadata:timemodified'] = 'The time the record was last modified.';
