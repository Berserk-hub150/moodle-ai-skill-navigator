<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/prototype_service/*.php') as $file) {
    require_once($file);
}

// Older demo service kept for compatibility.
class prototype_ai_service {
    public function answer_question(string $question): array {
        return (new prototype_service\prototype_answer_demo())->get($question);
    }

    public function generate_quiz(string $topic, string $difficulty): array {
        return (new prototype_service\prototype_quiz_demo())->get($topic, $difficulty);
    }

    public function generate_xr_scenario(string $topic, string $environment): array {
        return (new prototype_service\prototype_xr_demo())->get($topic, $environment);
    }
}
