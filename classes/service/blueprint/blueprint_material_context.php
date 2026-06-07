<?php

namespace local_aiskillnavigator\service\blueprint;

defined('MOODLE_INTERNAL') || die();

// Formats course materials for XR blueprint prompts.
class blueprint_material_context {
    public function build(array $materials, int $limit): string {
        $context = '';
        $seen = [];
        $source = 1;

        foreach ($materials as $material) {
            $content = trim((string) ($material->content ?? ''));
            $content = trim((string) preg_replace('/\s+/u', ' ', $content));

            if ($content === '') {
                continue;
            }

            $title = trim((string) ($material->title ?? 'Materiale senza titolo'));
            $type = trim((string) ($material->materialtype ?? 'text'));
            $key = $this->identity_key($material, $title, $type, $content);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($content, 'UTF-8') > $limit) {
                $content = mb_substr($content, 0, $limit, 'UTF-8') . '...';
            } else if (strlen($content) > $limit) {
                $content = substr($content, 0, $limit) . '...';
            }

            $context .= 'FONTE ' . $source . "\n"
                . 'Titolo: ' . $title . "\n"
                . 'Tipo: ' . $type . "\n"
                . "Contenuto: {$content}\n\n";

            $source++;
        }

        return trim($context);
    }

    private function identity_key(\stdClass $material, string $title, string $type, string $content): string {
        if (!empty($material->id)) {
            return 'id:' . (int) $material->id;
        }

        if (preg_match('/cm\s*#\s*([0-9]+)\s*\]/i', $title, $matches)) {
            return 'cm:' . (int) $matches[1];
        }

        $normalised = trim((string) preg_replace('/\s+/u', ' ', $title . "\n" . $content));

        if (class_exists('\core_text')) {
            $normalised = \core_text::strtolower($normalised);
        } else {
            $normalised = strtolower($normalised);
        }

        return strtolower($type) . ':' . md5($normalised);
    }
}
