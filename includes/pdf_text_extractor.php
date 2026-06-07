<?php

defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aiskillnavigator_pdf_tool_path')) {
    function local_aiskillnavigator_pdf_tool_path(string $tool): string {
        $tool = preg_replace('/[^a-zA-Z0-9_\-]/', '', $tool);
        return trim((string)@shell_exec('command -v ' . escapeshellarg($tool) . ' 2>/dev/null'));
    }
}

if (!function_exists('local_aiskillnavigator_pdf_clean_text')) {
    function local_aiskillnavigator_pdf_clean_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace("/\n{3,}/u", "\n\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', (string)$text);

        return trim((string)$text);
    }
}

if (!function_exists('local_aiskillnavigator_pdf_timeout_prefix')) {
    function local_aiskillnavigator_pdf_timeout_prefix(int $seconds): string {
        $timeout = local_aiskillnavigator_pdf_tool_path('timeout');
        return $timeout !== '' ? escapeshellcmd($timeout) . ' ' . (int)$seconds . 's ' : '';
    }
}

if (!function_exists('local_aiskillnavigator_pdf_filesize')) {
    function local_aiskillnavigator_pdf_filesize(string $pdfpath): int {
        return is_readable($pdfpath) ? (int)@filesize($pdfpath) : 0;
    }
}

if (!function_exists('local_aiskillnavigator_pdf_large_threshold')) {
    function local_aiskillnavigator_pdf_large_threshold(): int {
        $configured = (int)get_config('local_aiskillnavigator', 'largefilethresholdbytes');
        if ($configured > 0) {
            return max(5 * 1024 * 1024, $configured);
        }
        return 25 * 1024 * 1024;
    }
}

if (!function_exists('local_aiskillnavigator_pdf_is_large')) {
    function local_aiskillnavigator_pdf_is_large(string $pdfpath): bool {
        $size = local_aiskillnavigator_pdf_filesize($pdfpath);
        return $size > 0 && $size >= local_aiskillnavigator_pdf_large_threshold();
    }
}

if (!function_exists('local_aiskillnavigator_pdf_text_layer')) {
    function local_aiskillnavigator_pdf_text_layer(string $pdfpath): string {
        $pdftotext = local_aiskillnavigator_pdf_tool_path('pdftotext');

        if ($pdftotext === '') {
            return '';
        }

        $seconds = local_aiskillnavigator_pdf_is_large($pdfpath) ? 35 : 60;
        $cmd = local_aiskillnavigator_pdf_timeout_prefix($seconds)
            . escapeshellcmd($pdftotext)
            . ' -enc UTF-8 -layout '
            . escapeshellarg($pdfpath)
            . ' - 2>/dev/null';

        return local_aiskillnavigator_pdf_clean_text((string)@shell_exec($cmd));
    }
}

if (!function_exists('local_aiskillnavigator_pdf_page_count')) {
    function local_aiskillnavigator_pdf_page_count(string $pdfpath): int {
        $pdfinfo = local_aiskillnavigator_pdf_tool_path('pdfinfo');

        if ($pdfinfo === '') {
            return 0;
        }

        $cmd = local_aiskillnavigator_pdf_timeout_prefix(10)
            . escapeshellcmd($pdfinfo) . ' ' . escapeshellarg($pdfpath) . ' 2>/dev/null';
        $out = (string)@shell_exec($cmd);

        if (preg_match('/Pages:\s+([0-9]+)/i', $out, $m)) {
            return (int)$m[1];
        }

        return 0;
    }
}

if (!function_exists('local_aiskillnavigator_pdf_ocr_allowed')) {
    function local_aiskillnavigator_pdf_ocr_allowed(string $pdfpath): bool {
        if (function_exists('local_aisn_ocr_enabled') && !local_aisn_ocr_enabled()) {
            return false;
        }

        if (local_aiskillnavigator_pdf_is_large($pdfpath)) {
            return false;
        }

        $pages = local_aiskillnavigator_pdf_page_count($pdfpath);
        $maxpages = (int)get_config('local_aiskillnavigator', 'pdfocrmaxpages');
        if ($maxpages <= 0) {
            $maxpages = 12;
        }

        return $pages <= 0 || $pages <= $maxpages;
    }
}

if (!function_exists('local_aiskillnavigator_pdf_ocr')) {
    function local_aiskillnavigator_pdf_ocr(string $pdfpath): string {
        if (!local_aiskillnavigator_pdf_ocr_allowed($pdfpath)) {
            return '';
        }

        $pdftoppm = local_aiskillnavigator_pdf_tool_path('pdftoppm');
        $tesseract = local_aiskillnavigator_pdf_tool_path('tesseract');

        if ($pdftoppm === '' || $tesseract === '') {
            return '';
        }

        $tmpdir = make_temp_directory('local_aiskillnavigator/pdf_ocr_' . uniqid('', true));
        $prefix = $tmpdir . '/page';

        $pages = local_aiskillnavigator_pdf_page_count($pdfpath);
        $maxpages = (int)get_config('local_aiskillnavigator', 'pdfocrmaxpages');
        if ($maxpages <= 0) {
            $maxpages = 12;
        }
        $lastpage = $pages > 0 ? min($pages, $maxpages) : $maxpages;

        $rendercmd = local_aiskillnavigator_pdf_timeout_prefix(35)
            . escapeshellcmd($pdftoppm)
            . ' -r 150 -png -f 1 -l ' . (int)$lastpage . ' '
            . escapeshellarg($pdfpath)
            . ' '
            . escapeshellarg($prefix)
            . ' 2>/dev/null';

        @shell_exec($rendercmd);

        $images = glob($tmpdir . '/page-*.png') ?: [];
        sort($images, SORT_NATURAL);

        $parts = [];
        $lang = function_exists('get_config') ? trim((string)get_config('local_aiskillnavigator', 'ocrlanguages')) : '';
        if ($lang === '') {
            $lang = 'ita+eng';
        }

        foreach ($images as $image) {
            $ocrcmd = local_aiskillnavigator_pdf_timeout_prefix(20)
                . escapeshellcmd($tesseract)
                . ' '
                . escapeshellarg($image)
                . ' stdout -l '
                . escapeshellarg($lang)
                . ' --psm 6 2>/dev/null';

            $txt = local_aiskillnavigator_pdf_clean_text((string)@shell_exec($ocrcmd));

            if ($txt !== '') {
                $parts[] = $txt;
            }

            @unlink($image);
        }

        @rmdir($tmpdir);

        return local_aiskillnavigator_pdf_clean_text(implode("\n\n", $parts));
    }
}

if (!function_exists('local_aiskillnavigator_extract_pdf_text_from_path')) {
    function local_aiskillnavigator_extract_pdf_text_from_path(string $pdfpath, string $filename = ''): string {
        if ($pdfpath === '' || !is_readable($pdfpath)) {
            return '';
        }

        // Always prefer fast text-layer extraction. This is the PDF -> TXT path.
        $textlayer = local_aiskillnavigator_pdf_text_layer($pdfpath);

        if (core_text::strlen($textlayer) >= 80) {
            return $textlayer;
        }

        // Large PDFs are never OCRed during Course Builder/material sync: they stay fast.
        if (local_aiskillnavigator_pdf_is_large($pdfpath)) {
            return $textlayer;
        }

        $ocrtext = local_aiskillnavigator_pdf_ocr($pdfpath);

        if ($ocrtext !== '') {
            if ($textlayer !== '') {
                return local_aiskillnavigator_pdf_clean_text($textlayer . "\n\n[OCR fallback]\n" . $ocrtext);
            }

            return $ocrtext;
        }

        return $textlayer;
    }
}
