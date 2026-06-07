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
    echo "Creates or repairs AI policy fields on local_aiskillnav_material.\n";
    echo "Usage: php local/aiskillnavigator/cli/install_material_ai_policy.php\n";
    exit(0);
}

global $DB;

$dbman = $DB->get_manager();
$table = new xmldb_table('local_aiskillnav_material');

if (!$dbman->table_exists($table)) {
    echo "Table local_aiskillnav_material not found. Install the plugin first.\n";
    exit(1);
}

$externalaiallowed = new xmldb_field('externalaiallowed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');

if (!$dbman->field_exists($table, $externalaiallowed)) {
    $dbman->add_field($table, $externalaiallowed);
    echo "Added field externalaiallowed\n";
} else {
    echo "Field externalaiallowed already exists\n";
}

$aipolicy = new xmldb_field('aipolicy', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'local_only', 'externalaiallowed');

if (!$dbman->field_exists($table, $aipolicy)) {
    $dbman->add_field($table, $aipolicy);
    echo "Added field aipolicy\n";
} else {
    echo "Field aipolicy already exists\n";
}

$DB->execute("UPDATE {local_aiskillnav_material}
                 SET externalaiallowed = 0
               WHERE externalaiallowed IS NULL");

$DB->execute("UPDATE {local_aiskillnav_material}
                 SET aipolicy = CASE
                       WHEN externalaiallowed = 1 THEN 'external_allowed'
                       ELSE 'local_only'
                     END
               WHERE aipolicy IS NULL OR aipolicy = ''");

echo "AI material policy schema ready.\n";
