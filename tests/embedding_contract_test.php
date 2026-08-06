<?php

define('MOODLE_INTERNAL', true);

function get_config($component, $name) {
    return false;
}

require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/embedding_service.php');

use local_aiskillnavigator\service\embedding_service;

$method = new ReflectionMethod(embedding_service::class, 'index_material');

if ($method->getNumberOfRequiredParameters() !== 1) {
    fwrite(STDERR, "index_material() must remain callable with a material ID only.\n");
    exit(1);
}

if ($method->getNumberOfParameters() !== 4) {
    fwrite(STDERR, "index_material() compatibility signature unexpectedly changed.\n");
    exit(1);
}

if (!method_exists(embedding_service::class, 'index_material_by_id')) {
    fwrite(STDERR, "index_material_by_id() compatibility entry point is missing.\n");
    exit(1);
}

echo "embedding_contract_test: OK\n";
