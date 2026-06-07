<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Adaptive learning helper.
 *
 * This is a lightweight mastery-estimation model:
 * - groups answers by ability/skill;
 * - weighs recent evidence slightly more than old evidence;
 * - uses Bayesian smoothing to avoid unstable percentages with few answers;
 * - collects wrong examples so the LLM can generate similar-but-not-identical practice.
 */

function local_aiskillnavigator_adaptive_table_exists(string $tablename): bool {
    global $DB;

    try {
        return $DB->get_manager()->table_exists(new xmldb_table($tablename));
    } catch (Throwable $e) {
        debugging('AI Skill Navigator adaptive table check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

function local_aiskillnavigator_adaptive_question_skill(array $question): string {
    $skill = trim((string)($question['ability'] ?? $question['Ability'] ?? $question['skill'] ?? $question['Skill'] ?? $question['topic'] ?? ''));

    if ($skill === '') {
        $skill = 'General understanding';
    }

    return $skill;
}

function local_aiskillnavigator_adaptive_question_text(array $question): string {
    return trim((string)($question['question'] ?? $question['text'] ?? $question['title'] ?? ''));
}

function local_aiskillnavigator_adaptive_option_text(array $question, int $index): string {
    $options = $question['options'] ?? $question['answers'] ?? [];

    if (!is_array($options) || !array_key_exists($index, $options)) {
        return '';
    }

    return trim((string)$options[$index]);
}

function local_aiskillnavigator_adaptive_register_answer(
    array &$skills,
    array &$wrongquestions,
    string $skill,
    bool $iscorrect,
    string $questiontext,
    string $studentanswer,
    string $correctanswer,
    int $timecreated
): void {
    $skill = trim($skill) !== '' ? trim($skill) : 'General understanding';

    if (!isset($skills[$skill])) {
        $skills[$skill] = [
            'Ability' => $skill,
            'skill' => $skill,
            'total' => 0,
            'correct' => 0,
            'wrong' => 0,
            'weighted_total' => 0.0,
            'weighted_correct' => 0.0,
            'mastery' => 0.0,
            'risk' => 'unknown',
            'wrong_examples' => [],
        ];
    }

    $age = $timecreated > 0 ? max(0, time() - $timecreated) : 0;
    $days = $age / 86400;
    $weight = max(0.35, min(1.0, 1.0 / (1.0 + ($days / 45.0))));

    $skills[$skill]['total']++;
    $skills[$skill]['weighted_total'] += $weight;

    if ($iscorrect) {
        $skills[$skill]['correct']++;
        $skills[$skill]['weighted_correct'] += $weight;
        return;
    }

    $skills[$skill]['wrong']++;

    $example = [
        'ability' => $skill,
        'question' => $questiontext,
        'student_answer' => $studentanswer,
        'correct_answer' => $correctanswer,
        'timecreated' => $timecreated,
    ];

    if (count($skills[$skill]['wrong_examples']) < 5) {
        $skills[$skill]['wrong_examples'][] = $example;
    }

    if (count($wrongquestions) < 20) {
        $wrongquestions[] = $example;
    }
}

function local_aiskillnavigator_adaptive_collect_student(int $courseid, int $userid): array {
    global $DB;

    $skills = [];
    $wrongquestions = [];

    if (
        local_aiskillnavigator_adaptive_table_exists('local_aiskillnav_assessment') &&
        local_aiskillnavigator_adaptive_table_exists('local_aiskillnav_ass_att')
    ) {
        $attempts = $DB->get_records(
            'local_aiskillnav_ass_att',
            ['courseid' => $courseid, 'userid' => $userid],
            'timecreated ASC'
        );

        foreach ($attempts as $attempt) {
            $assessment = $DB->get_record(
                'local_aiskillnav_assessment',
                ['id' => (int)$attempt->assessmentid, 'courseid' => $courseid]
            );

            if (!$assessment) {
                continue;
            }

            $quiz = json_decode((string)$assessment->quizjson, true);
            $answers = json_decode((string)$attempt->answersjson, true);

            if (!is_array($quiz) || empty($quiz['questions']) || !is_array($quiz['questions'])) {
                continue;
            }

            if (!is_array($answers)) {
                $answers = [];
            }

            $questions = array_values($quiz['questions']);

            foreach ($questions as $index => $question) {
                if (!is_array($question)) {
                    continue;
                }

                $skill = local_aiskillnavigator_adaptive_question_skill($question);
                $correctindex = isset($question['correct_index']) ? (int)$question['correct_index'] : -999;
                $studentindex = isset($answers[$index]) ? (int)$answers[$index] : -998;
                $iscorrect = $studentindex === $correctindex;

                local_aiskillnavigator_adaptive_register_answer(
                    $skills,
                    $wrongquestions,
                    $skill,
                    $iscorrect,
                    local_aiskillnavigator_adaptive_question_text($question),
                    local_aiskillnavigator_adaptive_option_text($question, $studentindex),
                    local_aiskillnavigator_adaptive_option_text($question, $correctindex),
                    (int)$attempt->timecreated
                );
            }
        }
    }

    if (local_aiskillnavigator_adaptive_table_exists('local_aiskillnav_attempt')) {
        $attempts = $DB->get_records(
            'local_aiskillnav_attempt',
            ['courseid' => $courseid, 'userid' => $userid],
            'timecreated ASC'
        );

        foreach ($attempts as $attempt) {
            $quiz = json_decode((string)($attempt->quizjson ?? ''), true);
            $answers = json_decode((string)($attempt->answersjson ?? ''), true);

            if (!is_array($quiz) || empty($quiz['questions']) || !is_array($quiz['questions'])) {
                continue;
            }

            if (!is_array($answers)) {
                $answers = [];
            }

            $questions = array_values($quiz['questions']);

            foreach ($questions as $index => $question) {
                if (!is_array($question)) {
                    continue;
                }

                $skill = local_aiskillnavigator_adaptive_question_skill($question);
                $correctindex = isset($question['correct_index']) ? (int)$question['correct_index'] : -999;
                $studentindex = isset($answers[$index]) ? (int)$answers[$index] : -998;
                $iscorrect = $studentindex === $correctindex;

                local_aiskillnavigator_adaptive_register_answer(
                    $skills,
                    $wrongquestions,
                    $skill,
                    $iscorrect,
                    local_aiskillnavigator_adaptive_question_text($question),
                    local_aiskillnavigator_adaptive_option_text($question, $studentindex),
                    local_aiskillnavigator_adaptive_option_text($question, $correctindex),
                    (int)($attempt->timecreated ?? 0)
                );
            }
        }
    }

    foreach ($skills as $name => $skill) {
        $weightedtotal = (float)$skill['weighted_total'];
        $weightedcorrect = (float)$skill['weighted_correct'];

        $mastery = (($weightedcorrect + 1.0) / ($weightedtotal + 2.0)) * 100.0;

        $skills[$name]['mastery'] = round($mastery, 1);

        if ($mastery < 50 || (int)$skill['wrong'] >= 3) {
            $skills[$name]['risk'] = 'high';
        } else if ($mastery < 70) {
            $skills[$name]['risk'] = 'medium';
        } else {
            $skills[$name]['risk'] = 'low';
        }
    }

    uasort($skills, function(array $a, array $b): int {
        if ($a['mastery'] === $b['mastery']) {
            return $b['wrong'] <=> $a['wrong'];
        }

        return $a['mastery'] <=> $b['mastery'];
    });

    return [
        'skills' => array_values($skills),
        'weakest' => array_slice(array_values($skills), 0, 5),
        'wrongquestions' => $wrongquestions,
    ];
}

function local_aiskillnavigator_adaptive_prompt_context(array $profile): string {
    $lines = [];

    $lines[] = 'Profilo adattivo dello studente basato su test iniziali, test finali e quiz.';
    $lines[] = 'Il valore mastery è una stima pesata: errori recenti contano più degli errori vecchi.';
    $lines[] = '';

    if (empty($profile['skills'])) {
        return implode("\n", $lines) . "\nNessun dato disponibile.";
    }

    $lines[] = 'ABILITÀ STIMATE:';

    foreach ($profile['skills'] as $skill) {
        $lines[] = '- ' . $skill['Ability']
            . ': mastery ' . $skill['mastery'] . '%'
            . ', corrette ' . $skill['correct']
            . ', sbagliate ' . $skill['wrong']
            . ', rischio ' . $skill['risk'];
    }

    if (!empty($profile['wrongquestions'])) {
        $lines[] = '';
        $lines[] = 'ERRORI DA USARE PER RECUPERO ADATTIVO:';

        foreach (array_slice($profile['wrongquestions'], 0, 12) as $wrong) {
            $lines[] = '- Abilità: ' . $wrong['ability'];
            if ($wrong['question'] !== '') {
                $lines[] = '  Domanda sbagliata: ' . $wrong['question'];
            }
            if ($wrong['student_answer'] !== '') {
                $lines[] = '  Risposta dello studente: ' . $wrong['student_answer'];
            }
            if ($wrong['correct_answer'] !== '') {
                $lines[] = '  Risposta corretta: ' . $wrong['correct_answer'];
            }
        }
    }

    $lines[] = '';
    $lines[] = 'ISTRUZIONI PER LLM:';
    $lines[] = '- Spiega le lacune in modo semplice.';
    $lines[] = '- Genera domande simili ma NON identiche a quelle sbagliate.';
    $lines[] = '- Ripeti i concetti più deboli con esempi diversi.';
    $lines[] = '- Dai priorità alle abilità con mastery più bassa o più errori.';
    $lines[] = '- Non inventare dati personali dello studente.';

    return implode("\n", $lines);
}