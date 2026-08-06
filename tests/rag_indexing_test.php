<?php

define('MOODLE_INTERNAL', true);

$testconfig = [
    'provider' => 'prototype',
    'embeddingprovider' => 'same_as_chat',
];

function get_config($component, $name) {
    global $testconfig;

    return array_key_exists($name, $testconfig) ? $testconfig[$name] : false;
}

class core_text {
    public static function strlen(string $text): int {
        return mb_strlen($text, 'UTF-8');
    }

    public static function substr(string $text, int $start, ?int $length = null): string {
        return $length === null
            ? mb_substr($text, $start, null, 'UTF-8')
            : mb_substr($text, $start, $length, 'UTF-8');
    }
}

class fake_moodle_database {
    public stdClass $material;
    public array $inserted = [];

    public function __construct(stdClass $material) {
        $this->material = $material;
    }

    public function get_record(string $table, array $conditions) {
        if ($table === 'local_aiskillnav_material' && (int)$conditions['id'] === (int)$this->material->id) {
            return clone $this->material;
        }

        return false;
    }

    public function delete_records(string $table, array $conditions): void {
    }

    public function insert_record(string $table, stdClass $record): int {
        $this->inserted[] = clone $record;
        return count($this->inserted);
    }
}

require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/embedding_service.php');

use local_aiskillnavigator\service\embedding_service;

$material = (object)[
    'id' => 17,
    'courseid' => 42,
    'title' => 'Database fundamentals',
    'content' => 'A relational database stores data in tables linked by keys.',
    'externalaiallowed' => 0,
    'aipolicy' => 'local_only',
];

$DB = new fake_moodle_database($material);
$result = (new embedding_service())->index_material(17, 42);

if (empty($result['success']) || (int)$result['chunks'] !== 1 || count($DB->inserted) !== 1) {
    fwrite(STDERR, "RAG indexing by material ID failed.\n");
    exit(1);
}

$embedding = json_decode((string)$DB->inserted[0]->embedding, true);

if ($embedding !== []) {
    fwrite(STDERR, "Prototype mode unexpectedly generated an external embedding.\n");
    exit(1);
}

$testconfig = [
    'provider' => 'openai',
    'embeddingprovider' => 'same_as_chat',
    'externalaiapproved' => '0',
];
$material->externalaiallowed = 1;
$material->aipolicy = 'external_allowed';
$DB = new fake_moodle_database($material);
$result = (new embedding_service())->index_material(17, 42);

if (empty($result['success']) || json_decode((string)$DB->inserted[0]->embedding, true) !== []) {
    fwrite(STDERR, "External embeddings were not blocked by the global privacy gate.\n");
    exit(1);
}

echo "rag_indexing_test: OK\n";
