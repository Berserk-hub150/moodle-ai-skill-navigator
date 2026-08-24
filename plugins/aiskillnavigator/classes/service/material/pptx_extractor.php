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

namespace local_aiskillnavigator\service\material;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Reads slide text from PPTX files.
/**
 * Pptx extractor implementation.
 */
class pptx_extractor {
    /**
     * Extract helper.
     */
    public function extract(string $path): array {
        if (!class_exists('\ZipArchive')) {
            // phpcs:ignore moodle.Files.LineLength
            return ['success' => false, 'content' => '', 'message' => 'PHP ZipArchive is not available. PPTX extraction cannot run.', 'type' => 'slide'];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['success' => false, 'content' => '', 'message' => 'Unable to open PPTX file.', 'type' => 'slide'];
        }

        $slides = [];
        $reader = new slide_xml_reader();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (!preg_match('#^ppt/slides/slide([0-9]+)\.xml$#', $entry, $matches)) {
                continue;
            }
            $xml = $zip->getFromName($entry);
            $text = $xml === false ? '' : $reader->text($xml);
            if ($text !== '') {
                $slides[(int) $matches[1]] = "Slide {$matches[1]}:\n" . $text;
            }
        }

        $zip->close();
        if (empty($slides)) {
            // phpcs:ignore moodle.Files.LineLength
            return ['success' => false, 'content' => '', 'message' => 'No readable text found in the PPTX slides.', 'type' => 'slide'];
        }

        ksort($slides);
        // phpcs:ignore moodle.Files.LineLength
        return ['success' => true, 'content' => trim(implode("\n\n", $slides)), 'message' => 'PPTX slides extracted successfully.', 'type' => 'slide'];
    }
}
