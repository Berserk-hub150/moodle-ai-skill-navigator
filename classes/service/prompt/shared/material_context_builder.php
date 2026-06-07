<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();

// Formats uploaded Moodle materials for prompt text.
class material_context_builder {
    private text_tools $text;

    public function __construct(text_tools $text) {
        $this->text = $text;
    }

    public function build(array $materials, int $limit): string {
        $context = '';
        $seen = [];
        $source = 1;

        foreach ($materials as $material) {
            $title = $this->read($material, 'title', 'Materiale senza titolo');
            $type = $this->read($material, 'materialtype', 'text');
            $content = $this->text->clean($this->read($material, 'content', ''));

            if ($content === '') {
                continue;
            }

            $key = $this->identity_key($material, $title, $type, $content);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $context .= "Fonte {$source}\n"
                . 'Titolo: ' . $title . "\n"
                . 'Tipo: ' . $type . "\n"
                . 'Testo: ' . $this->text->cut($content, $limit) . "\n\n";

            $source++;
        }

        return trim($context);
    }

    private function read($material, string $field, string $default): string {
        if (is_array($material) && array_key_exists($field, $material)) {
            return trim((string) $material[$field]);
        }

        if (is_object($material) && isset($material->{$field})) {
            return trim((string) $material->{$field});
        }

        return $default;
    }

    private function identity_key($material, string $title, string $type, string $content): string {
        $id = $this->read($material, 'id', '');

        if ($id !== '') {
            return 'id:' . $id;
        }

        if (preg_match('/cm\s*#\s*([0-9]+)\s*\]/i', $title, $matches)) {
            return 'cm:' . (int) $matches[1];
        }

        return strtolower($type) . ':' . md5($this->normalise($title) . "\n" . $this->normalise($content));
    }

    private function normalise(string $value): string {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (class_exists('\core_text')) {
            return \core_text::strtolower($value);
        }

        return strtolower($value);
    }
}
