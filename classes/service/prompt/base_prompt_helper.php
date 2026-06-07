<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
abstract class base_prompt_helper {
    private text_tools $text;
    private material_context_builder $materials;
    private style_notes $style;

    public function __construct() {
        $this->text = new text_tools();
        $this->materials = new material_context_builder($this->text);
        $this->style = new style_notes();
    }

    protected function default_if_empty(string $value, string $default): string {
        return $this->text->fallback($value, $default);
    }

    protected function material_context(array $materials, int $limit): string {
        return $this->materials->build($materials, $limit);
    }

    protected function plain_style_rules(): string {
        return $this->style->plain();
    }
}
