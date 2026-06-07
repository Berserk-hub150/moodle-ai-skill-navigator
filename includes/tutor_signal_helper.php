<?php

defined('MOODLE_INTERNAL') || die();

function local_aiskillnavigator_tutor_signal_ensure_table(): void {
    global $DB, $CFG;

    require_once($CFG->libdir . '/ddllib.php');

    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_aiskillnav_tutor_sig');

    if ($dbman->table_exists($table)) {
        return;
    }

    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('question', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('sourcemode', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'none');
    $table->add_field('materials', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('skill', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, 'General');
    $table->add_field('requesttype', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, 'question');
    $table->add_field('difficulty', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'medium');
    $table->add_field('answerpreview', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
    $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
    $table->add_index('skill_idx', XMLDB_INDEX_NOTUNIQUE, ['skill']);

    $dbman->create_table($table);
}

function local_aiskillnavigator_tutor_signal_contains(string $text, array $needles): bool {
    $text = core_text::strtolower($text);

    foreach ($needles as $needle) {
        if (str_contains($text, core_text::strtolower($needle))) {
            return true;
        }
    }

    return false;
}

function local_aiskillnavigator_tutor_signal_classify_skill(string $question, string $answer): string {
    $text = $question . ' ' . $answer;

    $rules = [
        'HTML semantico' => ['html', 'section', 'article', 'header', 'footer', 'img', 'input', 'form'],
        'CSS layout' => ['css', 'flexbox', 'grid', 'box model', 'padding', 'margin', 'border'],
        'Funzioni matematiche' => ['funzione', 'funzioni', 'lineare', 'quadratica', 'dominio', 'codominio', 'grafico'],
        'Composizione di funzioni' => ['f(g', 'g(x)', 'f(x)', 'composta', 'composizione'],
        'Database / SQL' => ['sql', 'select', 'join', 'database', 'tabella', 'query'],
        'Programmazione' => ['codice', 'variabile', 'funzione', 'classe', 'metodo', 'array', 'ciclo'],
        'RAG / AI' => ['rag', 'embedding', 'llm', 'token', 'prompt', 'modello'],
    ];

    foreach ($rules as $skill => $needles) {
        if (local_aiskillnavigator_tutor_signal_contains($text, $needles)) {
            return $skill;
        }
    }

    return 'General question';
}

function local_aiskillnavigator_tutor_signal_classify_type(string $question): string {
    if (local_aiskillnavigator_tutor_signal_contains($question, ['non capisco', 'non ho capito', 'perche ho sbagliato', 'perchè ho sbagliato', 'dove sbaglio'])) {
        return 'doubt/error';
    }

    if (local_aiskillnavigator_tutor_signal_contains($question, ['esercizio', 'fammi provare', 'allenamento', 'quiz', 'domanda'])) {
        return 'practice request';
    }

    if (local_aiskillnavigator_tutor_signal_contains($question, ['differenza', 'confronta', 'versus', 'vs'])) {
        return 'comparison';
    }

    if (local_aiskillnavigator_tutor_signal_contains($question, ['spiegami', 'cos e', 'cosè', 'cosa significa', 'definisci'])) {
        return 'explanation';
    }

    return 'question';
}

function local_aiskillnavigator_tutor_signal_classify_difficulty(string $question, string $answer): string {
    $text = $question . ' ' . $answer;

    if (local_aiskillnavigator_tutor_signal_contains($text, ['non capisco', 'confuso', 'sbaglio', 'errore', 'difficile', 'non riesco'])) {
        return 'high';
    }

    if (local_aiskillnavigator_tutor_signal_contains($question, ['esempio', 'semplice', 'base'])) {
        return 'low';
    }

    return 'medium';
}

function local_aiskillnavigator_tutor_signal_store(
    int $courseid,
    int $userid,
    string $question,
    string $sourcemode,
    array $usedmaterials,
    string $answer
): void {
    global $DB;

    if (trim($question) === '' || trim($answer) === '') {
        return;
    }

    try {
        local_aiskillnavigator_tutor_signal_ensure_table();

        $record = new stdClass();
        $record->courseid = $courseid;
        $record->userid = $userid;
        $record->question = core_text::substr(trim($question), 0, 4000);
        $record->sourcemode = core_text::substr(trim($sourcemode), 0, 32);
        $record->materials = json_encode(array_values($usedmaterials), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $record->skill = core_text::substr(local_aiskillnavigator_tutor_signal_classify_skill($question, $answer), 0, 120);
        $record->requesttype = core_text::substr(local_aiskillnavigator_tutor_signal_classify_type($question), 0, 80);
        $record->difficulty = core_text::substr(local_aiskillnavigator_tutor_signal_classify_difficulty($question, $answer), 0, 40);
        $record->answerpreview = core_text::substr(trim(strip_tags($answer)), 0, 1200);
        $record->timecreated = time();

        $DB->insert_record('local_aiskillnav_tutor_sig', $record);
    } catch (Throwable $e) {
        debugging('AI Skill Navigator tutor signal store failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

function local_aiskillnavigator_tutor_signal_teacher_panel(int $courseid): string {
    global $DB, $OUTPUT;

    try {
        local_aiskillnavigator_tutor_signal_ensure_table();
    } catch (Throwable $e) {
        return html_writer::div('Tutor analytics unavailable: ' . s($e->getMessage()), 'alert alert-warning');
    }

    $total = $DB->count_records('local_aiskillnav_tutor_sig', ['courseid' => $courseid]);

    $skills = $DB->get_records_sql(
        "SELECT " . $DB->sql_compare_text('skill', 120) . " AS skillkey,
                MIN(skill) AS skill,
                COUNT(1) AS total
           FROM {local_aiskillnav_tutor_sig}
          WHERE courseid = :courseid
       GROUP BY " . $DB->sql_compare_text('skill', 120) . "
       ORDER BY total DESC",
        ['courseid' => $courseid],
        0,
        8
    );

    $types = $DB->get_records_sql(
        "SELECT " . $DB->sql_compare_text('requesttype', 80) . " AS typekey,
                MIN(requesttype) AS requesttype,
                COUNT(1) AS total
           FROM {local_aiskillnav_tutor_sig}
          WHERE courseid = :courseid
       GROUP BY " . $DB->sql_compare_text('requesttype', 80) . "
       ORDER BY total DESC",
        ['courseid' => $courseid],
        0,
        6
    );

    $recent = $DB->get_records('local_aiskillnav_tutor_sig', ['courseid' => $courseid], 'timecreated DESC', '*', 0, 5);

    $html = '';
    $html .= html_writer::start_div('card mb-4 border-info');
    $html .= html_writer::start_div('card-body');

    $html .= html_writer::tag('h3', 'Tutor-as-Sensor analytics');
    $html .= html_writer::tag(
        'p',
        'Le domande fatte dagli studenti al tutor diventano segnali didattici: competenze richieste, dubbi ricorrenti e argomenti da rinforzare.',
        ['class' => 'text-muted']
    );

    $html .= html_writer::start_div('row mb-3');

    $html .= html_writer::start_div('col-md-4 mb-2');
    $html .= html_writer::div(
        html_writer::tag('div', (string)$total, ['style' => 'font-size:32px;font-weight:900;']) .
        html_writer::tag('div', 'Tutor questions tracked', ['class' => 'text-muted']),
        'p-3 rounded',
        ['style' => 'background:#eff6ff;border:1px solid #bfdbfe;']
    );
    $html .= html_writer::end_div();

    $topskill = 'n/a';
    foreach ($skills as $s) {
        $topskill = (string)$s->skill;
        break;
    }

    $html .= html_writer::start_div('col-md-4 mb-2');
    $html .= html_writer::div(
        html_writer::tag('div', s($topskill), ['style' => 'font-size:22px;font-weight:900;']) .
        html_writer::tag('div', 'Most requested skill', ['class' => 'text-muted']),
        'p-3 rounded',
        ['style' => 'background:#f0fdf4;border:1px solid #bbf7d0;']
    );
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('col-md-4 mb-2');
    $html .= html_writer::div(
        html_writer::tag('div', 'Teacher action', ['style' => 'font-size:22px;font-weight:900;']) .
        html_writer::tag('div', 'Create a mini lesson or review activity on the most requested skill.', ['class' => 'text-muted']),
        'p-3 rounded',
        ['style' => 'background:#fff7ed;border:1px solid #fed7aa;']
    );
    $html .= html_writer::end_div();

    $html .= html_writer::end_div();

    $html .= html_writer::start_div('row');

    $html .= html_writer::start_div('col-md-6 mb-3');
    $html .= html_writer::tag('h4', 'Most requested skills');
    if (empty($skills)) {
        $html .= html_writer::div('No tutor questions tracked yet.', 'text-muted');
    } else {
        $html .= html_writer::start_tag('ul', ['class' => 'list-group']);
        foreach ($skills as $s) {
            $html .= html_writer::tag(
                'li',
                html_writer::tag('strong', s((string)$s->skill)) .
                html_writer::span((string)$s->total, 'badge badge-primary float-right'),
                ['class' => 'list-group-item']
            );
        }
        $html .= html_writer::end_tag('ul');
    }
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('col-md-6 mb-3');
    $html .= html_writer::tag('h4', 'Request types');
    if (empty($types)) {
        $html .= html_writer::div('No request types yet.', 'text-muted');
    } else {
        $html .= html_writer::start_tag('ul', ['class' => 'list-group']);
        foreach ($types as $t) {
            $html .= html_writer::tag(
                'li',
                html_writer::tag('strong', s((string)$t->requesttype)) .
                html_writer::span((string)$t->total, 'badge badge-secondary float-right'),
                ['class' => 'list-group-item']
            );
        }
        $html .= html_writer::end_tag('ul');
    }
    $html .= html_writer::end_div();

    $html .= html_writer::end_div();

    $html .= html_writer::tag('h4', 'Recent tutor questions');
    if (empty($recent)) {
        $html .= html_writer::div('No recent tutor questions.', 'text-muted');
    } else {
        $html .= html_writer::start_tag('div', ['class' => 'table-responsive']);
        $html .= html_writer::start_tag('table', ['class' => 'table table-sm']);
        $html .= html_writer::tag('thead',
            html_writer::tag('tr',
                html_writer::tag('th', 'Time') .
                html_writer::tag('th', 'Student') .
                html_writer::tag('th', 'Skill') .
                html_writer::tag('th', 'Type') .
                html_writer::tag('th', 'Question')
            )
        );
        $html .= html_writer::start_tag('tbody');

        foreach ($recent as $r) {
            $user = $DB->get_record('user', ['id' => $r->userid], 'id,firstname,lastname', IGNORE_MISSING);
            $student = $user ? fullname($user) : ('User #' . (int)$r->userid);

            $html .= html_writer::tag('tr',
                html_writer::tag('td', userdate((int)$r->timecreated, '%d/%m %H:%M')) .
                html_writer::tag('td', s($student)) .
                html_writer::tag('td', s((string)$r->skill)) .
                html_writer::tag('td', s((string)$r->requesttype)) .
                html_writer::tag('td', s(core_text::substr((string)$r->question, 0, 160)))
            );
        }

        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');
        $html .= html_writer::end_tag('div');
    }

    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}