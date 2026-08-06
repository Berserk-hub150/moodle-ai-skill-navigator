<?php

define('MOODLE_INTERNAL', true);

$testconfig = [];

function get_config($component, $name) {
    global $testconfig;

    return array_key_exists($name, $testconfig) ? $testconfig[$name] : false;
}

require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/embedding/embedding_config.php');
require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/embedding/embedding_client.php');

use local_aiskillnavigator\service\embedding\embedding_config;
use local_aiskillnavigator\service\embedding\embedding_client;

function assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL
        );
        exit(1);
    }
}

function embedding_config_for(array $values): embedding_config {
    global $testconfig;

    $testconfig = $values;
    return new embedding_config();
}

$config = embedding_config_for([
    'provider' => 'prototype',
    'embeddingprovider' => 'same_as_chat',
]);
assert_same('keyword', $config->provider, 'Prototype must use keyword fallback');
assert_same('', $config->endpoint, 'Prototype must not contact an embedding endpoint');
assert_same(false, $config->uses_external_service(), 'Keyword fallback is local');

$config = embedding_config_for([
    'provider' => 'openai',
    'embeddingprovider' => 'same_as_chat',
]);
assert_same('openai', $config->provider, 'OpenAI chat can use OpenAI embeddings');
assert_same('https://api.openai.com/v1', $config->endpoint, 'OpenAI embeddings need the correct default endpoint');
assert_same(true, $config->uses_external_service(), 'OpenAI endpoint is external');

$config = embedding_config_for([
    'provider' => 'ollama',
    'embeddingprovider' => 'same_as_chat',
]);
assert_same('ollama', $config->provider, 'Ollama chat must use Ollama embeddings');
assert_same('http://host.docker.internal:11434', $config->endpoint, 'Ollama needs the local default endpoint');
assert_same(false, $config->uses_external_service(), 'Default Ollama endpoint is local');

$config = embedding_config_for([
    'provider' => 'gemini',
    'embeddingprovider' => 'same_as_chat',
]);
assert_same('keyword', $config->provider, 'Unsupported chat embedding protocols must fall back to keywords');
assert_same('', $config->endpoint, 'Unsupported providers must not fall through to Ollama');

$config = embedding_config_for([
    'provider' => 'prototype',
    'embeddingprovider' => 'custom_http',
    'embeddingendpoint' => 'https://embeddings.example.test/v1',
]);
assert_same('custom_http', $config->provider, 'Explicit custom embeddings must be preserved');
assert_same('https://embeddings.example.test/v1', $config->endpoint, 'Custom embedding endpoint must be preserved');

$config = embedding_config_for([
    'provider' => 'openai',
    'embeddingprovider' => 'openai',
    'embeddingendpoint' => 'https://embeddings.example.test/v1/embeddings',
]);
$client = new embedding_client($config);
$urlmethod = new ReflectionMethod($client, 'openai_url');
$urlmethod->setAccessible(true);
assert_same(
    'https://embeddings.example.test/v1/embeddings',
    $urlmethod->invoke($client),
    'A full OpenAI embeddings endpoint must not gain a duplicate /v1 segment'
);

echo "embedding_config_test: OK\n";
