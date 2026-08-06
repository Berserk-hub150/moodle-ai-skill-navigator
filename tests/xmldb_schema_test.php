<?php

$path = __DIR__ . '/../plugins/aiskillnavigator/db/install.xml';
$xml = simplexml_load_file($path);

if ($xml === false) {
    fwrite(STDERR, "install.xml is not valid XML.\n");
    exit(1);
}

$errors = [];
$tablenames = [];

foreach ($xml->TABLES->TABLE as $table) {
    $tablename = (string)$table['NAME'];

    if (isset($tablenames[$tablename])) {
        $errors[] = "Duplicate table {$tablename}.";
    }

    $tablenames[$tablename] = true;
    $fields = [];

    foreach ($table->FIELDS->FIELD as $field) {
        $fields[(string)$field['NAME']] = true;
    }

    foreach ($table->KEYS->KEY as $key) {
        $keyname = (string)$key['NAME'];

        foreach (preg_split('/\s*,\s*/', (string)$key['FIELDS']) as $fieldname) {
            if ($fieldname !== '' && !isset($fields[$fieldname])) {
                $errors[] = "{$tablename}.{$keyname} references missing field {$fieldname}.";
            }
        }
    }

    if (isset($table->INDEXES)) {
        foreach ($table->INDEXES->INDEX as $index) {
            $indexname = (string)$index['NAME'];

            foreach (preg_split('/\s*,\s*/', (string)$index['FIELDS']) as $fieldname) {
                if ($fieldname !== '' && !isset($fields[$fieldname])) {
                    $errors[] = "{$tablename}.{$indexname} references missing field {$fieldname}.";
                }
            }
        }
    }
}

if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "xmldb_schema_test: OK\n";
