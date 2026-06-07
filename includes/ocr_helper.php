<?php
defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aisn_ocr_clean_text')) {
    function local_aisn_ocr_clean_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', (string)$text);
        $text = preg_replace("/\n{3,}/u", "\n\n", (string)$text);
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', (string)$text);
        return trim((string)$text);
    }
}

if (!function_exists('local_aisn_ocr_tool_path')) {
    function local_aisn_ocr_tool_path(string $tool): string {
        $tool = preg_replace('/[^a-zA-Z0-9_\-]/', '', $tool);
        if ($tool === '') {
            return '';
        }
        return trim((string)@shell_exec('command -v ' . escapeshellarg($tool) . ' 2>/dev/null'));
    }
}

if (!function_exists('local_aisn_ocr_enabled')) {
    function local_aisn_ocr_enabled(): bool {
        $value = get_config('local_aiskillnavigator', 'enablelocalocr');
        if ($value === false || $value === null || $value === '') {
            return true;
        }
        return ((string)$value) === '1';
    }
}

if (!function_exists('local_aisn_ocr_max_images')) {
    function local_aisn_ocr_max_images(): int {
        $configured = (int)get_config('local_aiskillnavigator', 'ocrmaximages');
        if ($configured > 0) {
            return min(250, $configured);
        }
        return 120;
    }
}

if (!function_exists('local_aisn_ocr_max_image_bytes')) {
    function local_aisn_ocr_max_image_bytes(): int {
        $configured = (int)get_config('local_aiskillnavigator', 'ocrmaximagebytes');
        if ($configured > 0) {
            return min(50 * 1024 * 1024, $configured);
        }
        return 18 * 1024 * 1024;
    }
}

if (!function_exists('local_aisn_ocr_available')) {
    function local_aisn_ocr_available(): bool {
        return local_aisn_ocr_enabled() && local_aisn_ocr_tool_path('tesseract') !== '';
    }
}

if (!function_exists('local_aisn_ocr_status_text')) {
    function local_aisn_ocr_status_text(): string {
        $tesseract = local_aisn_ocr_tool_path('tesseract');
        $pdftoppm = local_aisn_ocr_tool_path('pdftoppm');
        $pdftotext = local_aisn_ocr_tool_path('pdftotext');
        return 'OCR status: tesseract=' . ($tesseract !== '' ? 'OK' : 'MISSING')
            . ', pdftoppm=' . ($pdftoppm !== '' ? 'OK' : 'MISSING')
            . ', pdftotext=' . ($pdftotext !== '' ? 'OK' : 'MISSING');
    }
}

if (!function_exists('local_aisn_ocr_image_path')) {
    function local_aisn_ocr_image_path(string $imagepath, string $label = ''): string {
        if (!local_aisn_ocr_available()) {
            return '';
        }
        if ($imagepath === '' || !is_readable($imagepath)) {
            return '';
        }
        $size = (int)@filesize($imagepath);
        if ($size <= 0 || $size > local_aisn_ocr_max_image_bytes()) {
            return '';
        }
        $tesseract = local_aisn_ocr_tool_path('tesseract');
        $lang = trim((string)get_config('local_aiskillnavigator', 'ocrlanguages'));
        if ($lang === '') {
            $lang = 'ita+eng';
        }
        $cmd = escapeshellcmd($tesseract)
            . ' '
            . escapeshellarg($imagepath)
            . ' stdout -l '
            . escapeshellarg($lang)
            . ' --psm 6 2>/dev/null';
        $text = local_aisn_ocr_clean_text((string)@shell_exec($cmd));
        if ($text === '') {
            return '';
        }
        if ($label !== '') {
            return "[OCR image: " . $label . "]\n" . $text;
        }
        return $text;
    }
}

if (!function_exists('local_aisn_zip_extract_entry_to_temp')) {
    function local_aisn_zip_extract_entry_to_temp(ZipArchive $zip, int $index, string $tmpdir): string {
        $entry = (string)$zip->getNameIndex($index);
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($entry));
        if ($safe === '') {
            $safe = uniqid('entry_', true) . '.' . $ext;
        }
        $target = $tmpdir . '/' . uniqid('zip_', true) . '_' . $safe;
        $content = $zip->getFromIndex($index);
        if (!is_string($content) || $content === '') {
            return '';
        }
        file_put_contents($target, $content);
        return $target;
    }
}

if (!function_exists('local_aisn_extract_pptx_xml_text_from_path')) {
    function local_aisn_extract_pptx_xml_text_from_path(string $path): string {
        if (!class_exists('ZipArchive') || !is_readable($path)) {
            return '';
        }
        $zip = new ZipArchive();
        $parts = [];
        if ($zip->open($path) !== true) {
            return '';
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string)$zip->getNameIndex($i);
            if (!preg_match('#^ppt/(slides/slide|notesSlides/notesSlide|comments/comment)[0-9]+\.xml$#', $entry)) {
                continue;
            }
            $xml = $zip->getFromIndex($i);
            if (!is_string($xml) || $xml === '') {
                continue;
            }
            if (preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/s', $xml, $matches)) {
                $localparts = [];
                foreach ($matches[1] as $match) {
                    $text = local_aisn_ocr_clean_text(html_entity_decode((string)$match, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    if ($text !== '') {
                        $localparts[] = $text;
                    }
                }
                if (!empty($localparts)) {
                    $parts[] = "[PPTX text: " . basename($entry) . "]\n" . implode("\n", $localparts);
                }
            }
        }
        $zip->close();
        return local_aisn_ocr_clean_text(implode("\n\n", $parts));
    }
}

if (!function_exists('local_aisn_extract_pptx_chart_text_from_path')) {
    function local_aisn_extract_pptx_chart_text_from_path(string $path): string {
        if (!class_exists('ZipArchive') || !is_readable($path)) {
            return '';
        }
        $zip = new ZipArchive();
        $parts = [];
        if ($zip->open($path) !== true) {
            return '';
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string)$zip->getNameIndex($i);
            if (!preg_match('#^ppt/charts/chart[0-9]+\.xml$#', $entry)) {
                continue;
            }
            $xml = $zip->getFromIndex($i);
            if (!is_string($xml) || $xml === '') {
                continue;
            }
            $values = [];
            if (preg_match_all('/<(?:c:)?v[^>]*>(.*?)<\/(?:c:)?v>/s', $xml, $matches)) {
                foreach ($matches[1] as $match) {
                    $value = local_aisn_ocr_clean_text(html_entity_decode((string)$match, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }
            }
            if (preg_match_all('/<(?:a:)?t[^>]*>(.*?)<\/(?:a:)?t>/s', $xml, $matches)) {
                foreach ($matches[1] as $match) {
                    $value = local_aisn_ocr_clean_text(html_entity_decode((string)$match, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }
            }
            $values = array_values(array_unique($values));
            if (!empty($values)) {
                $parts[] = "[PPTX chart/graph data: " . basename($entry) . "]\n" . implode(" | ", array_slice($values, 0, 250));
            }
        }
        $zip->close();
        return local_aisn_ocr_clean_text(implode("\n\n", $parts));
    }
}

if (!function_exists('local_aisn_ocr_zip_images_from_path')) {
    function local_aisn_ocr_zip_images_from_path(string $path, string $prefix, string $label): string {
        if (!class_exists('ZipArchive') || !is_readable($path) || !local_aisn_ocr_available()) {
            return '';
        }
        $zip = new ZipArchive();
        $parts = [];
        $count = 0;
        $maximages = local_aisn_ocr_max_images();
        if ($zip->open($path) !== true) {
            return '';
        }
        $tmpdir = make_temp_directory('local_aiskillnavigator/ocr_zip_' . uniqid('', true));
        $allowed = ['png', 'jpg', 'jpeg', 'bmp', 'tif', 'tiff', 'webp'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($count >= $maximages) {
                break;
            }
            $entry = (string)$zip->getNameIndex($i);
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!str_starts_with($entry, $prefix) || !in_array($ext, $allowed, true)) {
                continue;
            }
            $image = local_aisn_zip_extract_entry_to_temp($zip, $i, $tmpdir);
            if ($image === '') {
                continue;
            }
            $txt = local_aisn_ocr_image_path($image, $label . ' ' . basename($entry));
            if ($txt !== '') {
                $parts[] = $txt;
            }
            @unlink($image);
            $count++;
        }
        $zip->close();
        @rmdir($tmpdir);
        return local_aisn_ocr_clean_text(implode("\n\n", $parts));
    }
}

if (!function_exists('local_aisn_ocr_pptx_images_from_path')) {
    function local_aisn_ocr_pptx_images_from_path(string $path): string {
        return local_aisn_ocr_zip_images_from_path($path, 'ppt/media/', 'PPTX embedded image');
    }
}

if (!function_exists('local_aisn_ocr_docx_images_from_path')) {
    function local_aisn_ocr_docx_images_from_path(string $path): string {
        return local_aisn_ocr_zip_images_from_path($path, 'word/media/', 'DOCX embedded image');
    }
}

if (!function_exists('local_aisn_extract_docx_xml_text_from_path')) {
    function local_aisn_extract_docx_xml_text_from_path(string $path): string {
        if (!class_exists('ZipArchive') || !is_readable($path)) {
            return '';
        }
        $zip = new ZipArchive();
        $parts = [];
        if ($zip->open($path) !== true) {
            return '';
        }
        $entries = ['word/document.xml', 'word/footnotes.xml', 'word/endnotes.xml', 'word/comments.xml'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string)$zip->getNameIndex($i);
            if (preg_match('#^word/(header|footer)[0-9]+\.xml$#', $entry)) {
                $entries[] = $entry;
            }
        }
        foreach (array_unique($entries) as $entry) {
            $xml = $zip->getFromName($entry);
            if (!is_string($xml) || $xml === '') {
                continue;
            }
            $xml = preg_replace('/<\/w:p>/', "\n", $xml);
            $text = local_aisn_ocr_clean_text(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($text !== '') {
                $parts[] = "[DOCX text: " . basename($entry) . "]\n" . $text;
            }
        }
        $zip->close();
        return local_aisn_ocr_clean_text(implode("\n\n", $parts));
    }
}