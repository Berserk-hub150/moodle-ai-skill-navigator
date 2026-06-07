<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Splits text by length when no better boundary exists.
class length_chunker {
    public const SIZE = 2000;
    public const OVERLAP = 300;

    public function split(string $text): array {
        $chunks = [];
        $length = \core_text::strlen($text);
        $start = 0;

        while ($start < $length) {
            $chunks[] = trim(\core_text::substr($text, $start, self::SIZE));
            $start += self::SIZE - self::OVERLAP;
        }

        return array_values(array_filter($chunks));
    }
}
