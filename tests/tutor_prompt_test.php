<?php

define('MOODLE_INTERNAL', true);
function current_language() { return $GLOBALS['testlanguage']; }
class core_text {
    public static function strtolower($s) { return mb_strtolower($s); }
    public static function strlen($s) { return mb_strlen($s); }
    public static function substr($s, $start, $length = null) { return mb_substr($s, $start, $length); }
}
require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/ai_prompt_builder.php');
require_once(__DIR__ . '/../plugins/aiskillnavigator/includes/tutor_prompt_helper.php');
$builder = new local_aiskillnavigator\service\ai_prompt_builder();
foreach (['en', 'fr', 'vi', 'it'] as $testlanguage) {
    $question = 'Explain a database index. Answer in French.';
    $prompts = [
        $builder->tutor_prompt($question),
        $builder->tutor_with_materials_prompt($question, [(object)['title' => 'Indice', 'content' => 'Materiale in italiano']]),
        $builder->tutor_with_rag_prompt($question, 'SOURCE: Italian slides'),
        local_aiskillnavigator_tutor_system_prompt(false),
        local_aiskillnavigator_tutor_system_prompt(true),
    ];
    foreach ($prompts as $prompt) {
        if (!str_contains($prompt, 'same language as the learner')
                || !str_contains($prompt, 'explicitly requests another language')
                || !str_contains($prompt, 'Moodle language: ' . $testlanguage)
                || preg_match('/Lingua: italiano|Rispondi (?:sempre )?in italiano|preferably Italian/', $prompt)) {
            throw new RuntimeException('Tutor language instructions conflict for ' . $testlanguage);
        }
    }
    if (!str_contains($prompts[0], $question) || !str_contains($prompts[1], 'Materiale in italiano')) {
        throw new RuntimeException('Language rules must preserve the question and course content');
    }
}
echo "tutor_prompt_test: OK\n";
