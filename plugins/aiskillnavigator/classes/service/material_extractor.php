<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

global $CFG;

$pdfhelper = $CFG->dirroot . '/local/aiskillnavigator/includes/pdf_text_extractor.php';
$ocrhelper = $CFG->dirroot . '/local/aiskillnavigator/includes/ocr_helper.php';
$mistralhelper = $CFG->dirroot . '/local/aiskillnavigator/includes/mistral_ocr_helper.php';

if (file_exists($pdfhelper)) {
    require_once($pdfhelper);
}

if (file_exists($ocrhelper)) {
    require_once($ocrhelper);
}

if (file_exists($mistralhelper)) {
    require_once($mistralhelper);
}

foreach (glob(__DIR__ . '/material/*.php') as $materialhelper) {
    require_once($materialhelper);
}

class material_extractor {
    private const MAX_BYTES = 26214400;
    private const MAX_CHARS = 120000;

    private const ALLOWED_EXTENSIONS = [
        'txt', 'md', 'csv', 'json', 'xml', 'html', 'htm',
        'css', 'js', 'ts', 'sql', 'cs', 'java', 'py', 'cpp', 'c',
        'pdf', 'pptx', 'docx',
        'png', 'jpg', 'jpeg', 'bmp', 'tif', 'tiff', 'webp',
    ];

    public static function extract($file, ?string $name = null): array {
        if (is_array($file)) {
            return self::extract_from_upload($file);
        }
        if (is_string($file)) {
            return self::extract_from_path($file, $name ?? basename($file));
        }
        if ($file instanceof \stored_file) {
            return self::extract_from_moodle_file($file);
        }
        return self::fail('Unsupported material input.', 'unknown');
    }

    public static function extract_file($file, ?string $name = null): array { return self::extract($file, $name); }
    public static function extract_material($file, ?string $name = null): array { return self::extract($file, $name); }
    public static function extract_uploaded_file(array $file): array { return self::extract_from_upload($file); }

    public static function extract_from_upload(array $file): array {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return self::fail(self::upload_error_message($error), 'upload');
        }
        $name = (string)($file['name'] ?? '');
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($name === '' || $tmp === '' || !is_file($tmp)) {
            return self::fail('Uploaded file is missing or unreadable.', 'upload');
        }
        if ($size <= 0) { $size = (int)filesize($tmp); }
        if ($size <= 0) { return self::fail('Uploaded file is empty.', self::extension($name)); }
        if ($size > self::MAX_BYTES) {
            return self::fail('Uploaded file is too large. Maximum supported size is 25 MB.', self::extension($name));
        }
        return self::extract_from_path($tmp, $name);
    }

    public static function extract_from_moodle_file(\stored_file $file): array {
        $tmpdir = make_temp_directory('local_aiskillnavigator/material_extractor');
        $name = $file->get_filename();
        $path = $tmpdir . '/' . uniqid('material_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $file->copy_content_to($path);
        try {
            $result = self::extract_from_path($path, $name);
        } finally {
            @unlink($path);
        }
        return $result;
    }

    public static function extract_from_path(string $path, string $name = ''): array {
        if ($path === '' || !is_readable($path)) {
            return self::fail('Material file is not readable.', 'unknown');
        }
        $filename = $name !== '' ? $name : basename($path);
        $ext = self::extension($filename);
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return self::fail('Unsupported file type. Supported formats: TXT, MD, CSV, JSON, XML, HTML, code files, PDF, PPTX, DOCX and images.', $ext);
        }
        switch ($ext) {
            case 'txt': case 'md': case 'csv': case 'json': case 'xml': case 'css': case 'js': case 'ts': case 'sql': case 'cs': case 'java': case 'py': case 'cpp': case 'c':
                return self::extract_text_file($path, $filename, $ext);
            case 'html': case 'htm':
                return self::extract_html_file($path, $filename, $ext);
            case 'pdf':
                return self::extract_pdf($path, $filename);
            case 'pptx':
                return self::extract_pptx($path, $filename);
            case 'docx':
                return self::extract_docx($path, $filename);
            case 'png': case 'jpg': case 'jpeg': case 'bmp': case 'tif': case 'tiff': case 'webp':
                return self::extract_image($path, $filename, $ext);
            default:
                return self::fail('Unsupported file type.', $ext);
        }
    }

    private static function extract_text_file(string $path, string $filename, string $ext): array {
        $text = file_get_contents($path);
        if ($text === false) { return self::fail('Could not read text file.', $ext); }
        $text = self::limit(self::clean($text));
        if ($text === '') { return self::fail('No readable text found in file.', $ext); }
        return self::ok($text, $ext, $filename, 'Text extracted successfully.');
    }

    private static function extract_html_file(string $path, string $filename, string $ext): array {
        $html = file_get_contents($path);
        if ($html === false) { return self::fail('Could not read HTML file.', $ext); }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = self::limit(self::clean($text));
        if ($text === '') { return self::fail('No readable text found in HTML file.', $ext); }
        return self::ok($text, $ext, $filename, 'HTML extracted successfully.');
    }

    // AISN_MATERIAL_EXTRACTOR_MISTRAL_FIRST_V1
    private static function try_mistral_ocr(string $path, string $filename, string $type): ?array {
        if (!function_exists('\\local_aisn_mistral_ocr_supported_extension') ||
            !function_exists('\\local_aisn_mistral_ocr_extract_path')) {
            return null;
        }

        $ext = self::extension($filename);

        if (!\local_aisn_mistral_ocr_supported_extension($ext)) {
            return null;
        }

        $text = \local_aisn_mistral_ocr_extract_path($path, $filename, 0);
        $text = self::limit(self::clean((string)$text));

        if ($text === '') {
            return null;
        }

        return self::ok($text, $type, $filename, 'Document converted to structured Markdown using Mistral OCR.');
    }
    private static function extract_pdf(string $path, string $filename): array {
        $mistral = self::try_mistral_ocr($path, $filename, 'pdf');
        if ($mistral !== null) { return $mistral; }

        if (function_exists('\\local_aiskillnavigator_extract_pdf_text_from_path')) {
            $text = \local_aiskillnavigator_extract_pdf_text_from_path($path, $filename);
            $text = self::limit(self::clean($text));
            if ($text !== '') {
                return self::ok($text, 'pdf', $filename, 'PDF converted to TXT using fast text-layer extraction; OCR only for small scanned PDFs.');
            }
        }
        return self::fail('No readable text layer found in PDF. Large scanned PDFs are not OCRed during Course Builder for performance.', 'pdf');
    }

    private static function extract_pptx(string $path, string $filename): array {
        $parts = [];

        if (class_exists('\\local_aiskillnavigator\\service\\material\\pptx_extractor')) {
            $result = (new material\pptx_extractor())->extract($path);
            if (!empty($result['success']) && trim((string)($result['content'] ?? '')) !== '') {
                $parts[] = (string)$result['content'];
            }
        }

        // Fast PPTX -> TXT mode: read slide XML and chart XML only.
        // No embedded-image OCR here, otherwise 100MB+ decks freeze the Course Builder.
        if (function_exists('\\local_aisn_extract_pptx_xml_text_from_path')) { $parts[] = \local_aisn_extract_pptx_xml_text_from_path($path); }
        if (function_exists('\\local_aisn_extract_pptx_chart_text_from_path')) { $parts[] = \local_aisn_extract_pptx_chart_text_from_path($path); }

        $content = self::limit(self::clean(implode("\n\n", array_filter($parts))));
        if ($content === '') {
            return self::ok('[PPTX linked as Moodle resource. Fast XML-to-TXT found no selectable text; embedded-image OCR skipped for performance.]', 'pptx', $filename, 'PPTX linked without OCR.');
        }
        return self::ok($content, 'pptx', $filename, 'PPTX converted to TXT from slide XML/chart XML without OCR.');
    }

    private static function extract_docx(string $path, string $filename): array {
        $parts = [];

        $xmltext = self::extract_docx_xml_text($path);
        if ($xmltext !== '') {
            $parts[] = $xmltext;
        }

        if (function_exists('\\local_aisn_extract_docx_xml_text_from_path')) { $parts[] = \local_aisn_extract_docx_xml_text_from_path($path); }

        // Only OCR embedded DOCX images for small files and only when XML text is weak.
        $size = is_readable($path) ? (int)@filesize($path) : 0;
        $large = $size >= self::large_file_threshold();
        $current = self::clean(implode("\n\n", array_filter($parts)));
        if (!$large && strlen($current) < 2000 && function_exists('\\local_aisn_ocr_docx_images_from_path')) {
            $parts[] = \local_aisn_ocr_docx_images_from_path($path);
        }

        $content = self::limit(self::clean(implode("\n\n", array_filter($parts))));
        if ($content === '') { return self::fail('No readable text found in DOCX.', 'docx'); }
        return self::ok($content, 'docx', $filename, 'DOCX converted to TXT from XML; OCR only for small image-heavy files.');
    }

    private static function extract_docx_xml_text(string $path): string {
        if (!class_exists('\\ZipArchive')) {
            return '';
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $entries = ['word/document.xml'];
        for ($i = 1; $i <= 5; $i++) {
            $entries[] = 'word/header' . $i . '.xml';
            $entries[] = 'word/footer' . $i . '.xml';
        }

        $parts = [];

        foreach ($entries as $entry) {
            $xml = $zip->getFromName($entry);
            if ($xml === false || trim((string)$xml) === '') {
                continue;
            }

            $text = self::extract_openxml_text((string)$xml);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        $zip->close();

        return trim(implode("\n\n", $parts));
    }

    private static function extract_openxml_text(string $xml): string {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();

        if (!$dom->loadXML($xml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return '';
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $nodes = $xpath->query('//w:t');
        $parts = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $value = trim($node->textContent);
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return trim((string) preg_replace('/\s+/u', ' ', implode(' ', $parts)));
    }

    private static function extract_image(string $path, string $filename, string $ext): array {
        $text = function_exists('\\local_aisn_ocr_image_path') ? \local_aisn_ocr_image_path($path, $filename) : '';
        $text = self::limit(self::clean($text));
        if ($text === '') {
            return self::fail('No readable text found in image. OCR needs local Tesseract and a readable image.', $ext);
        }
        return self::ok($text, $ext, $filename, 'Image OCR extracted successfully.');
    }

    private static function large_file_threshold(): int {
        $configured = (int)get_config('local_aiskillnavigator', 'largefilethresholdbytes');
        if ($configured > 0) {
            return max(5 * 1024 * 1024, $configured);
        }
        return 25 * 1024 * 1024;
    }

    private static function extension(string $filename): string { return strtolower((string)pathinfo($filename, PATHINFO_EXTENSION)); }

    private static function clean(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace("/\n{3,}/u", "\n\n", (string)$text);
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', (string)$text);
        return trim((string)$text);
    }

    private static function limit(string $text): string {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') > self::MAX_CHARS) {
                return mb_substr($text, 0, self::MAX_CHARS, 'UTF-8') . "\n\n[Content truncated]";
            }
            return $text;
        }
        if (strlen($text) > self::MAX_CHARS) {
            return substr($text, 0, self::MAX_CHARS) . "\n\n[Content truncated]";
        }
        return $text;
    }

    private static function ok(string $content, string $type, string $filename, string $message): array {
        return ['success' => true, 'content' => $content, 'message' => $message, 'type' => $type, 'filename' => $filename];
    }

    private static function fail(string $message, string $type): array {
        return ['success' => false, 'content' => '', 'message' => $message, 'type' => $type];
    }

    private static function upload_error_message(int $error): string {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: return 'Uploaded file is too large.';
            case UPLOAD_ERR_PARTIAL: return 'Uploaded file was only partially uploaded.';
            case UPLOAD_ERR_NO_FILE: return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR: return 'Missing temporary upload directory.';
            case UPLOAD_ERR_CANT_WRITE: return 'Could not write uploaded file to disk.';
            case UPLOAD_ERR_EXTENSION: return 'Upload was stopped by a PHP extension.';
            default: return 'Unknown upload error.';
        }
    }
}


