<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Scores text using shared words.
class keyword_similarity {
    public function score(string $query, string $text): float {
        $querywords = $this->words($query);
        $textwords = $this->words($text);

        if (empty($querywords) || empty($textwords)) {
            return 0.0;
        }

        $intersection = count(array_intersect($querywords, $textwords));
        $union = count(array_unique(array_merge($querywords, $textwords)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function words(string $text): array {
        $text = \core_text::strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', (string) $text);

        return array_values(array_filter($words, function ($word) {
            return \core_text::strlen($word) > 2;
        }));
    }
}
