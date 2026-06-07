<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class web_search_service {
    private string $provider;
    private string $apikey;
    private string $endpoint;

    public function __construct() {
        $this->provider = strtolower(trim((string) get_config('local_aiskillnavigator', 'searchprovider')));
        $this->apikey = trim((string) get_config('local_aiskillnavigator', 'searchapikey'));
        $this->endpoint = trim((string) get_config('local_aiskillnavigator', 'searchendpoint'));
    }

    public function is_enabled(): bool {
        return $this->provider !== ''
            && $this->provider !== 'none'
            && $this->apikey !== '';
    }

    public function provider_name(): string {
        return $this->provider !== '' ? $this->provider : 'none';
    }

    public function search(string $query, int $limit = 5): array {
        $query = trim($query);
        $limit = max(1, min(10, $limit));

        if (!$this->is_enabled() || $query === '') {
            return [];
        }

        if ($this->provider === 'tavily') {
            return $this->search_tavily($query, $limit);
        }

        if ($this->provider === 'brave') {
            return $this->search_brave($query, $limit);
        }

        if ($this->provider === 'serpapi') {
            return $this->search_serpapi($query, $limit);
        }

        return [];
    }

    private function search_tavily(string $query, int $limit): array {
        $endpoint = $this->endpoint !== '' ? $this->endpoint : 'https://api.tavily.com/search';

        $payload = [
            'query' => $query,
            'search_depth' => 'basic',
            'max_results' => $limit,
            'include_answer' => false,
            'include_raw_content' => false,
            'topic' => 'general',
        ];

        $response = $this->post_json($endpoint, $payload, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apikey,
        ]);

        if (empty($response['ok']) || empty($response['json']['results']) || !is_array($response['json']['results'])) {
            return [];
        }

        $out = [];

        foreach ($response['json']['results'] as $row) {
            $out[] = [
                'title' => trim((string)($row['title'] ?? 'Untitled')),
                'url' => trim((string)($row['url'] ?? '')),
                'snippet' => trim((string)($row['content'] ?? '')),
                'source' => 'tavily',
            ];
        }

        return $this->clean_results($out, $limit);
    }

    private function search_brave(string $query, int $limit): array {
        $endpoint = $this->endpoint !== '' ? $this->endpoint : 'https://api.search.brave.com/res/v1/web/search';

        $url = $endpoint . '?' . http_build_query([
            'q' => $query,
            'count' => $limit,
            'safesearch' => 'moderate',
        ]);

        $response = $this->get_json($url, [
            'Accept: application/json',
            'X-Subscription-Token: ' . $this->apikey,
        ]);

        if (empty($response['ok']) || empty($response['json']['web']['results']) || !is_array($response['json']['web']['results'])) {
            return [];
        }

        $out = [];

        foreach ($response['json']['web']['results'] as $row) {
            $out[] = [
                'title' => trim((string)($row['title'] ?? 'Untitled')),
                'url' => trim((string)($row['url'] ?? '')),
                'snippet' => trim((string)($row['description'] ?? '')),
                'source' => 'brave',
            ];
        }

        return $this->clean_results($out, $limit);
    }

    private function search_serpapi(string $query, int $limit): array {
        $endpoint = $this->endpoint !== '' ? $this->endpoint : 'https://serpapi.com/search.json';

        $url = $endpoint . '?' . http_build_query([
            'engine' => 'google',
            'q' => $query,
            'api_key' => $this->apikey,
            'num' => $limit,
        ]);

        $response = $this->get_json($url, [
            'Accept: application/json',
        ]);

        if (empty($response['ok']) || empty($response['json']['organic_results']) || !is_array($response['json']['organic_results'])) {
            return [];
        }

        $out = [];

        foreach ($response['json']['organic_results'] as $row) {
            $out[] = [
                'title' => trim((string)($row['title'] ?? 'Untitled')),
                'url' => trim((string)($row['link'] ?? '')),
                'snippet' => trim((string)($row['snippet'] ?? '')),
                'source' => 'serpapi',
            ];
        }

        return $this->clean_results($out, $limit);
    }

    private function post_json(string $url, array $payload, array $headers): array {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return ['ok' => false, 'error' => 'Cannot encode JSON payload.'];
        }

        $curl = curl_init($url);

        if ($curl === false) {
            return ['ok' => false, 'error' => 'Cannot initialize cURL.'];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator web_search_service',
        ]);

        return $this->exec_json($curl);
    }

    private function get_json(string $url, array $headers): array {
        $curl = curl_init($url);

        if ($curl === false) {
            return ['ok' => false, 'error' => 'Cannot initialize cURL.'];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator web_search_service',
        ]);

        return $this->exec_json($curl);
    }

    private function exec_json($curl): array {
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            return ['ok' => false, 'status' => $status, 'error' => $error];
        }

        curl_close($curl);

        $json = json_decode((string)$raw, true);

        if (!is_array($json)) {
            return ['ok' => false, 'status' => $status, 'error' => 'Invalid JSON response.', 'raw' => $raw];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'json' => $json,
        ];
    }

    private function clean_results(array $rows, int $limit): array {
        $clean = [];
        $seen = [];

        foreach ($rows as $row) {
            $url = trim((string)($row['url'] ?? ''));

            if ($url === '' || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;

            $title = trim((string)($row['title'] ?? 'Untitled'));
            $snippet = trim((string)($row['snippet'] ?? ''));

            if (\core_text::strlen($snippet) > 500) {
                $snippet = \core_text::substr($snippet, 0, 500) . '...';
            }

            $clean[] = [
                'title' => $title !== '' ? $title : 'Untitled',
                'url' => $url,
                'snippet' => $snippet,
                'source' => (string)($row['source'] ?? $this->provider),
            ];

            if (count($clean) >= $limit) {
                break;
            }
        }

        return $clean;
    }
}