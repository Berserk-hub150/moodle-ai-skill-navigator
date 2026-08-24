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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aiskillnavigator_pdf_tool_path')) {
    /**
     * Local aiskillnavigator pdf tool path helper.
     */
    function local_aiskillnavigator_pdf_tool_path(string $tool): string {
        $tool = preg_replace('/[^a-zA-Z0-9_\-]/', '', $tool);
        return trim((string)@shell_exec('command -v ' . escapeshellarg($tool) . ' 2>/dev/null'));
    }
}

if (!function_exists('local_aiskillnavigator_pdf_clean_text')) {
    /**
     * Local aiskillnavigator pdf clean text helper.
     */
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
    /**
     * Local aiskillnavigator pdf timeout prefix helper.
     */
    function local_aiskillnavigator_pdf_timeout_prefix(int $seconds): string {
        $timeout = local_aiskillnavigator_pdf_tool_path('timeout');
        return $timeout !== '' ? escapeshellcmd($timeout) . ' ' . (int)$seconds . 's ' : '';
    }
}

if (!function_exists('local_aiskillnavigator_pdf_filesize')) {
    /**
     * Local aiskillnavigator pdf filesize helper.
     */
    function local_aiskillnavigator_pdf_filesize(string $pdfpath): int {
        return is_readable($pdfpath) ? (int)@filesize($pdfpath) : 0;
    }
}

if (!function_exists('local_aiskillnavigator_pdf_large_threshold')) {
    /**
     * Local aiskillnavigator pdf large threshold helper.
     */
    function local_aiskillnavigator_pdf_large_threshold(): int {
        $configured = (int)get_config('local_aiskillnavigator', 'largefilethresholdbytes');
        if ($configured > 0) {
            return max(5 * 1024 * 1024, $configured);
        }
        return 25 * 1024 * 1024;
    }
}

if (!function_exists('local_aiskillnavigator_pdf_is_large')) {
    /**
     * Local aiskillnavigator pdf is large helper.
     */
    function local_aiskillnavigator_pdf_is_large(string $pdfpath): bool {
        $size = local_aiskillnavigator_pdf_filesize($pdfpath);
        return $size > 0 && $size >= local_aiskillnavigator_pdf_large_threshold();
    }
}

if (!function_exists('local_aiskillnavigator_pdf_text_layer')) {
    /**
     * Local aiskillnavigator pdf text layer helper.
     */
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
    /**
     * Local aiskillnavigator pdf page count helper.
     */
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
    /**
     * Local aiskillnavigator pdf ocr allowed helper.
     */
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
    /**
     * Local aiskillnavigator pdf ocr helper.
     */
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
    /**
     * Local aiskillnavigator extract pdf text from path helper.
     */
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
