<?php
// This file is part of Moodle - https://moodle.org/
//
// Backward-compatible entry point for old links.
// The real AI tutor page is tutor.php.

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');

$courseid = optional_param('courseid', SITEID, PARAM_INT);

redirect(new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]));


