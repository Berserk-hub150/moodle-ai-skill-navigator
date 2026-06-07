<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Hardened HTTP client used by embedding providers.
class embedding_http_client {
    public function post(string $url, array $payload, array $headers = [], int $timeout = 60): ?array {
        $validation = $this->validate_url($url);

        if ($validation !== '') {
            debugging('AI Skill Navigator embedding endpoint blocked: ' . $validation, DEBUG_DEVELOPER);
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return null;
        }

        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        $headers = $this->normalise_headers($headers);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => max(10, $timeout),
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator embedding client',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($raw === false || $status < 200 || $status >= 300) {
            curl_close($curl);
            return null;
        }

        curl_close($curl);

        $body = json_decode((string)$raw, true);

        return is_array($body) ? $body : null;
    }

    private function normalise_headers(array $headers): array {
        $out = [];
        $hascontenttype = false;

        foreach ($headers as $header) {
            $header = trim((string)$header);

            if ($header === '') {
                continue;
            }

            if (stripos($header, 'Content-Type:') === 0) {
                $hascontenttype = true;
            }

            $out[] = $header;
        }

        if (!$hascontenttype) {
            $out[] = 'Content-Type: application/json';
        }

        return $out;
    }

    private function validate_url(string $url): string {
        $url = trim($url);

        if ($url === '') {
            return 'Endpoint URL is empty.';
        }

        $parts = parse_url($url);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return 'Invalid endpoint URL.';
        }

        $scheme = strtolower((string)$parts['scheme']);
        $host = strtolower((string)$parts['host']);

        $localhosts = ['localhost', '127.0.0.1', '::1', 'host.docker.internal'];

        if ($scheme !== 'https') {
            if ($scheme === 'http' && in_array($host, $localhosts, true)) {
                return '';
            }

            return 'Only HTTPS endpoints are allowed, except local HTTP embedding providers.';
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (in_array($host, $localhosts, true)) {
                return '';
            }

            if (!$this->is_public_ip($host)) {
                return 'Private, reserved or internal IP endpoints are not allowed.';
            }
        }

        return '';
    }

    private function is_public_ip(string $ip): bool {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}