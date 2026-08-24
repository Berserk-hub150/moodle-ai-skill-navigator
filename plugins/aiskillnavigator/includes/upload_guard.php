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

/**
 * Local aisn upload allowed extensions helper.
 */
function local_aisn_upload_allowed_extensions(): array {
    return [
        'txt', 'md', 'csv', 'json', 'xml', 'html', 'htm',
        'css', 'js', 'ts', 'sql', 'cs', 'java', 'py', 'cpp', 'c',
        'pptx', 'docx', 'pdf', 'png', 'jpg', 'jpeg', 'bmp', 'tif', 'tiff', 'webp',
    ];
}

/**
 * Local aisn upload max bytes helper.
 */
function local_aisn_upload_max_bytes(): int {
    $configured = (int)get_config('local_aiskillnavigator', 'maxuploadbytes');

    if ($configured > 0) {
        return $configured;
    }

    return 25 * 1024 * 1024;
}

/**
 * Local aisn upload error message helper.
 */
function local_aisn_upload_error_message(int $error): string {
    switch ($error) {
        case UPLOAD_ERR_OK:
            return 'Upload OK.';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Uploaded file is too large.';
        case UPLOAD_ERR_PARTIAL:
            return 'Uploaded file was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary upload directory.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Cannot write uploaded file.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload blocked by a PHP extension.';
        default:
            return 'Unknown upload error.';
    }
}

/**
 * Local aisn upload validate uploaded file helper.
 */
function local_aisn_upload_validate_uploaded_file(array $file, bool $mustbehttpupload = true): array {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'message' => local_aisn_upload_error_message($error),
            'file' => null,
        ];
    }

    $tmp = (string)($file['tmp_name'] ?? '');

    if ($tmp === '' || !file_exists($tmp) || !is_readable($tmp)) {
        return [
            'ok' => false,
            'message' => 'Uploaded file temporary path is not readable.',
            'file' => null,
        ];
    }

    if ($mustbehttpupload && !defined('CLI_SCRIPT') && !is_uploaded_file($tmp)) {
        return [
            'ok' => false,
            'message' => 'Invalid uploaded file source.',
            'file' => null,
        ];
    }

    $originalname = (string)($file['name'] ?? '');
    $cleanname = clean_param($originalname, PARAM_FILE);

    if ($cleanname === '') {
        $cleanname = 'teacher-material';
    }

    $extension = strtolower(pathinfo($cleanname, PATHINFO_EXTENSION));

    if ($extension === '') {
        $extension = strtolower(pathinfo($originalname, PATHINFO_EXTENSION));
    }

    if ($extension === '' || !in_array($extension, local_aisn_upload_allowed_extensions(), true)) {
        return [
            'ok' => false,
            'message' => 'Unsupported file type. Allowed: ' . implode(', ', local_aisn_upload_allowed_extensions()) . '.',
            'file' => null,
        ];
    }

    $size = (int)($file['size'] ?? 0);

    if ($size <= 0 && file_exists($tmp)) {
        $size = (int)filesize($tmp);
    }

    if ($size <= 0) {
        return [
            'ok' => false,
            'message' => 'Uploaded file is empty.',
            'file' => null,
        ];
    }

    $maxbytes = local_aisn_upload_max_bytes();

    if ($size > $maxbytes) {
        return [
            'ok' => false,
            'message' => 'Uploaded file is too large. Max allowed: ' . display_size($maxbytes) . '.',
            'file' => null,
        ];
    }

    return [
        'ok' => true,
        'message' => 'Upload validated.',
        'file' => [
            'name' => $cleanname,
            'type' => (string)($file['type'] ?? ''),
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => $size,
            'extension' => $extension,
        ],
    ];
}
