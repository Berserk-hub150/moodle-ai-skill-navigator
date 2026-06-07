<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\course_created',
        'callback' => '\\local_aiskillnavigator\\observer::course_created',
    ],
    [
        'eventname' => '\\core\\event\\course_updated',
        'callback' => '\\local_aiskillnavigator\\observer::course_updated',
    ],
    [
        'eventname' => '\\core\\event\\course_module_created',
        'callback' => '\\local_aiskillnavigator\\observer::course_module_created',
    ],
    [
        'eventname' => '\\core\\event\\course_module_updated',
        'callback' => '\\local_aiskillnavigator\\observer::course_module_updated',
    ],
    [
        'eventname' => '\\core\\event\\course_module_deleted',
        'callback' => '\\local_aiskillnavigator\\observer::course_module_deleted',
    ],
];
