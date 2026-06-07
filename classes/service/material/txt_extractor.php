<?php

namespace local_aiskillnavigator\service\material;

defined('MOODLE_INTERNAL') || die();

// Reads text files uploaded by the teacher.
class txt_extractor {
    public function extract(string $path): array {
        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            return ['success' => false, 'content' => '', 'message' => 'The TXT file is empty or unreadable.', 'type' => 'text'];
        }

        return ['success' => true, 'content' => $this->clean($content), 'message' => 'TXT file extracted successfully.', 'type' => 'text'];
    }

    private function clean(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text);
    }
}
