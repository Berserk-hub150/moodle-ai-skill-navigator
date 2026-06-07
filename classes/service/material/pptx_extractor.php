<?php

namespace local_aiskillnavigator\service\material;

defined('MOODLE_INTERNAL') || die();

// Reads slide text from PPTX files.
class pptx_extractor {
    public function extract(string $path): array {
        if (!class_exists('\ZipArchive')) {
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
            if (!preg_match('#^ppt/slides/slide([0-9]+)\.xml$#', $entry, $matches)) { continue; }
            $xml = $zip->getFromName($entry);
            $text = $xml === false ? '' : $reader->text($xml);
            if ($text !== '') { $slides[(int) $matches[1]] = "Slide {$matches[1]}:\n" . $text; }
        }

        $zip->close();
        if (empty($slides)) {
            return ['success' => false, 'content' => '', 'message' => 'No readable text found in the PPTX slides.', 'type' => 'slide'];
        }

        ksort($slides);
        return ['success' => true, 'content' => trim(implode("\n\n", $slides)), 'message' => 'PPTX slides extracted successfully.', 'type' => 'slide'];
    }
}
