<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Turns search results into a compact prompt context.
class rag_context_builder {
    public function build(array $results, int $maxchars = 6000): string {
        $context = '';
        $total = 0;
        $source = 1;
        $seen = [];

        foreach ($results as $result) {
            $text = trim((string) ($result->chunktext ?? ''));

            if ($text === '') {
                continue;
            }

            $title = trim((string) ($result->title ?? 'Materiale'));
            $key = $this->identity_key($result, $title, $text);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $score = (string) ($result->similarity ?? 'n/a');
            $block = "FONTE {$source} (materiale: {$title}, rilevanza: {$score})\n{$text}\n\n";
            $length = \core_text::strlen($block);

            if ($total + $length > $maxchars) {
                $remaining = $maxchars - $total;
                if ($remaining > 250) {
                    $context .= \core_text::substr($block, 0, $remaining) . "...\n\n";
                }
                break;
            }

            $context .= $block;
            $total += $length;
            $source++;
        }

        return trim($context);
    }

    private function identity_key(\stdClass $result, string $title, string $text): string {
        if (!empty($result->materialid) && isset($result->chunkindex)) {
            return 'material-chunk:' . (int) $result->materialid . ':' . (int) $result->chunkindex;
        }

        $normalised = trim((string) preg_replace('/\s+/u', ' ', $title . "\n" . $text));

        if (class_exists('\core_text')) {
            $normalised = \core_text::strtolower($normalised);
        } else {
            $normalised = strtolower($normalised);
        }

        return md5($normalised);
    }
}
