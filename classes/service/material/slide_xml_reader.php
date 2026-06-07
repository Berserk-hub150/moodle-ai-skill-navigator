<?php

namespace local_aiskillnavigator\service\material;

defined('MOODLE_INTERNAL') || die();

// Extracts visible text from one PowerPoint slide XML file.
class slide_xml_reader {
    public function text(string $xml): string {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();

        if (!$dom->loadXML($xml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return '';
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $nodes = $xpath->query('//a:t');
        $parts = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $value = trim($node->textContent);
                if ($value !== '') { $parts[] = $value; }
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return trim((string) preg_replace("/\s+/u", " ", implode("\n", $parts)));
    }
}
