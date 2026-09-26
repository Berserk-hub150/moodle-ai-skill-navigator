<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiskillnavigator\service\provider;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Small hardened HTTP JSON client for AI providers.
/**
 * Http json client implementation.
 */
class http_json_client {
    /**
     * Post helper.
     *
     * @param string $url Url.
     * @param array $payload Payload.
     * @param array $headers Headers.
     * @param int|null $timeout Timeout.
     */
    public function post(string $url, array $payload, array $headers = [], ?int $timeout = null): array {
        $timeout = self::request_timeout($timeout);
        $validation = $this->validate_url($url);

        if ($validation !== '') {
            return [
                'ok' => false,
                'status' => 0,
                'error' => $validation,
                'raw' => '',
                'body' => null,
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'PHP cURL extension is not available.',
                'raw' => '',
                'body' => null,
            ];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'Cannot encode JSON payload.',
                'raw' => '',
                'body' => null,
            ];
        }

        $curl = curl_init($url);

        if ($curl === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'Cannot initialize cURL.',
                'raw' => '',
                'body' => null,
            ];
        }

        $headers = $this->normalise_headers($headers);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator AI client',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);

            return [
                'ok' => false,
                'status' => $status,
                'error' => $error,
                'raw' => '',
                'body' => null,
            ];
        }

        curl_close($curl);

        $body = json_decode((string)$raw, true);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => '',
            'raw' => (string)$raw,
            'body' => is_array($body) ? $body : null,
        ];
    }

    /**
     * Normalise headers helper.
     *
     * @param array $headers Headers.
     */
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

    /**
     * Resolve the administrator's generation timeout, in seconds.
     *
     * @param int|null $timeout Explicit timeout, or null to use the plugin setting.
     * @return int Timeout bounded to 10–600 seconds; invalid settings use 60 seconds.
     */
    public static function request_timeout(?int $timeout = null): int {
        if ($timeout === null) {
            $configured = get_config('local_aiskillnavigator', 'requesttimeout');
            $timeout = filter_var($configured, FILTER_VALIDATE_INT);
        }
        if ($timeout === false || $timeout <= 0) {
            return 60;
        }
        return max(10, min(600, $timeout));
    }

    /**
     * Validate url helper.
     *
     * @param string $url Url.
     */
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
        $host = strtolower(trim((string)$parts['host'], '[]'));

        $localhosts = ['localhost', '127.0.0.1', '::1', 'host.docker.internal', 'ollama'];
        $islocal = in_array($host, $localhosts, true) || (bool)preg_match('/(^|\.)local$/', $host);

        if ($scheme !== 'https') {
            if ($scheme === 'http' && $islocal) {
                return '';
            }

            return 'Only HTTPS endpoints are allowed, except local HTTP providers such as Ollama/LM Studio.';
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

    /**
     * Is public ip helper.
     *
     * @param string $ip Ip.
     */
    private function is_public_ip(string $ip): bool {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
