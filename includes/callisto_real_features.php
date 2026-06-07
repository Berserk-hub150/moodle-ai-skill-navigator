<?php

defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aiskillnavigator_call_ai_inline')) {
    function local_aiskillnavigator_call_ai_inline(string $prompt, string $systemprompt = '', int $maxtokens = 2600): string {
        try {
            if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
                $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
                return $provider->generate($prompt, $maxtokens, $systemprompt);
            }
        } catch (Throwable $e) {
            return 'AI generation error: ' . $e->getMessage();
        }

        return 'AI provider not available. Configure the provider from plugin settings.';
    }
}

if (!function_exists('local_aiskillnavigator_collect_gap_data')) {
    function local_aiskillnavigator_collect_gap_data(int $courseid): array {
        global $DB;

        $dbman = $DB->get_manager();

        if (
            !$dbman->table_exists(new xmldb_table('local_aiskillnav_assessment')) ||
            !$dbman->table_exists(new xmldb_table('local_aiskillnav_ass_att'))
        ) {
            return ['summary' => [], 'skills' => []];
        }

        $assessments = $DB->get_records(
            'local_aiskillnav_assessment',
            ['courseid' => $courseid],
            'timecreated ASC'
        );

        $summary = [];
        $skills = [];

        foreach ($assessments as $assessment) {
            $attempts = $DB->get_records(
                'local_aiskillnav_ass_att',
                ['assessmentid' => $assessment->id]
            );

            $quiz = json_decode((string) $assessment->quizjson, true);
            $questions = is_array($quiz) && !empty($quiz['questions']) && is_array($quiz['questions'])
                ? $quiz['questions']
                : [];

            $count = count($attempts);
            $sum = 0;

            foreach ($attempts as $attempt) {
                $sum += (int) $attempt->percentage;
                $answers = json_decode((string) $attempt->answersjson, true);

                if (!is_array($answers)) {
                    $answers = [];
                }

                foreach ($questions as $index => $question) {
                    $skill = trim((string) ($question['skill'] ?? 'General understanding'));
                    $correctindex = isset($question['correct_index']) ? (int) $question['correct_index'] : -999;
                    $answer = isset($answers[$index]) ? (int) $answers[$index] : -998;

                    if (!isset($skills[$skill])) {
                        $skills[$skill] = [
                            'total' => 0,
                            'correct' => 0,
                            'wrong' => 0,
                        ];
                    }

                    $skills[$skill]['total']++;

                    if ($answer === $correctindex) {
                        $skills[$skill]['correct']++;
                    } else {
                        $skills[$skill]['wrong']++;
                    }
                }
            }

            $summary[] = [
                'title' => $assessment->title,
                'type' => $assessment->assessmenttype,
                'attempts' => $count,
                'average' => $count > 0 ? round($sum / $count, 1) : 0,
            ];
        }

        uasort($skills, function($a, $b) {
            $arate = $a['total'] > 0 ? $a['correct'] / $a['total'] : 0;
            $brate = $b['total'] > 0 ? $b['correct'] / $b['total'] : 0;
            return $arate <=> $brate;
        });

        return [
            'summary' => $summary,
            'skills' => $skills,
        ];
    }
}

if (!function_exists('local_aiskillnavigator_render_gap_panel')) {
    function local_aiskillnavigator_render_gap_panel(int $courseid): void {
        $action = optional_param('callisto_action', '', PARAM_ALPHANUMEXT);
        $airesult = '';
        $data = local_aiskillnavigator_collect_gap_data($courseid);

        if ($action === 'gap_analysis') {
            require_sesskey();

            $lines = [];
            $lines[] = 'Course id: ' . $courseid;
            $lines[] = 'Assessment summary:';

            foreach ($data['summary'] as $item) {
                $lines[] = '- ' . $item['title'] . ' [' . $item['type'] . '], attempts: ' . $item['attempts'] . ', average: ' . $item['average'] . '%';
            }

            $lines[] = 'Skill-level results:';

            foreach ($data['skills'] as $skill => $stats) {
                $rate = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100, 1) : 0;
                $lines[] = '- ' . $skill . ': correct ' . $rate . '%, wrong answers ' . $stats['wrong'] . ', total answers ' . $stats['total'];
            }

            $prompt = "Analizza questi risultati Moodle di pre-test e test finale e individua le lacune degli studenti.\n\n";
            $prompt .= implode("\n", $lines);
            $prompt .= "\n\nRispondi in italiano con queste sezioni:\n";
            $prompt .= "1. Sintesi della classe\n";
            $prompt .= "2. Lacune principali\n";
            $prompt .= "3. Concetti da ripassare\n";
            $prompt .= "4. Azioni didattiche consigliate al docente\n";
            $prompt .= "5. Mini-quiz di recupero con 5 domande\n";
            $prompt .= "Non inventare dati personali non presenti nei risultati.\n";

            $airesult = local_aiskillnavigator_call_ai_inline(
                $prompt,
                'You are an educational analytics assistant for a Moodle teacher. Do not invent unavailable personal data.',
                2800
            );
        }

        echo html_writer::start_div('card mt-4 mb-4');
        echo html_writer::start_div('card-body');

        echo html_writer::tag('h3', 'AI learning-gap analysis');
        echo html_writer::tag(
            'p',
            'This tool uses initial and final test attempts to identify weak skills and suggest teacher-side remediation actions.',
            ['class' => 'text-muted']
        );

        if (empty($data['summary'])) {
            echo html_writer::div(
                'No pre-test/final-test attempts found yet. Generate an initial/final assessment and let students submit answers first.',
                'alert alert-info'
            );
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
            echo html_writer::tag(
                'tr',
                html_writer::tag('th', 'Assessment') .
                html_writer::tag('th', 'Type') .
                html_writer::tag('th', 'Attempts') .
                html_writer::tag('th', 'Average')
            );

            foreach ($data['summary'] as $item) {
                echo html_writer::tag(
                    'tr',
                    html_writer::tag('td', s($item['title'])) .
                    html_writer::tag('td', s($item['type'])) .
                    html_writer::tag('td', (int) $item['attempts']) .
                    html_writer::tag('td', s($item['average'] . '%'))
                );
            }

            echo html_writer::end_tag('table');

            if (!empty($data['skills'])) {
                echo html_writer::tag('h4', 'Weak skills detected');
                echo html_writer::start_tag('table', ['class' => 'table table-sm table-bordered']);
                echo html_writer::tag(
                    'tr',
                    html_writer::tag('th', 'Skill') .
                    html_writer::tag('th', 'Correct') .
                    html_writer::tag('th', 'Wrong') .
                    html_writer::tag('th', 'Correct %')
                );

                foreach ($data['skills'] as $skill => $stats) {
                    $rate = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100, 1) : 0;

                    echo html_writer::tag(
                        'tr',
                        html_writer::tag('td', s($skill)) .
                        html_writer::tag('td', (int) $stats['correct']) .
                        html_writer::tag('td', (int) $stats['wrong']) .
                        html_writer::tag('td', s($rate . '%'))
                    );
                }

                echo html_writer::end_tag('table');
            }

            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]),
            ]);

            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'callisto_action', 'value' => 'gap_analysis']);
            echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => 'Ask AI to analyze learning gaps']);
            echo html_writer::end_tag('form');
        }

        if ($airesult !== '') {
            echo html_writer::tag('h4', 'AI teacher recommendation', ['class' => 'mt-4']);
            echo html_writer::tag('pre', s($airesult), [
                'style' => 'white-space: pre-wrap; background:#f8f9fa; padding:16px; border-radius:12px;',
            ]);
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

if (!function_exists('local_aiskillnavigator_render_course_builder_panel')) {
    function local_aiskillnavigator_render_course_builder_panel(int $courseid): void {
        $action = optional_param('callisto_action', '', PARAM_ALPHANUMEXT);
        $result = '';

        if ($action === 'site_to_course') {
            require_sesskey();

            $title = required_param('site_title', PARAM_TEXT);
            $url = optional_param('site_url', '', PARAM_RAW_TRIMMED);
            $content = required_param('site_content', PARAM_RAW_TRIMMED);

            $prompt = "Trasforma il seguente sito/materiale in un percorso didattico Moodle.\n\n";
            $prompt .= "Titolo: " . $title . "\n";
            $prompt .= "URL sorgente: " . $url . "\n\n";
            $prompt .= "Contenuto:\n" . $content . "\n\n";
            $prompt .= "Restituisci in italiano con questa struttura:\n";
            $prompt .= "1. Obiettivi formativi\n";
            $prompt .= "2. Prerequisiti\n";
            $prompt .= "3. Struttura del corso Moodle in sezioni\n";
            $prompt .= "4. Attività Moodle consigliate\n";
            $prompt .= "5. Quiz iniziale diagnostico con 5 domande\n";
            $prompt .= "6. Test finale con 5 domande\n";
            $prompt .= "7. Possibili lacune degli studenti\n";
            $prompt .= "8. Suggerimenti RAG/privacy\n";
            $prompt .= "9. Simulatori online o strumenti esterni che potrebbero integrare il corso, senza inventare link specifici se non sono nel testo.\n";

            $result = local_aiskillnavigator_call_ai_inline(
                $prompt,
                'You are an instructional designer creating Moodle course structures from teacher-provided material.',
                3200
            );
        }

        echo html_writer::start_div('card mt-4 mb-4');
        echo html_writer::start_div('card-body');

        echo html_writer::tag('h3', 'AI course builder from website/material');
        echo html_writer::tag(
            'p',
            'Paste website text or external material and let the AI transform it into a Moodle course plan with pre-test, final test, activities and possible simulators.',
            ['class' => 'text-muted']
        );

        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/aiskillnavigator/pages/teacher.php', ['courseid' => $courseid]),
        ]);

        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'callisto_action', 'value' => 'site_to_course']);

        echo html_writer::tag('label', 'Title', ['for' => 'site_title']);
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'site_title',
            'id' => 'site_title',
            'class' => 'form-control mb-3',
            'required' => 'required',
        ]);

        echo html_writer::tag('label', 'Source URL optional', ['for' => 'site_url']);
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'site_url',
            'id' => 'site_url',
            'class' => 'form-control mb-3',
        ]);

        echo html_writer::tag('label', 'Website/material content', ['for' => 'site_content']);
        echo html_writer::tag('textarea', '', [
            'name' => 'site_content',
            'id' => 'site_content',
            'class' => 'form-control mb-3',
            'rows' => 10,
            'required' => 'required',
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'value' => 'Generate Moodle course plan',
        ]);

        echo html_writer::end_tag('form');

        if ($result !== '') {
            echo html_writer::tag('h4', 'Generated Moodle course plan', ['class' => 'mt-4']);
            echo html_writer::tag('pre', s($result), [
                'style' => 'white-space: pre-wrap; background:#f8f9fa; padding:16px; border-radius:12px;',
            ]);
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

if (!function_exists('local_aiskillnavigator_render_external_baseline_panel')) {
    function local_aiskillnavigator_render_external_baseline_panel(): void {
        $rows = [
            ['Moodle Quiz', 'Native Moodle quiz/question bank.', 'The plugin adds AI generation from teacher materials and pre/post diagnostic workflow.'],
            ['H5P', 'Interactive learning objects.', 'The plugin focuses on RAG, teacher materials and AI remediation.'],
            ['Learning analytics dashboards', 'Show participation and grade indicators.', 'The plugin asks an LLM to interpret pre/post test gaps and suggest actions.'],
            ['AI quiz generators', 'Generate questions from text.', 'The plugin integrates generation into Moodle course roles, attempts and teacher dashboard.'],
            ['Online simulators', 'External topic-specific practice tools.', 'The plugin can suggest simulator usage in a Moodle course plan, connected to teacher material.'],
        ];

        echo html_writer::start_div('card mt-4 mb-4');
        echo html_writer::start_div('card-body');

        echo html_writer::tag('h3', 'External baseline and existing tools');
        echo html_writer::tag(
            'p',
            'This section is not a checklist: it documents what already exists and how the plugin differs.',
            ['class' => 'text-muted']
        );

        echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
        echo html_writer::tag(
            'tr',
            html_writer::tag('th', 'Existing tool/category') .
            html_writer::tag('th', 'What it already does') .
            html_writer::tag('th', 'What this plugin adds')
        );

        foreach ($rows as $row) {
            echo html_writer::tag(
                'tr',
                html_writer::tag('td', s($row[0])) .
                html_writer::tag('td', s($row[1])) .
                html_writer::tag('td', s($row[2]))
            );
        }

        echo html_writer::end_tag('table');

        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}