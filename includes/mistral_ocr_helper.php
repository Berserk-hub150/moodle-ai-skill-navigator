<?php

defined('MOODLE_INTERNAL') || die();

$aisnDocumentOcrHelper = __DIR__ . '/document_ocr_toggle_helper.php';
if (file_exists($aisnDocumentOcrHelper)) {
    require_once($aisnDocumentOcrHelper);
}

/**
 * Optional Mistral OCR helper.
 *
 * Strategy:
 * - Try Mistral OCR first for complex documents.
 * - Return Markdown when conversion is successful.
 * - Return empty string on any error, timeout, quota issue or local-only policy.
 * - The caller must fallback to the existing local extraction pipeline.
 */

if (!function_exists('local_aisn_mistral_ocr_enabled')) {
    function local_aisn_mistral_ocr_enabled(): bool {
        $enabled = (string)get_config('local_aiskillnavigator', 'mistral_ocr_enabled');
        $key = trim((string)get_config('local_aiskillnavigator', 'mistral_ocr_api_key'));

        if ($key === '') {
            $key = trim((string)getenv('MISTRAL_API_KEY'));
        }

        return $enabled === '1' && $key !== '';
    }
}

if (!function_exists('local_aisn_mistral_ocr_api_key')) {
    function local_aisn_mistral_ocr_api_key(): string {
        $key = trim((string)get_config('local_aiskillnavigator', 'mistral_ocr_api_key'));

        if ($key === '') {
            $key = trim((string)getenv('MISTRAL_API_KEY'));
        }

        return $key;
    }
}

if (!function_exists('local_aisn_mistral_ocr_supported_extension')) {
    function local_aisn_mistral_ocr_supported_extension(string $extension): bool {
        $extension = strtolower(trim($extension));

        return in_array($extension, [
            'pdf', 'pptx', 'docx',
            'png', 'jpg', 'jpeg', 'webp', 'avif',
        ], true);
    }
}

if (!function_exists('local_aisn_mistral_ocr_mime_type')) {
    function local_aisn_mistral_ocr_mime_type(string $filename): string {
        $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'pdf':
                return 'application/pdf';
            case 'pptx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            case 'docx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            case 'png':
                return 'image/png';
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'webp':
                return 'image/webp';
            case 'avif':
                return 'image/avif';
            default:
                return 'application/octet-stream';
        }
    }
}

if (!function_exists('local_aisn_mistral_ocr_max_bytes')) {
    function local_aisn_mistral_ocr_max_bytes(): int {
        $configured = (int)get_config('local_aiskillnavigator', 'mistral_ocr_maxbytes');

        if ($configured > 0) {
            return $configured;
        }

        return 100 * 1024 * 1024;
    }
}

if (!function_exists('local_aisn_mistral_ocr_timeout')) {
    function local_aisn_mistral_ocr_timeout(): int {
        $configured = (int)get_config('local_aiskillnavigator', 'mistral_ocr_timeout');

        if ($configured >= 30) {
            return min(300, $configured);
        }

        return 120;
    }
}

if (!function_exists('local_aisn_mistral_ocr_model')) {
    function local_aisn_mistral_ocr_model(): string {
        $model = trim((string)get_config('local_aiskillnavigator', 'mistral_ocr_model'));
        return $model !== '' ? $model : 'mistral-ocr-latest';
    }
}

if (!function_exists('local_aisn_mistral_ocr_can_send_external')) {
    function local_aisn_mistral_ocr_can_send_external(int $cmid): bool {
        if ($cmid <= 0) {
            return false;
        }

        if (function_exists('local_aiskillnavigator_course_module_external_ai_allowed')) {
            return local_aiskillnavigator_course_module_external_ai_allowed($cmid) === 1;
        }

        return false;
    }
}

if (!function_exists('local_aisn_mistral_ocr_extract_path')) {
    function local_aisn_mistral_ocr_extract_path(string $path, string $filename, int $cmid = 0): string {
        // AISN_COURSE_SCOPED_OCR_GATE_V1
        // Advanced OCR is course-scoped. If disabled for the course of this cmid,
        // Mistral is skipped and the plugin falls back to the local extraction pipeline.
        if (function_exists('local_aisn_document_ocr_cmid_enabled') && !local_aisn_document_ocr_cmid_enabled((int)$cmid)) {
            return '';
        }

        if (!local_aisn_mistral_ocr_enabled()) {
            return '';
        }

        if (!local_aisn_mistral_ocr_can_send_external($cmid)) {
            debugging('AI Skill Navigator Mistral OCR skipped: material is local-only.', DEBUG_DEVELOPER);
            return '';
        }

        if ($path === '' || !is_readable($path)) {
            return '';
        }

        $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));

        if (!local_aisn_mistral_ocr_supported_extension($extension)) {
            return '';
        }

        $size = (int)@filesize($path);

        if ($size <= 0 || $size > local_aisn_mistral_ocr_max_bytes()) {
            debugging('AI Skill Navigator Mistral OCR skipped: file too large or empty.', DEBUG_DEVELOPER);
            return '';
        }

        if (!function_exists('curl_init')) {
            debugging('AI Skill Navigator Mistral OCR skipped: PHP curl extension not available.', DEBUG_DEVELOPER);
            return '';
        }

        $key = local_aisn_mistral_ocr_api_key();

        if ($key === '') {
            return '';
        }

        $raw = @file_get_contents($path);

        if ($raw === false || $raw === '') {
            return '';
        }

        $mime = local_aisn_mistral_ocr_mime_type($filename);
        $base64 = base64_encode($raw);
        $dataurl = 'data:' . $mime . ';base64,' . $base64;

        $isimagetype = strpos($mime, 'image/') === 0;
        $documenttype = $isimagetype ? 'image_url' : 'document_url';
        $documentfield = $isimagetype ? 'image_url' : 'document_url';

        $payload = [
            'model' => local_aisn_mistral_ocr_model(),
            'document' => [
                'type' => $documenttype,
                $documentfield => $dataurl,
            ],
            'include_image_base64' => false,
            'table_format' => null,
            'extract_header' => true,
            'extract_footer' => false,
        ];

        $curl = curl_init('https://api.mistral.ai/v1/ocr');

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            // AISN_MISTRAL_CURL_HARDENING_V2
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => local_aisn_mistral_ocr_timeout(),
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator mistral OCR client',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($curl);

        curl_close($curl);

        if ($response === false || $response === '' || $httpcode >= 400) {
            debugging('AI Skill Navigator Mistral OCR failed. HTTP ' . $httpcode . ' ' . $curlerror, DEBUG_DEVELOPER);
            return '';
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || empty($decoded['pages']) || !is_array($decoded['pages'])) {
            debugging('AI Skill Navigator Mistral OCR returned invalid JSON structure.', DEBUG_DEVELOPER);
            return '';
        }

        $blocks = [];

        foreach ($decoded['pages'] as $page) {
            if (!is_array($page)) {
                continue;
            }

            $index = isset($page['index']) ? (string)$page['index'] : '';
            $markdown = trim((string)($page['markdown'] ?? ''));
            $header = trim((string)($page['header'] ?? ''));

            $block = [];

            if ($index !== '') {
                $block[] = '<!-- page ' . $index . ' -->';
            }

            if ($header !== '') {
                $block[] = '<!-- header: ' . $header . ' -->';
            }

            if ($markdown !== '') {
                $block[] = $markdown;
            }

            if (!empty($block)) {
                $blocks[] = implode("\n\n", $block);
            }
        }

        $text = trim(implode("\n\n---\n\n", $blocks));

        if ($text === '') {
            return '';
        }

        // AISN_MISTRAL_REJECT_TABLE_LINK_ONLY_V1
        // If Mistral returns only asset links such as [tbl-0.html](tbl-0.html),
        // the output is not useful for RAG. Fallback to the local pipeline.
        $semantic = preg_replace('/<!--.*?-->/s', ' ', $text);
        $semantic = preg_replace('/\[[^\]]+\]\((?:tbl|img)-[^\)]+\)/i', ' ', (string)$semantic);
        $semantic = preg_replace('/[-\s_]+/u', ' ', (string)$semantic);
        $semantic = trim((string)$semantic);

        if (strlen($semantic) < 25) {
            debugging('AI Skill Navigator Mistral OCR returned only asset links; fallback to local extraction.', DEBUG_DEVELOPER);
            return '';
        }

        return "# " . $filename . "\n\n" . $text;
    }
}

if (!function_exists('local_aisn_mistral_ocr_extract_stored_file')) {
    function local_aisn_mistral_ocr_extract_stored_file(stored_file $file, int $cmid = 0): string {
        $filename = $file->get_filename();
        $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));

        if (!local_aisn_mistral_ocr_supported_extension($extension)) {
            return '';
        }

        $tmpdir = make_temp_directory('local_aiskillnavigator/mistral_ocr');
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $tmppath = $tmpdir . '/' . uniqid('mistral_', true) . '_' . $safe;

        $file->copy_content_to($tmppath);

        try {
            return local_aisn_mistral_ocr_extract_path($tmppath, $filename, $cmid);
        } catch (Throwable $e) {
            debugging('AI Skill Navigator Mistral OCR exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        } finally {
            @unlink($tmppath);
        }
    }
}


