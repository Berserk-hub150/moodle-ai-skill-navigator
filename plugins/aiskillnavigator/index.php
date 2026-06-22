<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT);

$params = [];
if ($courseid > SITEID) {
    $params['courseid'] = $courseid;
}

redirect(new moodle_url('/local/aiskillnavigator/pages/index.php', $params));