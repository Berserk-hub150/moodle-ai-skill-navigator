<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($options['help'])) {
    echo "Creates or repairs the AI Skill Navigator assessment tables.\n";
    echo "Usage: php local/aiskillnavigator/cli/install_assessment_tables.php\n";
    exit(0);
}

global $DB;

$dbman = $DB->get_manager();

function local_aisn_cli_add_field_if_missing($dbman, xmldb_table $table, xmldb_field $field): void {
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
        echo "Added field {$field->getName()} to {$table->getName()}\n";
    }
}

function local_aisn_cli_add_index_if_missing($dbman, xmldb_table $table, xmldb_index $index): void {
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
        echo "Added index {$index->getName()} to {$table->getName()}\n";
    }
}

$assessment = new xmldb_table('local_aiskillnav_assessment');

if (!$dbman->table_exists($assessment)) {
    $assessment->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $assessment->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $assessment->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $assessment->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $assessment->add_field('assessmenttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pre');
    $assessment->add_field('focus', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
    $assessment->add_field('difficulty', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'medium');
    $assessment->add_field('quizjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
    $assessment->add_field('sourcemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'all');
    $assessment->add_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $assessment->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
    $assessment->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $assessment->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $assessment->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $assessment->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
    $assessment->add_index('type_ix', XMLDB_INDEX_NOTUNIQUE, ['assessmenttype']);
    $assessment->add_index('visible_ix', XMLDB_INDEX_NOTUNIQUE, ['visible']);

    $dbman->create_table($assessment);
    echo "Created table local_aiskillnav_assessment\n";
} else {
    echo "Table local_aiskillnav_assessment already exists. Checking missing fields/indexes...\n";

    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'Untitled assessment', 'userid'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('assessmenttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pre', 'title'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('focus', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'assessmenttype'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('difficulty', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'medium', 'focus'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('quizjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '', 'difficulty'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('sourcemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'all', 'quizjson'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sourcemode'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'materialids'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'visible'));
    local_aisn_cli_add_field_if_missing($dbman, $assessment, new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'));

    local_aisn_cli_add_index_if_missing($dbman, $assessment, new xmldb_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']));
    local_aisn_cli_add_index_if_missing($dbman, $assessment, new xmldb_index('type_ix', XMLDB_INDEX_NOTUNIQUE, ['assessmenttype']));
    local_aisn_cli_add_index_if_missing($dbman, $assessment, new xmldb_index('visible_ix', XMLDB_INDEX_NOTUNIQUE, ['visible']));
}

$attempt = new xmldb_table('local_aiskillnav_ass_att');

if (!$dbman->table_exists($attempt)) {
    $attempt->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $attempt->add_field('assessmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $attempt->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $attempt->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $attempt->add_field('score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $attempt->add_field('maxscore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $attempt->add_field('percentage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $attempt->add_field('answersjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $attempt->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $attempt->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $attempt->add_index('assessmentid_ix', XMLDB_INDEX_NOTUNIQUE, ['assessmentid']);
    $attempt->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
    $attempt->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

    $dbman->create_table($attempt);
    echo "Created table local_aiskillnav_ass_att\n";
} else {
    echo "Table local_aiskillnav_ass_att already exists. Checking missing fields/indexes...\n";

    local_aisn_cli_add_field_if_missing($dbman, $attempt, new xmldb_field('assessmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id'));
    local_aisn_cli_add_field_if_missing($dbman, $attempt, new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'assessmentid'));
    local_aisn_cli_add_field_if_missing($dbman, $attempt, new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid'));
    local_aisn_cli_add_field_if_missing($dbman, $attempt, new xmldb_field('score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid'));
    local_aisn_cli_add_field_if_missing($dbman, $attempt, new xmldb_field('maxscore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'score'));
    local_aisn_cli_add_field_if_missing($dbman, $attempt, new xmldb_field('percentage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'maxscore'));
    local_aisn_cli_add_field_if_missing($dbman, $attempt, new xmldb_field('answersjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'percentage'));
    local_aisn_cli_add_field_if_missing($dbman, $attempt, new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'answersjson'));

    local_aisn_cli_add_index_if_missing($dbman, $attempt, new xmldb_index('assessmentid_ix', XMLDB_INDEX_NOTUNIQUE, ['assessmentid']));
    local_aisn_cli_add_index_if_missing($dbman, $attempt, new xmldb_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']));
    local_aisn_cli_add_index_if_missing($dbman, $attempt, new xmldb_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']));
}

echo "Assessment tables ready.\n";
