<?php
/** Exercise the real HTTP client with an in-process transport double. */
namespace local_aiskillnavigator\service\provider {
    function curl_init($url) { $GLOBALS['lasturl'] = $url; return true; }
    function curl_setopt_array($curl, $options) { $GLOBALS['lastoptions'] = $options; return true; }
    function curl_exec($curl) { return '{"choices":[{"message":{"content":"ok"}}]}'; }
    function curl_getinfo($curl, $option) { return 200; }
    function curl_close($curl) {}
}
namespace {
    define('MOODLE_INTERNAL', true);
    $testconfig = [];
    function get_config($component, $name) { return $GLOBALS['testconfig'][$name] ?? false; }
    require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/provider/http_json_client.php');
    require_once(__DIR__ . '/../plugins/aiskillnavigator/includes/ai_response_guard.php');
    use local_aiskillnavigator\service\provider\http_json_client;
    function check($expected, $actual, $message) {
        if ($expected !== $actual) { throw new RuntimeException($message . ': ' . var_export($actual, true)); }
    }
    $client = new http_json_client();
    foreach ([null => 60, '' => 60, '0' => 60, '-1' => 60, 'invalid' => 60, '5' => 10, '180' => 180, '900' => 600] as $input => $expected) {
        $testconfig['requesttimeout'] = $input;
        $result = $client->post('http://localhost:11434/api/chat', ['prompt' => 'hello']);
        check(true, $result['ok'], 'Local request should succeed');
        check($expected, $lastoptions[CURLOPT_TIMEOUT], 'Configured timeout reaches cURL');
        check(min(15, $expected), $lastoptions[CURLOPT_CONNECTTIMEOUT], 'Connect timeout fits total timeout');
    }
    $testconfig = [];
    $client->post('https://api.example.test/v1/chat/completions', [], [], 25);
    check(25, $lastoptions[CURLOPT_TIMEOUT], 'Explicit caller timeout is honoured');
    check(false, $lastoptions[CURLOPT_FOLLOWLOCATION], 'Redirects remain disabled');
    check(true, $lastoptions[CURLOPT_SSL_VERIFYPEER], 'TLS verification remains enabled');
    foreach (['http://[::1]:11434', 'http://ollama:11434', 'http://model.local:1234'] as $url) {
        check(true, $client->post($url, [])['ok'], 'Local endpoint should be accepted');
    }
    foreach (['http://remote.example.test', 'file:///etc/passwd', 'https://192.168.1.2', ''] as $url) {
        check(false, $client->post($url, [])['ok'], 'Disallowed endpoint must be rejected');
    }
    foreach (['', 'Errore AI API/cURL HTTP 0: Operation timed out', 'AI error: failed', 'AI provider not available.'] as $error) {
        check(true, local_aiskillnavigator_ai_response_is_error($error), 'Provider failure must not become an answer');
    }
    check(false, local_aiskillnavigator_ai_response_is_error('An error in this algorithm occurs when...'), 'Real explanation is not a transport error');
    echo "provider_http_test: OK\n";
}
