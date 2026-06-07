<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Splits material text into overlapping chunks.
class paragraph_chunker {
    public function split(string $text): array {
        $text = preg_replace("/\r\n|\r/", "\n", trim($text));

        if ($text === '') {
            return [];
        }

        if (\core_text::strlen($text) <= length_chunker::SIZE) {
            return [$text];
        }

        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = array_values(array_filter(array_map('trim', (array) $paragraphs)));

        if (empty($paragraphs)) {
            return (new sentence_chunker())->split($text);
        }

        return $this->merge($paragraphs);
    }

    private function merge(array $paragraphs): array {
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $tooBig = $current !== '' && \core_text::strlen($current . $paragraph) > length_chunker::SIZE;
            if ($tooBig) {
                $chunks[] = trim($current);
                $current = trim(\core_text::substr($current, -length_chunker::OVERLAP)) . "\n\n" . $paragraph;
            } else {
                $current .= ($current !== '' ? "\n\n" : '') . $paragraph;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
