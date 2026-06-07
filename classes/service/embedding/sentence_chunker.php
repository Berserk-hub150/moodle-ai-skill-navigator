<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Splits long text around sentence boundaries.
class sentence_chunker {
    public function split(string $text): array {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text));

        if (!$sentences || count($sentences) <= 1) {
            return (new length_chunker())->split($text);
        }

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $tooBig = $current !== ''
                && \core_text::strlen($current) + \core_text::strlen($sentence) + 1 > length_chunker::SIZE;

            if ($tooBig) {
                $chunks[] = trim($current);
                $overlap = \core_text::substr($current, -length_chunker::OVERLAP);
                $current = trim($overlap . ' ' . $sentence);
            } else {
                $current .= ($current !== '' ? ' ' : '') . $sentence;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
