<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/resource/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/upload_guard.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../classes/service/ai_provider_interface.php');
require_once(__DIR__ . '/../classes/service/ai_provider_factory.php');

global $DB, $PAGE, $OUTPUT, $CFG, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$prompt = optional_param('prompt', '', PARAM_RAW_TRIMMED);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/course_builder.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Course Builder');
$PAGE->set_heading('AI Course Builder');

function local_aisn_cb_low(string $text): string {
    return core_text::strtolower(trim($text));
}

function local_aisn_cb_clean(string $text, int $max = 800): string {
    $text = trim(strip_tags($text));
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\xC2\xA0", "\t", "\r", "\n"], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', (string)$text);
    $text = trim((string)$text);

    if ($text === '') {
        return '';
    }

    if (core_text::strlen($text) > $max) {
        return core_text::substr($text, 0, $max);
    }

    return $text;
}

function local_aisn_cb_section_text(string $text): string {
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\xC2\xA0", "\t", "\r", "\n"], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', (string)$text);
    $text = trim((string)$text);
    $text = trim($text, " \t\n\r\0\x0B\"'.,:;()[]{}“”‘’«»");

    $text = preg_replace('/^(?:la|il|lo|una|un)\s+/iu', '', (string)$text);
    $text = preg_replace('/^(?:sezione|sezioni|section)\s+/iu', '', (string)$text);
    $text = preg_replace('/\s+(?:del corso|nel corso|corrente)$/iu', '', (string)$text);
    $text = trim((string)$text);
    $text = trim($text, " \t\n\r\0\x0B\"'.,:;()[]{}“”‘’«»");
    $text = preg_replace('/\s+/u', ' ', (string)$text);

    return trim((string)$text);
}

function local_aisn_cb_section_key(string $text): string {
    $text = local_aisn_cb_low(local_aisn_cb_section_text($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$text);

    return trim((string)$text);
}

function local_aisn_cb_section_tokens(string $text): array {
    $text = local_aisn_cb_low(local_aisn_cb_section_text($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string)$text);
    $parts = preg_split('/\s+/u', trim((string)$text));

    return array_values(array_filter((array)$parts, function ($part) {
        return core_text::strlen((string)$part) >= 2;
    }));
}

function local_aisn_cb_all_tokens_match(array $needles, string $haystack): bool {
    if (empty($needles)) {
        return false;
    }

    $haystack = local_aisn_cb_low($haystack);

    foreach ($needles as $token) {
        if (!str_contains($haystack, local_aisn_cb_low((string)$token))) {
            return false;
        }
    }

    return true;
}

function local_aisn_cb_clean_section_title(string $title, int $max = 120): string {
    $title = local_aisn_cb_section_text($title);

    if ($title === '') {
        return 'Nuova sezione';
    }

    if (core_text::strlen($title) > $max) {
        $title = core_text::substr($title, 0, $max);
    }

    return trim($title);
}

function local_aisn_cb_next_section(int $courseid): int {
    global $DB;
    $max = $DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$courseid]);

    return ((int)$max) + 1;
}

function local_aisn_cb_get_section(int $courseid, int $sectionnum): ?stdClass {
    global $DB;

    return $DB->get_record('course_sections', ['course' => $courseid, 'section' => $sectionnum]) ?: null;
}

function local_aisn_cb_find_section(int $courseid, string $ref): ?stdClass {
    global $DB;

    $ref = local_aisn_cb_section_text($ref);

    if ($ref === '') {
        return null;
    }

    if (ctype_digit($ref)) {
        return local_aisn_cb_get_section($courseid, (int)$ref);
    }

    $reflow = local_aisn_cb_low($ref);

    // Moodle/Boost often shows the general section as "Highlights" even when
    // course_sections.name is empty. Treat those words as section 0.
    if (local_aisn_cb_is_section_zero_alias($ref)) {
        return local_aisn_cb_get_section($courseid, 0);
    }

    $refkey = local_aisn_cb_section_key($ref);
    $reftokens = local_aisn_cb_section_tokens($ref);
    $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');

    foreach ($sections as $section) {
        $name = local_aisn_cb_section_text((string)($section->name ?? ''));

        if ($name !== '' && local_aisn_cb_low($name) === $reflow) {
            return $section;
        }
    }

    foreach ($sections as $section) {
        $sectionnum = (int)($section->section ?? -1);
        $name = local_aisn_cb_section_text((string)($section->name ?? ''));
        $synthetic = $name !== '' ? $name : ($sectionnum === 0 ? 'Highlights' : ('Section ' . $sectionnum));
        $namekey = local_aisn_cb_section_key($synthetic);

        if ($namekey !== '' && $refkey !== '' && $namekey === $refkey) {
            return $section;
        }
    }

    foreach ($sections as $section) {
        $sectionnum = (int)($section->section ?? -1);
        $name = local_aisn_cb_section_text((string)($section->name ?? ''));
        $synthetic = $name !== '' ? $name : ($sectionnum === 0 ? 'Highlights' : ('Section ' . $sectionnum));
        $namekey = local_aisn_cb_section_key($synthetic);

        if ($namekey === '' || $refkey === '') {
            continue;
        }

        if (core_text::strlen($refkey) >= 3 &&
            (str_contains($namekey, $refkey) || str_contains($refkey, $namekey))) {
            return $section;
        }

        if (local_aisn_cb_all_tokens_match($reftokens, $synthetic)) {
            return $section;
        }
    }

    return null;
}

function local_aisn_cb_create_section(int $courseid, string $title, string $summary = ''): stdClass {
    global $DB;

    $sectionnum = local_aisn_cb_next_section($courseid);
    $course = get_course($courseid);

    try {
        $section = course_create_section($course, $sectionnum);
    } catch (Throwable $e) {
        $section = course_create_section($courseid, $sectionnum);
    }

    if (empty($section) || empty($section->id)) {
        $section = new stdClass();
        $section->course = $courseid;
        $section->section = $sectionnum;
        $section->name = '';
        $section->summary = '';
        $section->summaryformat = FORMAT_HTML;
        $section->sequence = '';
        $section->visible = 1;
        $section->availability = null;
        $section->timemodified = time();
        $section->id = $DB->insert_record('course_sections', $section);
    }

    $section->name = local_aisn_cb_clean_section_title($title, 120);
    $section->summary = $summary !== '' ? $summary : '<p>Sezione creata da AI Course Builder tramite prompt docente.</p>';
    $section->summaryformat = FORMAT_HTML;
    $section->visible = 1;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);
    rebuild_course_cache($courseid, true);

    return $DB->get_record('course_sections', ['id' => $section->id], '*', MUST_EXIST);
}

function local_aisn_cb_ensure_section(int $courseid, string $title, string $summary = ''): stdClass {
    $section = local_aisn_cb_find_section($courseid, $title);

    if ($section) {
        return $section;
    }

    return local_aisn_cb_create_section($courseid, $title, $summary);
}

function local_aisn_cb_update_section(stdClass $section, string $title = '', string $summary = ''): void {
    global $DB;

    if ($title !== '') {
        $section->name = local_aisn_cb_clean_section_title($title, 120);
    }

    if ($summary !== '') {
        $section->summary = '<p>' . s(local_aisn_cb_clean($summary, 1800)) . '</p>';
        $section->summaryformat = FORMAT_HTML;
    }

    $section->timemodified = time();
    $DB->update_record('course_sections', $section);
    rebuild_course_cache((int)$section->course, true);
}

function local_aisn_cb_set_visibility(stdClass $section, bool $visible): void {
    global $DB;

    $section->visible = $visible ? 1 : 0;
    $section->timemodified = time();
    $DB->update_record('course_sections', $section);
    rebuild_course_cache((int)$section->course, true);
}

function local_aisn_cb_is_section_zero_alias(string $text): bool {
    $key = local_aisn_cb_section_key($text);

    return in_array($key, [
        '0',
        'section0',
        'sezione0',
        'general',
        'generale',
        'generalcourse',
        'sezionegenerale',
        'highlights',
        'highlight',
        'evidenza',
        'evidenze',
        'inrilievo',
        'announcements',
        'annunci',
    ], true);
}

function local_aisn_cb_delete_modules_in_section(int $courseid, int $sectionnum): int {
    global $DB;

    $sql = "SELECT cm.id
              FROM {course_modules} cm
              JOIN {course_sections} cs ON cs.id = cm.section
             WHERE cm.course = :courseid
               AND cs.course = :courseid2
               AND cs.section = :sectionnum
          ORDER BY cm.id DESC";

    $cms = $DB->get_records_sql($sql, [
        'courseid' => $courseid,
        'courseid2' => $courseid,
        'sectionnum' => $sectionnum,
    ]);

    $deleted = 0;

    foreach ($cms as $cm) {
        try {
            course_delete_module((int)$cm->id);
            $deleted++;
        } catch (Throwable $e) {
            debugging('AI Course Builder module deletion failed for cmid ' . (int)$cm->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    return $deleted;
}

function local_aisn_cb_clear_section_zero(int $courseid): void {
    global $DB;

    $section0 = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0]);

    if (!$section0) {
        return;
    }

    $section0->name = '';
    $section0->summary = '';
    $section0->summaryformat = FORMAT_HTML;
    $section0->sequence = '';
    $section0->visible = 1;
    $section0->timemodified = time();

    $DB->update_record('course_sections', $section0);
}

function local_aisn_cb_delete_all_sections(int $courseid): array {
    global $DB;

    $course = get_course($courseid);
    $logs = [];

    $cms = $DB->get_records('course_modules', ['course' => $courseid], 'id DESC', 'id');
    $deletedmods = 0;

    foreach ($cms as $cm) {
        try {
            course_delete_module((int)$cm->id);
            $deletedmods++;
        } catch (Throwable $e) {
            $logs[] = 'Modulo cmid ' . (int)$cm->id . ' non cancellato: ' . $e->getMessage();
        }
    }

    $sections = $DB->get_records_select(
        'course_sections',
        'course = ? AND section > 0',
        [$courseid],
        'section DESC',
        'id, course, section, name'
    );

    $deletedsections = 0;

    foreach ($sections as $section) {
        try {
            if (function_exists('course_delete_section')) {
                course_delete_section($course, (int)$section->section, true);
            } else {
                $DB->delete_records('course_sections', ['id' => (int)$section->id]);
            }
            $deletedsections++;
        } catch (Throwable $e) {
            $logs[] = 'Sezione ' . (int)$section->section . ' non cancellata: ' . $e->getMessage();
        }
    }

    local_aisn_cb_clear_section_zero($courseid);
    rebuild_course_cache($courseid, true);

    array_unshift($logs, 'Azione diretta: corso ripulito. Moduli cancellati: ' . $deletedmods . '. Sezioni cancellate: ' . $deletedsections . '. Highlights/section 0 svuotata.');

    return $logs;
}

function local_aisn_cb_prompt_wants_clear_course(string $prompt): bool {
    $p = local_aisn_cb_low($prompt);

    if (preg_match('/\b(?:elimina|cancella|rimuovi|togli|svuota|resetta|ripulisci|pulisci)\b.*\b(?:tutte|tutti|ogni|intero|intera)\b.*\b(?:sezioni|sezione|corso|materiali|risorse|attivit)/iu', $prompt)) {
        return true;
    }

    if (preg_match('/\b(?:elimina|cancella|rimuovi|togli|svuota|resetta|ripulisci|pulisci)\b.*\b(?:corso|course)\b/iu', $prompt)) {
        return true;
    }

    return str_contains($p, 'cancella tutto')
        || str_contains($p, 'elimina tutto')
        || str_contains($p, 'rimuovi tutto')
        || str_contains($p, 'svuota tutto')
        || str_contains($p, 'reset corso')
        || str_contains($p, 'ripulisci corso')
        || str_contains($p, 'pulisci corso')
        || str_contains($p, 'delete all sections')
        || str_contains($p, 'clear course');
}

function local_aisn_cb_prompt_mentions_files_or_materials(string $prompt): bool {
    $p = local_aisn_cb_low($prompt);

    return str_contains($p, 'file')
        || str_contains($p, 'materiale')
        || str_contains($p, 'materiali')
        || str_contains($p, 'caricato')
        || str_contains($p, 'caricati')
        || str_contains($p, 'allegato')
        || str_contains($p, 'allegati')
        || str_contains($p, 'inserito')
        || str_contains($p, 'inseriti');
}

function local_aisn_cb_prompt_singular_section_request(string $prompt): bool {
    return (bool)preg_match('/\b(?:una|unica|sola|solo una|1)\s+sezione\b/iu', $prompt)
        || (bool)preg_match('/\bsezione\s+(?:chiamata|chiamala|di nome|intitolata)\b/iu', $prompt)
        || (bool)preg_match('/\b(?:chiamala|chiamarla|chiamata|intitolata)\b/iu', $prompt);
}

function local_aisn_cb_command_title_clean(string $raw): string {
    $title = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = preg_replace('/\s+/u', ' ', (string)$title);
    $title = trim((string)$title, " \t\n\r\0\x0B\"'.,:;()[]{}“”‘’«»");

    $title = preg_replace('/^(?:una|unica|sola|solo una|nuova)\s+/iu', '', (string)$title);
    $title = preg_replace('/^(?:sezione|section)\s+/iu', '', (string)$title);
    $title = preg_replace('/^(?:chiamata|chiamato|chiamala|chiamalo|di nome|nome|intitolata|intitolato)\s+/iu', '', (string)$title);

    $stoppers = [
        ' e mett', ' e metti', ' e inser', ' e carica', ' e collega', ' e aggiung',
        ' con i file', ' con file', ' con i materiali', ' con materiali',
        ' dove ', ' nel corso', ' al corso', ' che ti ho', ' che ho', ' caricati', ' caricata', ' caricati',
    ];

    $low = local_aisn_cb_low($title);
    $cut = null;

    foreach ($stoppers as $stopper) {
        $pos = strpos($low, $stopper);
        if ($pos !== false && ($cut === null || $pos < $cut)) {
            $cut = $pos;
        }
    }

    if ($cut !== null && $cut > 0) {
        $title = substr($title, 0, $cut);
    }

    $title = local_aisn_cb_clean_section_title((string)$title);
    $key = local_aisn_cb_section_key($title);

    if ($key === '' || in_array($key, ['file', 'files', 'materiale', 'materiali', 'tutti', 'tutte', 'corso'], true)) {
        return '';
    }

    return $title;
}

function local_aisn_cb_extract_named_section_title(string $prompt): ?string {
    $patterns = [
        '/\b(?:chiamala|chiamarlo|chiamalo|chiamarla|chiamata|chiamato|intitolata|intitolato)\s+["“”\'«»]?(.+?)(?:["“”\'«»]|\s+(?:mettendoci|mettici|metti|inserendo|contenente|che contiene|con|e\s+per quanto riguarda)\b|[\r\n.;:]|$)/iu',
        '/\bsezione\s+(?:chiamata|chiamato|di nome|intitolata|intitolato)\s+["“”\'«»]?(.+?)(?:["“”\'«»]|\s+(?:mettendoci|mettici|metti|inserendo|contenente|che contiene|con|e\s+per quanto riguarda)\b|[\r\n.;:]|$)/iu',
        '/\b(?:crea|creami|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\s+["“”\'«»]?(.+?)(?:["“”\'«»]|\s+(?:mettendoci|mettici|metti|inserendo|contenente|che contiene|con|e\s+per quanto riguarda)\b|[\r\n.;:]|$)/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $matches)) {
            $title = local_aisn_cb_safe_title_clean((string)$matches[1]);

            if ($title !== '' && !in_array(local_aisn_cb_low($title), ['file', 'files', 'materiale', 'materiali', 'tutti', 'tutte'], true)) {
                return $title;
            }
        }
    }

    return null;
}
function local_aisn_cb_delete_section(int $courseid, stdClass $section): string {
    $sectionnum = (int)($section->section ?? -1);

    // Section 0 cannot be physically removed in Moodle. For a prompt like
    // "elimina Highlights", the expected Course Builder behaviour is to remove
    // all activities from it and clear title/summary so it becomes empty.
    if ($sectionnum <= 0) {
        $deletedmods = local_aisn_cb_delete_modules_in_section($courseid, 0);
        local_aisn_cb_clear_section_zero($courseid);
        rebuild_course_cache($courseid, true);

        return 'svuotata (section 0 Moodle non eliminabile fisicamente, moduli rimossi: ' . $deletedmods . ')';
    }

    $course = get_course($courseid);

    if (function_exists('course_delete_section')) {
        course_delete_section($course, $sectionnum, true);
        rebuild_course_cache($courseid, true);

        return 'cancellata';
    }

    local_aisn_cb_set_visibility($section, false);

    return 'nascosta perché course_delete_section non è disponibile';
}

function local_aisn_cb_duplicate_section(int $courseid, stdClass $source, string $newtitle = ''): stdClass {
    $title = $newtitle !== '' ? $newtitle : ((string)$source->name . ' - copia');

    return local_aisn_cb_create_section($courseid, $title, (string)($source->summary ?? ''));
}

function local_aisn_cb_move_section(int $courseid, stdClass $section, int $destination): string {
    $destination = max(1, $destination);
    $course = get_course($courseid);

    if (function_exists('move_section_to')) {
        move_section_to($course, (int)$section->section, $destination);
        rebuild_course_cache($courseid, true);

        return 'spostata';
    }

    return 'move_section_to non disponibile';
}

function local_aisn_cb_file_signature(array $file): string {
    $name = core_text::strtolower(clean_param((string)($file['name'] ?? ''), PARAM_FILE));
    $size = (int)($file['size'] ?? 0);
    $tmp = (string)($file['tmp_name'] ?? '');
    $hash = '';

    if ($tmp !== '' && is_file($tmp) && is_readable($tmp)) {
        $hash = (string)@sha1_file($tmp);
    }

    return $name . '|' . $size . '|' . $hash;
}

function local_aisn_cb_dedupe_files(array $files): array {
    $seen = [];
    $out = [];

    foreach ($files as $file) {
        $sig = local_aisn_cb_file_signature($file);

        if ($sig === '||' || isset($seen[$sig])) {
            continue;
        }

        $seen[$sig] = true;
        $out[] = $file;
    }

    return $out;
}

function local_aisn_cb_uploaded_files(): array {
    if (empty($_FILES['materials']) || empty($_FILES['materials']['name'])) {
        return [];
    }

    $files = [];
    $names = $_FILES['materials']['name'];

    if (!is_array($names)) {
        $candidate = [
            'name' => $_FILES['materials']['name'] ?? '',
            'type' => $_FILES['materials']['type'] ?? '',
            'tmp_name' => $_FILES['materials']['tmp_name'] ?? '',
            'error' => $_FILES['materials']['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES['materials']['size'] ?? 0,
        ];

        $validation = local_aisn_upload_validate_uploaded_file($candidate, true);

        if (!empty($validation['ok']) && !empty($validation['file'])) {
            $files[] = $validation['file'];
        }

        return local_aisn_cb_dedupe_files($files);
    }

    foreach ($names as $i => $name) {
        $candidate = [
            'name' => $name,
            'type' => $_FILES['materials']['type'][$i] ?? '',
            'tmp_name' => $_FILES['materials']['tmp_name'][$i] ?? '',
            'error' => $_FILES['materials']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES['materials']['size'][$i] ?? 0,
        ];

        $validation = local_aisn_upload_validate_uploaded_file($candidate, true);

        if (!empty($validation['ok']) && !empty($validation['file'])) {
            $files[] = $validation['file'];
        }
    }

    return local_aisn_cb_dedupe_files($files);
}

function local_aisn_cb_resource_identity(string $name): string {
    $name = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = core_text::strtolower(trim((string)$name));
    $name = preg_replace('/^\s*materiale\s*-\s*/iu', '', (string)$name);
    $name = preg_replace('/^\s*file\s*[:\-]?\s*/iu', '', (string)$name);
    $name = str_replace(["\xC2\xA0", "\t", "\r", "\n"], ' ', (string)$name);
    $name = preg_replace('/\s+/u', ' ', (string)$name);
    $name = trim((string)$name);

    return preg_replace('/[^\p{L}\p{N}\.]+/u', '', (string)$name);
}

function local_aisn_cb_existing_resource_cmid(int $courseid, int $sectionnum, string $resourcename): int {
    global $DB;

    $name = local_aisn_cb_clean($resourcename, 120);

    if ($name === '') {
        return 0;
    }

    $wantedkey = local_aisn_cb_resource_identity($name);

    $sql = "SELECT cm.id, r.name
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {resource} r ON r.id = cm.instance
              JOIN {course_sections} cs ON cs.id = cm.section
             WHERE cm.course = :courseid
               AND cs.course = :courseid2
               AND cs.section = :sectionnum
               AND m.name = :modname
               AND cm.deletioninprogress = 0
          ORDER BY cm.id ASC";

    $records = $DB->get_records_sql($sql, [
        'courseid' => $courseid,
        'courseid2' => $courseid,
        'sectionnum' => $sectionnum,
        'modname' => 'resource',
    ]);

    foreach ($records as $record) {
        if (trim((string)$record->name) === $name) {
            return (int)$record->id;
        }
    }

    foreach ($records as $record) {
        if ($wantedkey !== '' && local_aisn_cb_resource_identity((string)$record->name) === $wantedkey) {
            return (int)$record->id;
        }
    }

    return 0;
}

function local_aisn_cb_cleanup_duplicate_resources_in_course(int $courseid): int {
    global $DB;

    $sql = "SELECT cm.id AS cmid,
                   cm.section AS sectionid,
                   cs.section AS sectionnum,
                   r.name AS resourcename
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {resource} r ON r.id = cm.instance
              JOIN {course_sections} cs ON cs.id = cm.section
             WHERE cm.course = :courseid
               AND cs.course = :courseid2
               AND m.name = :modname
               AND cm.deletioninprogress = 0
          ORDER BY cs.section ASC, cm.id ASC";

    $records = $DB->get_records_sql($sql, [
        'courseid' => $courseid,
        'courseid2' => $courseid,
        'modname' => 'resource',
    ]);

    $seen = [];
    $deleted = 0;

    foreach ($records as $record) {
        $identity = local_aisn_cb_resource_identity((string)$record->resourcename);

        if ($identity === '') {
            continue;
        }

        $key = (int)$record->sectionid . '|' . $identity;

        if (!isset($seen[$key])) {
            $seen[$key] = (int)$record->cmid;
            continue;
        }

        try {
            course_delete_module((int)$record->cmid);
            $deleted++;
        } catch (Throwable $e) {
            debugging('AI Course Builder duplicate resource cleanup failed for cmid ' . (int)$record->cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    if ($deleted > 0) {
        rebuild_course_cache($courseid, true);
    }

    return $deleted;
}

function local_aisn_cb_create_resource_from_file(int $courseid, int $sectionnum, array $file, string $resourcename): int {
    global $DB, $USER;

    $validation = local_aisn_upload_validate_uploaded_file($file, true);

    if (empty($validation['ok']) || empty($validation['file'])) {
        throw new RuntimeException('Uploaded material rejected: ' . (string)($validation['message'] ?? 'invalid file'));
    }

    $file = $validation['file'];
    $filename = clean_param((string)$file['name'], PARAM_FILE);

    if ($filename === '') {
        $filename = 'teacher-material.' . (string)($file['extension'] ?? 'txt');
    }

    $cleanresourcename = local_aisn_cb_clean($resourcename, 120);

    if ($cleanresourcename === '') {
        $cleanresourcename = 'Materiale - ' . $filename;
    }

    $existingcmid = local_aisn_cb_existing_resource_cmid($courseid, $sectionnum, $cleanresourcename);

    if ($existingcmid > 0) {
        return $existingcmid;
    }

    $moduleid = $DB->get_field('modules', 'id', ['name' => 'resource'], MUST_EXIST);
    $draftitemid = file_get_unused_draft_itemid();
    $usercontext = context_user::instance((int)$USER->id);

    $fs = get_file_storage();
    $fs->create_file_from_pathname([
        'contextid' => $usercontext->id,
        'component' => 'user',
        'filearea' => 'draft',
        'itemid' => $draftitemid,
        'filepath' => '/',
        'filename' => $filename,
    ], (string)$file['tmp_name']);

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'resource';
    $moduleinfo->module = $moduleid;
    $moduleinfo->course = $courseid;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->visible = 1;
    $moduleinfo->name = $cleanresourcename;
    $moduleinfo->intro = 'Risorsa caricata tramite AI Course Builder.';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->showdescription = 0;
    $moduleinfo->files = $draftitemid;
    $moduleinfo->display = defined('RESOURCELIB_DISPLAY_AUTO') ? RESOURCELIB_DISPLAY_AUTO : 0;
    $moduleinfo->displayoptions = '';
    $moduleinfo->printintro = 0;
    $moduleinfo->printlastmodified = 1;
    $moduleinfo->completion = 0;
    $moduleinfo->availability = null;

    $created = add_moduleinfo($moduleinfo, get_course($courseid));

    rebuild_course_cache($courseid, true);

    return !empty($created->coursemodule) ? (int)$created->coursemodule : 0;
}

function local_aisn_cb_attach_files_to_section(int $courseid, int $userid, stdClass $section, array $files): array {
    $logs = [];
    $files = local_aisn_cb_dedupe_files($files);

    foreach ($files as $file) {
        $filename = clean_param((string)($file['name'] ?? ''), PARAM_FILE);

        if ($filename === '') {
            $filename = 'file docente';
        }

        $resourcename = 'Materiale - ' . $filename;
        $sectionnum = (int)$section->section;
        $existingcmid = local_aisn_cb_existing_resource_cmid($courseid, $sectionnum, $resourcename);

        if ($existingcmid > 0) {
            $logs[] = 'File "' . $filename . '" già presente nella sezione "' . (string)$section->name . '": duplicato non creato.';
            continue;
        }

        $cmid = local_aisn_cb_create_resource_from_file($courseid, $sectionnum, $file, $resourcename);

        if ($cmid > 0) {
            $logs[] = 'File "' . $filename . '" aggiunto alla sezione "' . (string)$section->name . '" come risorsa Moodle.';
        } else {
            $logs[] = 'File "' . $filename . '" non aggiunto: creazione risorsa non riuscita.';
        }
    }

    $removed = local_aisn_cb_cleanup_duplicate_resources_in_course($courseid);

    if ($removed > 0) {
        $logs[] = 'Pulizia production: rimossi ' . $removed . ' materiali duplicati già presenti nel corso.';
    }

    return $logs;
}

function local_aisn_cb_sync_resources(int $courseid): void {
    global $CFG, $USER;

    static $running = false;

    if ($running || $courseid <= SITEID) {
        return;
    }

    $running = true;

    try {
        $syncfile = $CFG->dirroot . '/local/aiskillnavigator/includes/course_resource_sync.php';

        if (!file_exists($syncfile)) {
            return;
        }

        require_once($syncfile);

        if (function_exists('local_aiskillnavigator_sync_course_resources')) {
            local_aiskillnavigator_sync_course_resources(
                $courseid,
                !empty($USER->id) ? (int)$USER->id : 0,
                false
            );
        }

        local_aisn_cb_cleanup_duplicate_resources_in_course($courseid);
        rebuild_course_cache($courseid, true);
    } catch (Throwable $e) {
        debugging('AI Course Builder safe sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    } finally {
        $running = false;
    }
}

function local_aisn_cb_filename_to_section_title(string $filename): string {
    $filename = clean_param($filename, PARAM_FILE);
    $base = preg_replace('/\.[^.]+$/', '', $filename);
    $base = preg_replace('/^TBDM-2025-26-/iu', '', (string)$base);
    $base = preg_replace('/^Materiale\s*-\s*/iu', '', (string)$base);
    $base = str_replace(['_', '-'], ' ', (string)$base);
    $base = preg_replace('/\s+/u', ' ', (string)$base);
    $base = trim((string)$base);

    if ($base === '') {
        $base = 'Materiale docente';
    }

    return local_aisn_cb_clean_section_title($base, 120);
}

function local_aisn_cb_prompt_wants_one_section_per_file(string $prompt): bool {
    $p = local_aisn_cb_low($prompt);

    return (
        (str_contains($p, 'per ogni file') || str_contains($p, 'ogni file') || str_contains($p, 'ciascun file') || str_contains($p, 'ogni materiale'))
        && (str_contains($p, 'sezione') || str_contains($p, 'sezioni'))
    ) || str_contains($p, 'un file per sezione')
      || str_contains($p, 'uno per sezione')
      || str_contains($p, 'una sezione per file')
      || str_contains($p, 'una sezione per ogni file')
      || str_contains($p, 'dividi i file')
      || str_contains($p, 'organizza i file');
}

function local_aisn_cb_extract_delete_target(string $prompt): ?string {
    if (local_aisn_cb_prompt_wants_clear_course($prompt)) {
        return '__ALL_SECTIONS__';
    }

    $patterns = [
        '/\b(?:togli|elimina|cancella|rimuovi|rimuovere|cancellare|eliminare)\s+(?:la\s+)?sezione\s+["“”\'«»]?([^"“”\'«»\r\n]+)["“”\'«»]?/iu',
        '/\b(?:togli|elimina|cancella|rimuovi)\s+["“”\'«»]([^"“”\'«»\r\n]+)["“”\'«»]/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $matches)) {
            $target = local_aisn_cb_command_title_clean((string)$matches[1]);
            $target = preg_replace('/\s+(?:dal|del|nel)\s+corso.*$/iu', '', (string)$target);
            $target = trim((string)$target, " \t\n\r\0\x0B\"'“”«».:;");

            return $target !== '' ? $target : null;
        }
    }

    return null;
}

function local_aisn_cb_extract_target_section(string $prompt): ?string {
    $named = local_aisn_cb_extract_named_section_title($prompt);

    if ($named !== null) {
        return $named;
    }

    $patterns = [
        '/(?:nella|alla|dentro la|sotto la|in)\s+sezione\s+["“”\'«»]?([^"“”\'«».:,;\n\r]+)["“”\'«»]?/iu',
        '/sezione\s+["“”\'«»]([^"“”\'«»]+)["“”\'«»]/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $matches)) {
            $target = local_aisn_cb_command_title_clean((string)$matches[1]);

            return $target !== '' ? $target : null;
        }
    }

    return null;
}

function local_aisn_cb_extract_create_section_titles(string $prompt): array {
    $titles = [];
    $named = local_aisn_cb_extract_named_section_title($prompt);

    if ($named !== null) {
        $titles[local_aisn_cb_low($named)] = $named;
    }

    $patterns = [
        '/(?:crea|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\s+["“”\'«»]([^"“”\'«»]+)["“”\'«»]/iu',
        '/(?:crea|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\s+([^\.;\n\r]+)/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $prompt, $matches)) {
            foreach ($matches[1] as $raw) {
                $title = local_aisn_cb_command_title_clean((string)$raw);
                $low = local_aisn_cb_low($title);

                if ($title !== '' && !in_array($low, ['per ogni file', 'per ogni materiale'], true)) {
                    $titles[$low] = $title;
                }
            }
        }
    }

    return array_values($titles);
}

function local_aisn_cb_prompt_text_to_html(string $text): string {
    $text = trim((string)$text);

    if ($text === '') {
        return '';
    }

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/[ \t]+/u", " ", (string)$text);
    $text = trim((string)$text);

    return '<div class="aisn-cb-prompt-summary">' . nl2br(s($text), false) . '</div>';
}

function local_aisn_cb_set_section_summary_html(stdClass $section, string $html): void {
    global $DB;

    $section->summary = $html;
    $section->summaryformat = FORMAT_HTML;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);
    rebuild_course_cache((int)$section->course, true);
}

function local_aisn_cb_extract_text_section_request(string $prompt): ?array {
    $patterns = [
        '/\b(?:crea|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\s+(?:chiamata|chiamato|di nome|intitolata|intitolato)?\s*["“”\'«»]?(.+?)["“”\'«»]?\s+(?:mettendoci|mettici|metti|inserendo|con|contenente|che contiene)\s+(?:questo\s+)?(?:testo|contenuto)\s*:?\s*([\s\S]+)/iu',
        '/\b(?:crea|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\s+["“”\'«»]?([^"“”\'«»\r\n:]+)["“”\'«»]?\s*:\s*([\s\S]+)/iu',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match($pattern, $prompt, $matches)) {
            continue;
        }

        $title = (string)($matches[1] ?? '');
        $title = preg_replace('/\s+(?:mettendoci|mettici|metti|inserendo|con|contenente|che contiene).*$/iu', '', $title);
        $title = local_aisn_cb_command_title_clean($title);

        $body = trim((string)($matches[2] ?? ''));

        if ($title !== '' && $body !== '') {
            return [
                'title' => $title,
                'body' => $body,
            ];
        }
    }

    return null;
}

function local_aisn_cb_prompt_wants_existing_file_routing(string $prompt): bool {
    $p = local_aisn_cb_low($prompt);

    return (
        str_contains($p, 'sezioni già create') ||
        str_contains($p, 'sezioni gia create') ||
        str_contains($p, 'sezioni esistenti') ||
        str_contains($p, 'sezione esistente') ||
        str_contains($p, 'già create') ||
        str_contains($p, 'gia create') ||
        str_contains($p, 'relative sezioni') ||
        str_contains($p, 'sezione corretta') ||
        str_contains($p, 'nome del file') ||
        str_contains($p, 'nomi dei file') ||
        str_contains($p, 'in base al file') ||
        str_contains($p, 'in base al nome') ||
        str_contains($p, 'per argomento') ||
        str_contains($p, 'per lecture') ||
        str_contains($p, 'lecture')
    ) && local_aisn_cb_prompt_mentions_files_or_materials($prompt);
}

function local_aisn_cb_find_section_by_candidates(int $courseid, array $candidates): ?stdClass {
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);

        if ($candidate === '') {
            continue;
        }

        $section = local_aisn_cb_find_section($courseid, $candidate);

        if ($section) {
            return $section;
        }
    }

    return null;
}

function local_aisn_cb_existing_section_for_filename(int $courseid, string $filename): ?stdClass {
    $filename = clean_param($filename, PARAM_FILE);
    $base = preg_replace('/\.[^.]+$/', '', $filename);
    $low = local_aisn_cb_low((string)$base);

    $candidates = [];

    if (preg_match('/lecture[-_\s]*0*([0-9]+)/iu', $low, $m)) {
        $num = str_pad((string)((int)$m[1]), 2, '0', STR_PAD_LEFT);
        $candidates[] = 'Lecture ' . $num;
        $candidates[] = 'Lecture ' . ((int)$m[1]);

        if ($num === '00') {
            $candidates[] = 'Lecture 00 - Introduzione al corso';
            $candidates[] = 'Lecture 00 - Course overview';
            $candidates[] = 'Introduzione';
            $candidates[] = 'Introduzione al corso';
        }

        if ($num === '01') {
            $candidates[] = 'Lecture 01 - Introduzione alla gestione dei dati';
        }

        if ($num === '02') {
            $candidates[] = 'Lecture 02 - Business Intelligence e Big Data';
            $candidates[] = 'Business Intelligence e Big Data';
            $candidates[] = 'Big Data';
        }

        if ($num === '03') {
            $candidates[] = 'Lecture 03 - IoT e scenari di generazione dati';
            $candidates[] = 'IoT';
        }

        if ($num === '04') {
            $candidates[] = 'Lecture 04 - NoSQL';
            $candidates[] = 'NoSQL';
        }

        if ($num === '05') {
            $candidates[] = 'Lecture 05 - Modelli di computazione';
            $candidates[] = 'Modelli di computazione';
        }

        if ($num === '06') {
            $candidates[] = 'Lecture 06 - Streaming processing';
            $candidates[] = 'Streaming';
        }

        if ($num === '07') {
            $candidates[] = 'Lecture 07 - Message brokers';
            $candidates[] = 'Message brokers';
        }
    }

    if (str_contains($low, 'exam')) {
        $candidates[] = 'Exams';
        $candidates[] = 'Esami e progetto';
    }

    if (str_contains($low, 'project')) {
        $candidates[] = 'Esami e progetto';
        $candidates[] = 'Lecture 00 - Introduzione al corso';
        $candidates[] = 'Lecture 00';
    }

    $clean = preg_replace('/^tbdm[-_\s]*2025[-_\s]*26[-_\s]*/iu', '', (string)$base);
    $clean = str_replace(['_', '-'], ' ', (string)$clean);
    $clean = preg_replace('/\s+/u', ' ', (string)$clean);
    $clean = trim((string)$clean);

    if ($clean !== '') {
        $candidates[] = $clean;
    }

    return local_aisn_cb_find_section_by_candidates($courseid, $candidates);
}

function local_aisn_cb_attach_files_to_existing_sections(int $courseid, int $userid, array $files): array {
    $logs = [];
    $files = local_aisn_cb_dedupe_files($files);

    foreach ($files as $file) {
        $filename = clean_param((string)($file['name'] ?? ''), PARAM_FILE);

        if ($filename === '') {
            $filename = 'file docente';
        }

        $section = local_aisn_cb_existing_section_for_filename($courseid, $filename);

        if (!$section) {
            $logs[] = 'File "' . $filename . '" non collegato: nessuna sezione esistente compatibile trovata. Nessuna nuova sezione creata.';
            continue;
        }

        $logs[] = 'Azione diretta: file "' . $filename . '" assegnato alla sezione esistente "' . (string)$section->name . '".';
        $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, [$file]));
    }

    local_aisn_cb_sync_resources($courseid);
    $logs[] = 'RAG sync saltato in modalità demo veloce.';

    return $logs;
}

// AISN_EXISTING_SECTION_ROUTING_FIX
function local_aisn_cb_safe_title_clean(string $title): string {
    $title = trim((string)$title);
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $title = preg_replace('/^\s*(?:la|il|lo|una|un)\s+/iu', '', $title);
    $title = preg_replace('/^\s*(?:sezione|section)\s+/iu', '', $title);
    $title = preg_replace('/^\s*(?:chiamata|chiamato|di nome|intitolata|intitolato)\s+/iu', '', $title);

    $title = preg_replace('/\s+(?:e\s+)?(?:per quanto riguarda|mettendoci|mettici|metti|inserendo|contenente|che contiene|con questo testo|con il testo|con contenuto|con il contenuto)\b[\s\S]*$/iu', '', $title);
    $title = preg_replace('/\s+(?:non creare|senza creare|limitati|solo|soltanto)\b[\s\S]*$/iu', '', $title);

    $title = trim((string)$title, " \t\n\r\0\x0B\"'“”‘’«».,:;");

    if (function_exists('local_aisn_cb_command_title_clean')) {
        $title = local_aisn_cb_command_title_clean($title);
    }

    $title = trim((string)$title, " \t\n\r\0\x0B\"'“”‘’«».,:;");

    if ($title === '') {
        return '';
    }

    if (core_text::strlen($title) > 90) {
        $title = core_text::substr($title, 0, 90);
    }

    return trim((string)$title);
}

function local_aisn_cb_safe_is_conservative(string $prompt): bool {
    $p = local_aisn_cb_low($prompt);

    return str_contains($p, 'non creare nuove sezioni')
        || str_contains($p, 'non creare altre sezioni')
        || str_contains($p, 'senza creare nuove sezioni')
        || str_contains($p, 'sezioni già create')
        || str_contains($p, 'sezioni gia create')
        || str_contains($p, 'sezioni esistenti')
        || str_contains($p, 'sezione esistente')
        || str_contains($p, 'in base al nome del file')
        || str_contains($p, 'in base ai nomi dei file')
        || str_contains($p, 'in base all argomento')
        || str_contains($p, "in base all'argomento")
        || str_contains($p, 'sezione corretta')
        || str_contains($p, 'sezioni corrette');
}

function local_aisn_cb_safe_wants_create_section(string $prompt): bool {
    return preg_match('/\b(?:crea|creami|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\b/iu', $prompt) === 1;
}

function local_aisn_cb_safe_wants_one_section_per_file(string $prompt): bool {
    $p = local_aisn_cb_low($prompt);

    return str_contains($p, 'una sezione per ogni file')
        || str_contains($p, 'una sezione per file')
        || str_contains($p, 'ogni file in una sezione')
        || str_contains($p, 'dividi i file in sezioni')
        || str_contains($p, 'crea sezioni dai file');
}

function local_aisn_cb_safe_extract_text_section(string $prompt): ?array {
    $patterns = [
        '/\b(?:crea|creami|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\s+(?:chiamata|chiamato|di nome|intitolata|intitolato)?\s*["“”\'«»]?(.+?)["“”\'«»]?\s+(?:mettendoci|mettici|metti|inserendo|contenente|che contiene|con)\s+(?:questo\s+)?(?:testo|contenuto)?\s*:?\s*([\s\S]+)/iu',
        '/\b(?:crea|creami|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\s+["“”\'«»]?([^"“”\'«»\r\n:]+)["“”\'«»]?\s*:\s*([\s\S]+)/iu',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match($pattern, $prompt, $matches)) {
            continue;
        }

        $title = local_aisn_cb_safe_title_clean((string)($matches[1] ?? ''));
        $body = trim((string)($matches[2] ?? ''));

        if ($title !== '' && $body !== '') {
            return [
                'title' => $title,
                'body' => $body,
            ];
        }
    }

    return null;
}

function local_aisn_cb_safe_body_to_html(string $body): string {
    $body = trim((string)$body);
    $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = preg_replace("/[ \t]+/u", " ", (string)$body);

    $lines = preg_split('/\n/u', $body);
    $html = '<div class="aisn-coursebuilder-text">';
    $inlist = false;

    foreach ($lines as $line) {
        $line = trim((string)$line);

        if ($line === '') {
            if ($inlist) {
                $html .= '</ul>';
                $inlist = false;
            }
            continue;
        }

        $isheading = preg_match('/^(?:Exam Dates|Exam rules|Exam Results|Course Objectives|Syllabus|General Info|Study material|Reference materials|Teacher|Course|Degrees|Curricula)\b.*:?$/iu', $line)
            || preg_match('/^[\p{L}0-9 .\/\-]+:\s*$/u', $line);

        $isdate = preg_match('/^\d{2}\/\d{2}\/\d{4}$/u', $line);
        $isbullet = preg_match('/^(?:[-*•]\s+|\d+\.\s+)/u', $line);

        if ($isheading) {
            if ($inlist) {
                $html .= '</ul>';
                $inlist = false;
            }

            $html .= '<h4>' . s(rtrim($line, ':')) . '</h4>';
            continue;
        }

        if ($isdate || $isbullet) {
            if (!$inlist) {
                $html .= '<ul>';
                $inlist = true;
            }

            $line = preg_replace('/^(?:[-*•]\s+|\d+\.\s+)/u', '', $line);
            $html .= '<li>' . s($line) . '</li>';
            continue;
        }

        if ($inlist) {
            $html .= '</ul>';
            $inlist = false;
        }

        $html .= '<p>' . s($line) . '</p>';
    }

    if ($inlist) {
        $html .= '</ul>';
    }

    $html .= '</div>';

    return $html;
}

function local_aisn_cb_safe_set_section_summary(stdClass $section, string $html): void {
    global $DB;

    $section->summary = $html;
    $section->summaryformat = FORMAT_HTML;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);
    rebuild_course_cache((int)$section->course, true);
}

function local_aisn_cb_safe_extract_target(string $prompt): ?string {
    $patterns = [
        '/\b(?:nella sezione|alla sezione|dentro la sezione|in sezione)\s+["“”\'«»]([^"“”\'«»]+)["“”\'«»]/iu',
        '/\b(?:nella|alla|dentro la)\s+sezione\s+([^.;\r\n]+?)(?:\s+non creare|\s+senza creare|[.;\r\n]|$)/iu',
        '/\b(?:sezione|section)\s+["“”\'«»]([^"“”\'«»]+)["“”\'«»]/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $matches)) {
            $target = local_aisn_cb_safe_title_clean((string)$matches[1]);

            if ($target !== '') {
                return $target;
            }
        }
    }

    if (function_exists('local_aisn_cb_extract_target_section')) {
        $target = local_aisn_cb_extract_target_section($prompt);

        if ($target !== null) {
            $target = local_aisn_cb_safe_title_clean((string)$target);

            if ($target !== '') {
                return $target;
            }
        }
    }

    return null;
}

function local_aisn_cb_safe_section_candidates_for_file(string $filename): array {
    $filename = clean_param($filename, PARAM_FILE);
    $base = preg_replace('/\.[^.]+$/', '', $filename);
    $low = local_aisn_cb_low((string)$base);

    $candidates = [];

    if (preg_match('/lecture[-_\s]*0*([0-9]+)/iu', $low, $m)) {
        $n = (int)$m[1];
        $nn = str_pad((string)$n, 2, '0', STR_PAD_LEFT);

        $candidates[] = 'Lecture ' . $nn;
        $candidates[] = 'Lecture ' . $n;

        if ($n === 0) {
            $candidates[] = 'Lecture 00 - Introduzione al corso';
            $candidates[] = 'Lecture 00 - Course overview';
            $candidates[] = 'Introduzione al corso';
        } else if ($n === 1) {
            $candidates[] = 'Lecture 01 - Introduzione alla gestione dei dati';
            $candidates[] = 'Lecture 01';
        } else if ($n === 2) {
            $candidates[] = 'Lecture 02 - Business Intelligence e Big Data';
            $candidates[] = 'Business Intelligence e Big Data';
            $candidates[] = 'Big Data';
        } else if ($n === 3) {
            $candidates[] = 'Lecture 03 - IoT e scenari di generazione dati';
            $candidates[] = 'IoT';
        } else if ($n === 4) {
            $candidates[] = 'Lecture 04 - NoSQL';
            $candidates[] = 'NoSQL';
        } else if ($n === 5) {
            $candidates[] = 'Lecture 05 - Modelli di computazione';
            $candidates[] = 'Lecture 05 - Computation models';
            $candidates[] = 'Computation models';
            $candidates[] = 'Modelli di computazione';
        } else if ($n === 6) {
            $candidates[] = 'Lecture 06 - Streaming processing';
            $candidates[] = 'Lecture 06 streaming';
            $candidates[] = 'Streaming';
        } else if ($n === 7) {
            $candidates[] = 'Lecture 07 - Message brokers';
            $candidates[] = 'Lecture 07 message broker';
            $candidates[] = 'Message broker';
            $candidates[] = 'Message brokers';
        }
    }

    if (str_contains($low, 'exam')) {
        $candidates[] = 'Exams';
        $candidates[] = 'Esami e progetto';
    }

    if (str_contains($low, 'project')) {
        $candidates[] = 'Esami e progetto';
        $candidates[] = 'Lecture 00 - Introduzione al corso';
        $candidates[] = 'Lecture 00';
    }

    $clean = preg_replace('/^tbdm[-_\s]*2025[-_\s]*26[-_\s]*/iu', '', (string)$base);
    $clean = str_replace(['_', '-'], ' ', (string)$clean);
    $clean = preg_replace('/\s+/u', ' ', (string)$clean);
    $clean = trim((string)$clean);

    if ($clean !== '') {
        $candidates[] = $clean;
    }

    return array_values(array_unique(array_filter($candidates)));
}

function local_aisn_cb_safe_find_section_for_file(int $courseid, string $filename): ?stdClass {
    foreach (local_aisn_cb_safe_section_candidates_for_file($filename) as $candidate) {
        $section = local_aisn_cb_find_section($courseid, $candidate);

        if ($section) {
            return $section;
        }
    }

    return null;
}

function local_aisn_cb_safe_attach_existing(int $courseid, int $userid, array $files): array {
    $logs = [];
    $files = local_aisn_cb_dedupe_files($files);

    foreach ($files as $file) {
        $filename = clean_param((string)($file['name'] ?? ''), PARAM_FILE);

        if ($filename === '') {
            $filename = 'file docente';
        }

        $section = local_aisn_cb_safe_find_section_for_file($courseid, $filename);

        if (!$section) {
            $logs[] = 'File "' . $filename . '" non collegato: nessuna sezione esistente compatibile trovata. Nessuna nuova sezione creata.';
            continue;
        }

        $logs[] = 'File "' . $filename . '" collegato alla sezione esistente "' . (string)$section->name . '".';
        $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, [$file]));
    }

    local_aisn_cb_sync_resources($courseid);
    $logs[] = 'Modalità sicura: nessuna sezione inventata dai nomi dei file.';

    return $logs;
}

function local_aisn_cb_safe_extract_section_list(string $prompt): array {
    $p = local_aisn_cb_low($prompt);

    if (
        !str_contains($p, 'crea queste sezioni') &&
        !str_contains($p, 'crea le seguenti sezioni') &&
        !str_contains($p, 'struttura di corso') &&
        !str_contains($p, 'struttura del corso')
    ) {
        return [];
    }

    $lines = preg_split('/\r\n|\r|\n/u', $prompt);
    $titles = [];

    foreach ($lines as $line) {
        $line = trim((string)$line);

        if ($line === '') {
            continue;
        }

        if (preg_match('/^\s*(?:\d+[\).:-]\s+|[-*•]\s+)(.+)$/u', $line, $matches)) {
            $title = local_aisn_cb_safe_title_clean((string)$matches[1]);

            if ($title !== '' && core_text::strlen($title) <= 100) {
                $titles[] = $title;
            }
        }
    }

    return array_values(array_unique($titles));
}

function local_aisn_cb_content_edit_clean_value(string $value): string {
    $value = trim((string)$value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value, " \t\n\r\0\x0B\"'“”‘’«».,;");

    return $value;
}

function local_aisn_cb_content_edit_clean_section_name(string $value): string {
    $value = local_aisn_cb_content_edit_clean_value($value);

    if (function_exists('local_aisn_cb_safe_title_clean')) {
        $value = local_aisn_cb_safe_title_clean($value);
    }

    return trim($value);
}

function local_aisn_cb_content_edit_extract(string $prompt): ?array {
    $prompt = trim((string)$prompt);

    $replacepatterns = [
        '/(?:nel|nella|dentro\s+il|all’interno\s+del|all\'interno\s+del)?\s*contenuto\s+della\s+sezione\s+["“”\'«»]?(.+?)["“”\'«»]?\s+sostituisci\s+(.+?)\s+con\s+(.+)$/iu',
        '/(?:nella\s+sezione|sezione)\s+["“”\'«»]?(.+?)["“”\'«»]?\s+sostituisci\s+(.+?)\s+con\s+(.+)$/iu',
    ];

    foreach ($replacepatterns as $pattern) {
        if (preg_match($pattern, $prompt, $m)) {
            $section = local_aisn_cb_content_edit_clean_section_name((string)($m[1] ?? ''));
            $from = local_aisn_cb_content_edit_clean_value((string)($m[2] ?? ''));
            $to = local_aisn_cb_content_edit_clean_value((string)($m[3] ?? ''));

            if ($section !== '' && $from !== '') {
                return [
                    'action' => 'replace',
                    'section' => $section,
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }
    }

    $removepatterns = [
        '/(?:nel|nella|dentro\s+il|all’interno\s+del|all\'interno\s+del)?\s*contenuto\s+della\s+sezione\s+["“”\'«»]?(.+?)["“”\'«»]?\s+(?:togli|rimuovi|elimina|cancella)\s+(.+)$/iu',
        '/(?:togli|rimuovi|elimina|cancella)\s+(.+?)\s+(?:dal|dalla)\s+contenuto\s+della\s+sezione\s+["“”\'«»]?(.+?)["“”\'«»]?$/iu',
        '/(?:togli|rimuovi|elimina|cancella)\s+(.+?)\s+(?:dalla|nella)\s+sezione\s+["“”\'«»]?(.+?)["“”\'«»]?$/iu',
    ];

    foreach ($removepatterns as $i => $pattern) {
        if (preg_match($pattern, $prompt, $m)) {
            if ($i === 0) {
                $section = local_aisn_cb_content_edit_clean_section_name((string)($m[1] ?? ''));
                $needle = local_aisn_cb_content_edit_clean_value((string)($m[2] ?? ''));
            } else {
                $needle = local_aisn_cb_content_edit_clean_value((string)($m[1] ?? ''));
                $section = local_aisn_cb_content_edit_clean_section_name((string)($m[2] ?? ''));
            }

            if ($section !== '' && $needle !== '') {
                return [
                    'action' => 'remove',
                    'section' => $section,
                    'needle' => $needle,
                ];
            }
        }
    }

    return null;
}

function local_aisn_cb_content_edit_repair_flat_summary(string $summary): string {
    $hasstructure = preg_match('/<(h[1-6]|ul|ol|li|p|div)\b/i', $summary) === 1;

    if ($hasstructure && preg_match('/<(h[1-6]|ul|ol|li)\b/i', $summary) === 1) {
        return preg_replace('/[ \t]{2,}/u', ' ', $summary);
    }

    $plain = trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($plain === '') {
        return $summary;
    }

    $looksflat = preg_match('/\b(Exam Dates|Exam rules|Exam Results|Course Objectives|Syllabus|General Info|Study material|Reference materials)\b/iu', $plain) === 1;

    if (!$looksflat) {
        return $summary;
    }

    $plain = preg_replace('/\s+/u', ' ', $plain);

    $plain = preg_replace('/\b(Exam Dates\s+A\.Y\.\s*[0-9\/]+)(?:\s*\(tentative\))?/iu', "$1\n", $plain);
    $plain = preg_replace('/\s+(Exam rules)\b:?/iu', "\n\n$1:\n", $plain);
    $plain = preg_replace('/\s+(Exam Results)\b:?/iu', "\n\n$1:\n", $plain);
    $plain = preg_replace('/\s+(Course Objectives|Syllabus|General Info|Study material|Reference materials)\b:?/iu', "\n\n$1:\n", $plain);

    $plain = preg_replace('/\s+(\d{2}\/\d{2}\/\d{4})\b/u', "\n$1", $plain);
    $plain = preg_replace('/\s+(Writing Examination)\b/iu', "\n- $1", $plain);
    $plain = preg_replace('/\s+(Project lab[^\\n]*?team)\b/iu', "\n- $1", $plain);
    $plain = preg_replace('/\s+(See news section)\b/iu', "\n$1", $plain);

    if (function_exists('local_aisn_cb_safe_body_to_html')) {
        return local_aisn_cb_safe_body_to_html($plain);
    }

    return '<div>' . nl2br(s($plain), false) . '</div>';
}

function local_aisn_cb_content_edit_update_summary(stdClass $section, string $summary): void {
    global $DB;

    $section->summary = $summary;
    $section->summaryformat = FORMAT_HTML;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);
    rebuild_course_cache((int)$section->course, true);
}

function local_aisn_cb_content_edit_try_handle(int $courseid, string $prompt, array $files): ?array {
    if (!empty($files)) {
        return null;
    }

    $edit = local_aisn_cb_content_edit_extract($prompt);

    if ($edit === null) {
        return null;
    }

    $sectionname = (string)$edit['section'];
    $section = local_aisn_cb_find_section($courseid, $sectionname);

    if (!$section) {
        return [
            'Modifica contenuto: sezione "' . $sectionname . '" non trovata.',
            'Nessuna nuova sezione creata.',
        ];
    }

    $summary = (string)($section->summary ?? '');
    $before = $summary;

    if ((string)$edit['action'] === 'remove') {
        $needle = (string)$edit['needle'];
        $pattern = '/\s*' . preg_quote($needle, '/') . '\s*/iu';
        $summary = preg_replace($pattern, ' ', $summary);
        $summary = preg_replace('/[ \t]{2,}/u', ' ', (string)$summary);
        $summary = local_aisn_cb_content_edit_repair_flat_summary((string)$summary);

        local_aisn_cb_content_edit_update_summary($section, $summary);

        return [
            'Modifica contenuto: rimosso "' . $needle . '" dalla sezione "' . (string)$section->name . '".',
            'Formattazione sezione preservata/riparata.',
            'Nessuna nuova sezione creata.',
        ];
    }

    if ((string)$edit['action'] === 'replace') {
        $from = (string)$edit['from'];
        $to = s((string)$edit['to']);
        $pattern = '/' . preg_quote($from, '/') . '/iu';
        $summary = preg_replace($pattern, $to, $summary);
        $summary = local_aisn_cb_content_edit_repair_flat_summary((string)$summary);

        local_aisn_cb_content_edit_update_summary($section, $summary);

        return [
            'Modifica contenuto: sostituito "' . $from . '" nella sezione "' . (string)$section->name . '".',
            'Formattazione sezione preservata/riparata.',
            'Nessuna nuova sezione creata.',
        ];
    }

    return null;
}

// AISN_CB_CONTENT_EDIT_PATCH
function local_aisn_cb_brutal_content_clean_value(string $value): string {
    $value = trim((string)$value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value, " \t\n\r\0\x0B\"'“”‘’«».,;:");

    return $value;
}

function local_aisn_cb_brutal_content_clean_section(string $value): string {
    $value = local_aisn_cb_brutal_content_clean_value($value);

    if (function_exists('local_aisn_cb_safe_title_clean')) {
        $value = local_aisn_cb_safe_title_clean($value);
    }

    return trim($value);
}

function local_aisn_cb_brutal_find_section(int $courseid, string $sectionname): ?stdClass {
    global $DB;

    $sectionname = trim($sectionname);

    if ($sectionname === '') {
        return null;
    }

    $section = local_aisn_cb_find_section($courseid, $sectionname);

    if ($section) {
        return $section;
    }

    $wanted = core_text::strtolower(trim($sectionname));

    try {
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');

        foreach ($sections as $candidate) {
            $name = core_text::strtolower(trim((string)($candidate->name ?? '')));

            if ($name !== '' && $name === $wanted) {
                return $candidate;
            }
        }
    } catch (Throwable $e) {
        debugging('AI Course Builder section fallback lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return null;
}

function local_aisn_cb_brutal_content_extract(string $prompt): ?array {
    $prompt = trim((string)$prompt);
    $removeverb = '(?:eliminami|eliminiami|elimina|rimuovimi|rimuovi|toglimi|togli|cancellami|cancella|levami|leva)';

    $repairpatterns = [
        '/^(?:ripara|formatta|sistema)\s+(?:il\s+)?contenuto\s+(?:della\s+)?sezione\s+["“”\'«»]?(.+?)["“”\'«»]?\s*$/iu',
        '/^(?:ripara|formatta|sistema)\s+(?:la\s+)?sezione\s+["“”\'«»]?(.+?)["“”\'«»]?\s*$/iu',
    ];

    foreach ($repairpatterns as $pattern) {
        if (preg_match($pattern, $prompt, $m)) {
            $section = local_aisn_cb_brutal_content_clean_section((string)($m[1] ?? ''));

            if ($section !== '') {
                return [
                    'action' => 'repair',
                    'section' => $section,
                ];
            }
        }
    }

    $replacepatterns = [
        '/^(?:nel|nella|dentro\s+il)?\s*contenuto\s+della\s+sezione\s+["“”\'«»]?(.+?)["“”\'«»]?\s+sostituisci\s+(.+?)\s+con\s+(.+)$/iu',
        '/^sostituisci\s+(.+?)\s+con\s+(.+?)\s+(?:nel|nella|dal|dalla)\s+(?:contenuto\s+(?:della\s+)?sezione|sezione)\s+["“”\'«»]?(.+?)["“”\'«»]?\s*$/iu',
    ];

    foreach ($replacepatterns as $i => $pattern) {
        if (preg_match($pattern, $prompt, $m)) {
            if ($i === 0) {
                $section = local_aisn_cb_brutal_content_clean_section((string)($m[1] ?? ''));
                $from = local_aisn_cb_brutal_content_clean_value((string)($m[2] ?? ''));
                $to = local_aisn_cb_brutal_content_clean_value((string)($m[3] ?? ''));
            } else {
                $from = local_aisn_cb_brutal_content_clean_value((string)($m[1] ?? ''));
                $to = local_aisn_cb_brutal_content_clean_value((string)($m[2] ?? ''));
                $section = local_aisn_cb_brutal_content_clean_section((string)($m[3] ?? ''));
            }

            if ($section !== '' && $from !== '') {
                return [
                    'action' => 'replace',
                    'section' => $section,
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }
    }

    $removepatterns = [
        '/^(?:nel|nella|dentro\s+il)?\s*contenuto\s+della\s+sezione\s+["“”\'«»]?(.+?)["“”\'«»]?\s+' . $removeverb . '\s+(.+)$/iu',
        '/^' . $removeverb . '\s+(.+?)\s+(?:dal|dalla|nel|nella)\s+(?:contenuto\s+(?:della\s+)?sezione|sezione)\s+["“”\'«»]?(.+?)["“”\'«»]?\s*$/iu',
        '/^' . $removeverb . '\s+(.+?)\s+(?:dalla|nella)\s+sezione\s+["“”\'«»]?(.+?)["“”\'«»]?\s*$/iu',
    ];

    foreach ($removepatterns as $i => $pattern) {
        if (preg_match($pattern, $prompt, $m)) {
            if ($i === 0) {
                $section = local_aisn_cb_brutal_content_clean_section((string)($m[1] ?? ''));
                $needle = local_aisn_cb_brutal_content_clean_value((string)($m[2] ?? ''));
            } else {
                $needle = local_aisn_cb_brutal_content_clean_value((string)($m[1] ?? ''));
                $section = local_aisn_cb_brutal_content_clean_section((string)($m[2] ?? ''));
            }

            if ($section !== '' && $needle !== '') {
                return [
                    'action' => 'remove',
                    'section' => $section,
                    'needle' => $needle,
                ];
            }
        }
    }

    return null;
}

function local_aisn_cb_brutal_remove_text(string $summary, string $needle): string {
    $needle = local_aisn_cb_brutal_content_clean_value($needle);

    if ($needle === '') {
        return $summary;
    }

    $quoted = preg_quote($needle, '/');
    $quoted = preg_replace('/\\\s+/u', '\\s+', $quoted);

    $newsummary = preg_replace('/\s*' . $quoted . '\s*/iu', ' ', $summary);

    if ($newsummary !== null && $newsummary !== $summary) {
        return (string)$newsummary;
    }

    $plain = trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($plain !== '') {
        $plainquoted = preg_quote($needle, '/');
        $plainquoted = preg_replace('/\\\s+/u', '\\s+', $plainquoted);
        $plain = preg_replace('/\s*' . $plainquoted . '\s*/iu', ' ', $plain);
        return '<div>' . s(trim((string)$plain)) . '</div>';
    }

    return $summary;
}

function local_aisn_cb_brutal_repair_summary(string $summary): string {
    $summary = trim((string)$summary);

    if ($summary === '') {
        return $summary;
    }

    $hasgoodstructure = preg_match('/<(h[1-6]|ul|ol|li)\b/i', $summary) === 1;

    if ($hasgoodstructure) {
        $summary = preg_replace('/<li>\s*<\/li>/iu', '', $summary);
        $summary = preg_replace('/[ \t]{2,}/u', ' ', (string)$summary);
        return (string)$summary;
    }

    $plain = trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($plain === '') {
        return $summary;
    }

    $plain = preg_replace('/\s+/u', ' ', $plain);

    if (preg_match('/\bExam Dates\b/iu', $plain) || preg_match('/\bExam rules\b/iu', $plain) || preg_match('/\bExam Results\b/iu', $plain)) {
        $title = 'Exam Dates';
        if (preg_match('/(Exam Dates\s+A\.Y\.\s*[0-9\/]+)/iu', $plain, $m)) {
            $title = trim($m[1]);
        }

        preg_match_all('/\d{2}\/\d{2}\/\d{4}/u', $plain, $dmatches);
        $dates = array_values(array_unique($dmatches[0] ?? []));

        $rules = [];
        if (preg_match('/\bWriting Examination\b/iu', $plain)) {
            $rules[] = 'Writing Examination';
        }
        if (preg_match('/\bProject lab\b[^E]*/iu', $plain, $pm)) {
            $project = trim((string)$pm[0]);
            $project = preg_replace('/\s+Exam\s*Results.*$/iu', '', $project);
            $project = preg_replace('/\s+See\s+news\s+section.*$/iu', '', $project);
            if ($project !== '') {
                $rules[] = $project;
            }
        }

        $resulttext = '';
        if (preg_match('/\bExam Results\b\s*:?\s*(.+)$/iu', $plain, $rm)) {
            $resulttext = trim((string)$rm[1]);
        } else if (preg_match('/\bSee news section\b/iu', $plain)) {
            $resulttext = 'See news section';
        }

        $html = '<div class="aisn-coursebuilder-text">';
        $html .= '<h4>' . s($title) . '</h4>';

        if (!empty($dates)) {
            $html .= '<ul>';
            foreach ($dates as $date) {
                $html .= '<li>' . s($date) . '</li>';
            }
            $html .= '</ul>';
        }

        if (!empty($rules)) {
            $html .= '<h4>Exam rules</h4><ul>';
            foreach (array_values(array_unique($rules)) as $rule) {
                $html .= '<li>' . s($rule) . '</li>';
            }
            $html .= '</ul>';
        }

        if ($resulttext !== '') {
            $html .= '<h4>Exam Results</h4>';
            $html .= '<p>' . s($resulttext) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    if (function_exists('local_aisn_cb_safe_body_to_html')) {
        return local_aisn_cb_safe_body_to_html($plain);
    }

    return '<div>' . nl2br(s($plain), false) . '</div>';
}

function local_aisn_cb_brutal_update_summary(stdClass $section, string $summary): void {
    global $DB;

    $section->summary = $summary;
    $section->summaryformat = FORMAT_HTML;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);
    rebuild_course_cache((int)$section->course, true);
}

function local_aisn_cb_brutal_content_edit_try_handle(int $courseid, string $prompt, array $files): ?array {
    if (!empty($files)) {
        return null;
    }

    $edit = local_aisn_cb_brutal_content_extract($prompt);

    if ($edit === null) {
        return null;
    }

    $sectionname = (string)$edit['section'];
    $section = local_aisn_cb_brutal_find_section($courseid, $sectionname);

    if (!$section) {
        return [
            'Modifica contenuto: sezione "' . $sectionname . '" non trovata.',
            'Nessuna nuova sezione creata.',
        ];
    }

    $summary = (string)($section->summary ?? '');

    if ((string)$edit['action'] === 'repair') {
        $summary = local_aisn_cb_brutal_repair_summary($summary);
        local_aisn_cb_brutal_update_summary($section, $summary);

        return [
            'Modifica contenuto: formattazione riparata nella sezione "' . (string)$section->name . '".',
            'Nessuna nuova sezione creata.',
        ];
    }

    if ((string)$edit['action'] === 'remove') {
        $needle = (string)$edit['needle'];
        $summary = local_aisn_cb_brutal_remove_text($summary, $needle);
        $summary = local_aisn_cb_brutal_repair_summary($summary);
        local_aisn_cb_brutal_update_summary($section, $summary);

        return [
            'Modifica contenuto: rimosso "' . $needle . '" dalla sezione "' . (string)$section->name . '".',
            'Formattazione sezione preservata/riparata.',
            'Nessuna nuova sezione creata.',
        ];
    }

    if ((string)$edit['action'] === 'replace') {
        $from = (string)$edit['from'];
        $to = s((string)$edit['to']);
        $quoted = preg_quote($from, '/');
        $quoted = preg_replace('/\\\s+/u', '\\s+', $quoted);
        $summary = preg_replace('/' . $quoted . '/iu', $to, $summary);
        $summary = local_aisn_cb_brutal_repair_summary((string)$summary);
        local_aisn_cb_brutal_update_summary($section, $summary);

        return [
            'Modifica contenuto: sostituito "' . $from . '" nella sezione "' . (string)$section->name . '".',
            'Formattazione sezione preservata/riparata.',
            'Nessuna nuova sezione creata.',
        ];
    }

    return null;
}

// AISN_CB_BRUTAL_CONTENT_EDIT_PATCH
function local_aisn_cb_ui_editor_v2_low(string $value): string {
    $value = trim((string)$value);

    if (class_exists('core_text')) {
        return core_text::strtolower($value);
    }

    return strtolower($value);
}

function local_aisn_cb_ui_editor_v2_clean(string $value): string {
    $value = trim((string)$value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value, " \t\n\r\0\x0B\"'“”‘’«».,;:");

    return $value;
}

function local_aisn_cb_ui_editor_v2_clean_section(string $value): string {
    $value = local_aisn_cb_ui_editor_v2_clean($value);

    if (function_exists('local_aisn_cb_safe_title_clean')) {
        $value = local_aisn_cb_safe_title_clean($value);
    }

    return trim($value);
}

function local_aisn_cb_ui_editor_v2_find_section(int $courseid, string $sectionname): ?stdClass {
    global $DB;

    $sectionname = local_aisn_cb_ui_editor_v2_clean_section($sectionname);

    if ($sectionname === '') {
        return null;
    }

    $section = local_aisn_cb_find_section($courseid, $sectionname);

    if ($section) {
        return $section;
    }

    $wanted = local_aisn_cb_ui_editor_v2_low($sectionname);

    try {
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');

        foreach ($sections as $candidate) {
            $name = local_aisn_cb_ui_editor_v2_low(trim((string)($candidate->name ?? '')));

            if ($name !== '' && $name === $wanted) {
                return $candidate;
            }
        }
    } catch (Throwable $e) {
        debugging('AI Course Builder section lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return null;
}

function local_aisn_cb_ui_editor_v2_section_from_prompt(int $courseid, string $prompt): ?stdClass {
    $patterns = [
        '/(?:contenuto\s+della\s+sezione|sezione)\s+["“”\'«»]([^"“”\'«»]+)["“”\'«»]/iu',
        '/(?:contenuto\s+della\s+sezione|sezione)\s+([A-Za-z0-9À-ÿ _\-.]+?)(?:\s+dal|\s+nel|\s+con|\s+e\s+|\s*$)/iu',
        '/(?:dal|dalla|nel|nella)\s+(?:contenuto\s+(?:della\s+)?sezione|sezione)\s+["“”\'«»]?([A-Za-z0-9À-ÿ _\-.]+)["“”\'«»]?/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $matches)) {
            $sectionname = local_aisn_cb_ui_editor_v2_clean_section((string)($matches[1] ?? ''));

            if ($sectionname !== '') {
                $section = local_aisn_cb_ui_editor_v2_find_section($courseid, $sectionname);

                if ($section) {
                    return $section;
                }
            }
        }
    }

    $p = local_aisn_cb_ui_editor_v2_low($prompt);

    if (
        str_contains($p, 'exam') ||
        str_contains($p, 'esami') ||
        str_contains($p, 'date') ||
        str_contains($p, 'tentative')
    ) {
        $section = local_aisn_cb_ui_editor_v2_find_section($courseid, 'Exams');

        if ($section) {
            return $section;
        }
    }

    return null;
}

function local_aisn_cb_ui_editor_v2_color_from_prompt(string $prompt): ?array {
    $p = local_aisn_cb_ui_editor_v2_low($prompt);

    $colors = [
        'rosso' => ['Rosso', '#dc2626'],
        'rossa' => ['Rosso', '#dc2626'],
        'red' => ['Rosso', '#dc2626'],
        'blu' => ['Blu', '#2563eb'],
        'blue' => ['Blu', '#2563eb'],
        'verde' => ['Verde', '#16a34a'],
        'green' => ['Verde', '#16a34a'],
        'arancione' => ['Arancione', '#ea580c'],
        'orange' => ['Arancione', '#ea580c'],
        'giallo' => ['Giallo', '#ca8a04'],
        'yellow' => ['Giallo', '#ca8a04'],
        'viola' => ['Viola', '#9333ea'],
        'purple' => ['Viola', '#9333ea'],
        'nero' => ['Nero', '#111827'],
        'black' => ['Nero', '#111827'],
    ];

    foreach ($colors as $word => $color) {
        if (str_contains($p, $word)) {
            return $color;
        }
    }

    return null;
}

function local_aisn_cb_ui_editor_v2_wants_color(string $prompt): bool {
    $p = local_aisn_cb_ui_editor_v2_low($prompt);

    return str_contains($p, 'colora')
        || str_contains($p, 'colorami')
        || str_contains($p, 'metti in rosso')
        || str_contains($p, 'metti in blu')
        || str_contains($p, 'rendi rosso')
        || str_contains($p, 'rendi rosse')
        || str_contains($p, 'evidenzia');
}

function local_aisn_cb_ui_editor_v2_wants_repair(string $prompt): bool {
    $p = local_aisn_cb_ui_editor_v2_low($prompt);

    return str_contains($p, 'ripara')
        || str_contains($p, 'formatta')
        || str_contains($p, 'sistema la formattazione')
        || str_contains($p, 'sistema il contenuto');
}

function local_aisn_cb_ui_editor_v2_wants_remove(string $prompt): bool {
    $p = local_aisn_cb_ui_editor_v2_low($prompt);

    return str_contains($p, 'eliminami')
        || str_contains($p, 'elimina')
        || str_contains($p, 'rimuovimi')
        || str_contains($p, 'rimuovi')
        || str_contains($p, 'toglimi')
        || str_contains($p, 'togli')
        || str_contains($p, 'cancellami')
        || str_contains($p, 'cancella')
        || str_contains($p, 'levami')
        || str_contains($p, 'leva');
}

function local_aisn_cb_ui_editor_v2_wants_replace(string $prompt): bool {
    $p = local_aisn_cb_ui_editor_v2_low($prompt);

    return str_contains($p, 'sostituisci') || str_contains($p, 'rimpiazza');
}

function local_aisn_cb_ui_editor_v2_repair_exam_summary(string $summary): string {
    $summary = trim((string)$summary);

    if ($summary === '') {
        return $summary;
    }

    $plain = trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($plain === '') {
        return $summary;
    }

    $plain = preg_replace('/\s+/u', ' ', $plain);

    if (
        !preg_match('/\bExam Dates\b/iu', $plain) &&
        !preg_match('/\bExam rules\b/iu', $plain) &&
        !preg_match('/\bExam Results\b/iu', $plain)
    ) {
        if (function_exists('local_aisn_cb_safe_body_to_html')) {
            return local_aisn_cb_safe_body_to_html($plain);
        }

        return '<div>' . nl2br(s($plain), false) . '</div>';
    }

    $year = '';
    if (preg_match('/A\.Y\.\s*([0-9]{4}\/[0-9]{4})/iu', $plain, $ym)) {
        $year = ' A.Y. ' . $ym[1];
    }

    preg_match_all('/\d{2}\/\d{2}\/\d{4}/u', $plain, $dmatches);
    $dates = array_values(array_unique($dmatches[0] ?? []));

    $rules = [];

    if (preg_match('/\bWriting Examination\b/iu', $plain)) {
        $rules[] = 'Writing Examination';
    }

    if (preg_match('/\bProject lab\b.*?(?:team|$)/iu', $plain, $pm)) {
        $project = trim((string)$pm[0]);
        $project = preg_replace('/\s+Exam\s*Results.*$/iu', '', $project);
        $project = preg_replace('/\s+See\s+news\s+section.*$/iu', '', $project);
        $project = trim($project);

        if ($project !== '') {
            $rules[] = $project;
        }
    }

    $resulttext = '';

    if (preg_match('/\bExam Results\b\s*:?\s*(.+)$/iu', $plain, $rm)) {
        $resulttext = trim((string)$rm[1]);
    } else if (preg_match('/\bSee news section\b/iu', $plain)) {
        $resulttext = 'See news section';
    }

    $html = '<div class="aisn-coursebuilder-text">';
    $html .= '<h4>' . s('Exam Dates' . $year) . '</h4>';

    if (!empty($dates)) {
        $html .= '<ul>';
        foreach ($dates as $date) {
            $html .= '<li>' . s($date) . '</li>';
        }
        $html .= '</ul>';
    }

    if (!empty($rules)) {
        $html .= '<h4>Exam rules</h4><ul>';
        foreach (array_values(array_unique($rules)) as $rule) {
            $html .= '<li>' . s($rule) . '</li>';
        }
        $html .= '</ul>';
    }

    if ($resulttext !== '') {
        $html .= '<h4>Exam Results</h4>';
        $html .= '<p>' . s($resulttext) . '</p>';
    }

    $html .= '</div>';

    return $html;
}

function local_aisn_cb_ui_editor_v2_strip_existing_date_color(string $summary): string {
    return preg_replace(
        '/<span[^>]*class=["\']aisn-cb-colored-date["\'][^>]*>(\d{2}\/\d{2}\/\d{4})<\/span>/iu',
        '$1',
        $summary
    );
}

function local_aisn_cb_ui_editor_v2_color_dates(string $summary, string $hex): string {
    $summary = local_aisn_cb_ui_editor_v2_repair_exam_summary($summary);
    $summary = local_aisn_cb_ui_editor_v2_strip_existing_date_color($summary);

    return preg_replace(
        '/(?<![0-9])(\d{2}\/\d{2}\/\d{4})(?![0-9])/u',
        '<span class="aisn-cb-colored-date" style="color:' . s($hex) . ';font-weight:700;">$1</span>',
        $summary
    );
}

function local_aisn_cb_ui_editor_v2_extract_remove_text(string $prompt): string {
    $removeverb = '(?:eliminami|eliminiami|elimina|rimuovimi|rimuovi|toglimi|togli|cancellami|cancella|levami|leva)';

    $patterns = [
        '/^' . $removeverb . '\s+(.+?)\s+(?:dal|dalla|nel|nella)\s+(?:contenuto\s+(?:della\s+)?sezione|sezione)\s+["“”\'«»]?.+?["“”\'«»]?\s*$/iu',
        '/^(?:nel|nella)?\s*contenuto\s+della\s+sezione\s+["“”\'«»]?.+?["“”\'«»]?\s+' . $removeverb . '\s+(.+)$/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $m)) {
            return local_aisn_cb_ui_editor_v2_clean((string)($m[1] ?? ''));
        }
    }

    return '';
}

function local_aisn_cb_ui_editor_v2_extract_replace(string $prompt): ?array {
    $patterns = [
        '/sostituisci\s+(.+?)\s+con\s+(.+?)\s+(?:nel|nella|dal|dalla)\s+(?:contenuto\s+(?:della\s+)?sezione|sezione)\s+["“”\'«»]?.+?["“”\'«»]?\s*$/iu',
        '/(?:contenuto\s+della\s+sezione|sezione)\s+["“”\'«»]?.+?["“”\'«»]?\s+sostituisci\s+(.+?)\s+con\s+(.+)$/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $m)) {
            return [
                local_aisn_cb_ui_editor_v2_clean((string)($m[1] ?? '')),
                local_aisn_cb_ui_editor_v2_clean((string)($m[2] ?? '')),
            ];
        }
    }

    return null;
}

function local_aisn_cb_ui_editor_v2_remove_text(string $summary, string $needle): string {
    $needle = local_aisn_cb_ui_editor_v2_clean($needle);

    if ($needle === '') {
        return $summary;
    }

    $quoted = preg_quote($needle, '/');
    $quoted = preg_replace('/\\\s+/u', '\\s+', $quoted);

    $summary = preg_replace('/\s*' . $quoted . '\s*/iu', ' ', $summary);
    $summary = preg_replace('/[ \t]{2,}/u', ' ', (string)$summary);

    return local_aisn_cb_ui_editor_v2_repair_exam_summary((string)$summary);
}

function local_aisn_cb_ui_editor_v2_update(stdClass $section, string $summary): void {
    global $DB;

    $section->summary = $summary;
    $section->summaryformat = FORMAT_HTML;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);
    rebuild_course_cache((int)$section->course, true);
}

function local_aisn_cb_ui_editor_v2_try_handle(int $courseid, string $prompt, array $files): ?array {
    if (!empty($files)) {
        return null;
    }

    $p = local_aisn_cb_ui_editor_v2_low($prompt);

    if (
        !local_aisn_cb_ui_editor_v2_wants_color($prompt) &&
        !local_aisn_cb_ui_editor_v2_wants_remove($prompt) &&
        !local_aisn_cb_ui_editor_v2_wants_replace($prompt) &&
        !local_aisn_cb_ui_editor_v2_wants_repair($prompt)
    ) {
        return null;
    }

    $section = local_aisn_cb_ui_editor_v2_section_from_prompt($courseid, $prompt);

    if (!$section) {
        return [
            'Content editor: sezione non trovata nel prompt.',
            'Nessuna nuova sezione creata.',
        ];
    }

    $summary = (string)($section->summary ?? '');

    if (local_aisn_cb_ui_editor_v2_wants_repair($prompt)) {
        $summary = local_aisn_cb_ui_editor_v2_repair_exam_summary($summary);
        local_aisn_cb_ui_editor_v2_update($section, $summary);

        return [
            'Content editor: formattazione riparata nella sezione "' . (string)$section->name . '".',
            'Nessuna nuova sezione creata.',
        ];
    }

    if (local_aisn_cb_ui_editor_v2_wants_color($prompt)) {
        $color = local_aisn_cb_ui_editor_v2_color_from_prompt($prompt);

        if ($color === null) {
            return [
                'Content editor: colore non riconosciuto.',
                'Esempio: "colorami di rosso le date nella sezione Exams".',
            ];
        }

        if (str_contains($p, 'date') || str_contains($p, 'giorni') || str_contains($p, 'appelli')) {
            $summary = local_aisn_cb_ui_editor_v2_color_dates($summary, $color[1]);
            local_aisn_cb_ui_editor_v2_update($section, $summary);

            return [
                'Content editor: date colorate in ' . $color[0] . ' nella sezione "' . (string)$section->name . '".',
                'Nessuna nuova sezione creata.',
            ];
        }

        return [
            'Content editor: elemento da colorare non riconosciuto.',
            'Esempio: "colorami di rosso le date nella sezione Exams".',
        ];
    }

    if (local_aisn_cb_ui_editor_v2_wants_remove($prompt)) {
        $needle = local_aisn_cb_ui_editor_v2_extract_remove_text($prompt);

        if ($needle === '') {
            return [
                'Content editor: testo da eliminare non riconosciuto.',
                'Esempio: "eliminami (tentative) dal contenuto della sezione Exams".',
            ];
        }

        $summary = local_aisn_cb_ui_editor_v2_remove_text($summary, $needle);
        local_aisn_cb_ui_editor_v2_update($section, $summary);

        return [
            'Content editor: rimosso "' . $needle . '" dalla sezione "' . (string)$section->name . '".',
            'Formattazione preservata/riparata.',
            'Nessuna nuova sezione creata.',
        ];
    }

    if (local_aisn_cb_ui_editor_v2_wants_replace($prompt)) {
        $replace = local_aisn_cb_ui_editor_v2_extract_replace($prompt);

        if ($replace === null) {
            return [
                'Content editor: sostituzione non riconosciuta.',
                'Esempio: "sostituisci X con Y nel contenuto della sezione Exams".',
            ];
        }

        [$from, $to] = $replace;
        $quoted = preg_quote($from, '/');
        $quoted = preg_replace('/\\\s+/u', '\\s+', $quoted);
        $summary = preg_replace('/' . $quoted . '/iu', s($to), $summary);
        $summary = local_aisn_cb_ui_editor_v2_repair_exam_summary((string)$summary);
        local_aisn_cb_ui_editor_v2_update($section, $summary);

        return [
            'Content editor: sostituito "' . $from . '" con "' . $to . '" nella sezione "' . (string)$section->name . '".',
            'Formattazione preservata/riparata.',
            'Nessuna nuova sezione creata.',
        ];
    }

    return null;
}

// AISN_CB_UI_CONTENT_EDITOR_V2
function local_aisn_cb_ai_editor_is_content_prompt(string $prompt): bool {
    $p = local_aisn_cb_low($prompt);

    if (preg_match('/\b(?:elimina|cancella|rimuovi|togli)\s+(?:la\s+)?sezione\b/iu', $prompt)) {
        return false;
    }

    return str_contains($p, 'contenuto')
        || str_contains($p, 'testo')
        || str_contains($p, 'html')
        || str_contains($p, 'formatta')
        || str_contains($p, 'ripara')
        || str_contains($p, 'sistema')
        || str_contains($p, 'colora')
        || str_contains($p, 'colorami')
        || str_contains($p, 'evidenzia')
        || str_contains($p, 'metti in blu')
        || str_contains($p, 'metti in rosso')
        || str_contains($p, 'rendi blu')
        || str_contains($p, 'rendi rosso')
        || str_contains($p, 'date')
        || str_contains($p, 'appelli')
        || preg_match('/\d{2}\/\d{2}\/\d{4}/u', $prompt);
}

function local_aisn_cb_ai_editor_clean_html(string $html): string {
    $html = trim((string)$html);

    if ($html === '') {
        return '';
    }

    $html = preg_replace('#<(script|iframe|object|embed|form|input|button|meta|link)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|iframe|object|embed|form|input|button|meta|link)[^>]*/?>#is', '', $html);
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', (string)$html);
    $html = preg_replace('/javascript\s*:/iu', '', (string)$html);
    $html = preg_replace('/expression\s*\(/iu', '', (string)$html);
    $html = preg_replace('/url\s*\(/iu', '', (string)$html);

    $allowed = '<h3><h4><h5><p><br><ul><ol><li><strong><b><em><i><span><div><table><thead><tbody><tr><th><td>';
    $html = strip_tags((string)$html, $allowed);

    return trim((string)$html);
}

function local_aisn_cb_ai_editor_parse_json(string $raw): ?array {
    $raw = trim((string)$raw);
    $raw = preg_replace('/^```json\s*/i', '', $raw);
    $raw = preg_replace('/^```\s*/i', '', $raw);
    $raw = preg_replace('/\s*```$/', '', $raw);

    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');

    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $json = substr($raw, $start, $end - $start + 1);
    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : null;
}

function local_aisn_cb_ai_editor_sections_context(int $courseid): array {
    global $DB;

    $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
    $out = [];

    foreach ($sections as $section) {
        $sectionnum = (int)($section->section ?? -1);
        $name = trim((string)($section->name ?? ''));

        if ($name === '') {
            $name = $sectionnum === 0 ? 'General' : ('Section ' . $sectionnum);
        }

        $summary = (string)($section->summary ?? '');
        $plain = trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = preg_replace('/\s+/u', ' ', (string)$plain);

        $out[] = [
            'section_number' => $sectionnum,
            'name' => $name,
            'summary_html' => core_text::substr($summary, 0, 9000),
            'summary_text' => core_text::substr((string)$plain, 0, 2500),
        ];
    }

    return $out;
}

function local_aisn_cb_ai_editor_find_target_section(int $courseid, array $data): ?stdClass {
    if (isset($data['section_number']) && is_numeric($data['section_number'])) {
        $section = local_aisn_cb_get_section($courseid, (int)$data['section_number']);

        if ($section) {
            return $section;
        }
    }

    $target = trim((string)($data['target_section'] ?? ''));

    if ($target !== '') {
        $section = local_aisn_cb_find_section($courseid, $target);

        if ($section) {
            return $section;
        }
    }

    return null;
}

function local_aisn_cb_ai_editor_try_handle(int $courseid, string $teacherprompt, array $files): ?array {
    if (!empty($files)) {
        return null;
    }

    if (!local_aisn_cb_ai_editor_is_content_prompt($teacherprompt)) {
        return null;
    }

    if (!class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
        return [
            'AI Section Editor: provider AI non disponibile.',
            'Nessuna modifica applicata.',
        ];
    }

    $sections = local_aisn_cb_ai_editor_sections_context($courseid);

    $system = 'You are an AI editor for Moodle course sections. Return ONLY valid JSON. No markdown. No explanations.';

    $aiprompt = "Devi modificare il contenuto HTML di UNA sezione Moodle in base al prompt naturale del docente.\n\n"
        . "INPUT DISPONIBILE:\n"
        . "- Lista sezioni esistenti con nome, numero, HTML attuale e testo.\n"
        . "- Prompt docente.\n\n"
        . "AZIONI MATERIALI/RISORSE EXTRA:\n"
        . "- AISN_AI_MATERIAL_ACTIONS_FULL_PROMPT_V1\n"
        . "12. delete_material: {\"action\":\"delete_material\",\"target\":\"Nome o numero sezione\",\"material\":\"nome completo o parziale del materiale/file da eliminare\"}\n"
        . "13. move_material: {\"action\":\"move_material\",\"from\":\"sezione sorgente opzionale\",\"destination\":\"sezione destinazione\",\"material\":\"nome completo o parziale del materiale/file\"}\n"
        . "14. rename_material: {\"action\":\"rename_material\",\"target\":\"Nome o numero sezione\",\"material\":\"nome materiale attuale\",\"new_name\":\"nuovo nome materiale\"}\n"
        . "15. hide_material: {\"action\":\"hide_material\",\"target\":\"Nome o numero sezione\",\"material\":\"nome materiale\"}\n"
        . "16. show_material: {\"action\":\"show_material\",\"target\":\"Nome o numero sezione\",\"material\":\"nome materiale\"}\n"
        . "17. clear_section_content: {\"action\":\"clear_section_content\",\"target\":\"Nome o numero sezione\"}\n\n"        . "REGOLE OBBLIGATORIE:\n"
        . "1. Scegli una sola sezione target tra quelle esistenti.\n"
        . "2. Non inventare nuove sezioni.\n"
        . "3. Non appiattire il contenuto: conserva titoli, liste e paragrafi.\n"
        . "4. Restituisci l'HTML COMPLETO finale della sezione target, non una descrizione.\n"
        . "5. Se il docente chiede di colorare solo alcune date indicate nel prompt, colora SOLO quelle date.\n"
        . "6. Per il blu usa: <span style=\"color:#2563eb;font-weight:700;\">DATA</span>.\n"
        . "7. Per il rosso usa: <span style=\"color:#dc2626;font-weight:700;\">DATA</span>.\n"
        . "8. Usa solo HTML sicuro: h3, h4, h5, p, br, ul, ol, li, strong, em, span, div, table, tr, th, td.\n"
        . "9. Se non capisci la sezione o la modifica, metti can_handle=false.\n\n"
        . "SEZIONI ATTUALI:\n"
        . json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"

        . "DATE_CONTEXT_CALCOLATO_DAL_SERVER:\n"
        . json_encode($datefacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
        . "Usa DATE_CONTEXT_CALCOLATO_DAL_SERVER per tutti i prompt con date, oggi, domani, prossimo, più vicino, più lontano, appelli, scadenze.\n"
        . "Se il docente chiede la data più vicina a oggi, usa closest_to_today della sezione target.\n"
        . "Se il docente chiede la data più lontana da oggi, usa farthest_from_today della sezione target.\n"
        . "Non scegliere date a intuito: usa sempre le distanze già calcolate dal server.\n\n"
        . "DATA DI OGGI SERVER MOODLE: " . $todayiso . "\n"
        . "Quando il prompt usa riferimenti temporali come oggi, domani, prossimo, precedente, data più vicina ad oggi, calcola rispetto a DATA DI OGGI SERVER MOODLE.\n"
        . "Se il docente chiede la data più vicina ad oggi, scegli UNA SOLA data presente nella sezione target: quella con distanza minima dalla data di oggi.\n"
        . "Se tutte le date sono nel passato, la più vicina ad oggi è la più recente tra quelle passate.\n\n"
        . "PROMPT DOCENTE:\n"
        . $teacherprompt . "\n\n"
        . "RISPOSTA JSON OBBLIGATORIA:\n"
        . "{\n"
        . "  \"can_handle\": true,\n"
        . "  \"target_section\": \"Nome sezione esistente\",\n"
        . "  \"section_number\": 12,\n"
        . "  \"html\": \"HTML finale completo della sezione\",\n"
        . "  \"message\": \"breve messaggio per il docente\"\n"
        . "}\n";

    try {
        $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
        $raw = $provider->generate($aiprompt, 7000, $system);
        $data = local_aisn_cb_ai_editor_parse_json($raw);
    } catch (Throwable $e) {
        debugging('AI Course Builder section editor failed: ' . $e->getMessage(), DEBUG_DEVELOPER);

        return [
            'AI Section Editor: chiamata AI fallita: ' . $e->getMessage(),
            'Nessuna modifica applicata.',
        ];
    }

    if (!is_array($data)) {
        return [
            'AI Section Editor: risposta AI non valida.',
            'Nessuna modifica applicata.',
        ];
    }

    if (empty($data['can_handle'])) {
        $message = trim((string)($data['message'] ?? 'La richiesta non è abbastanza chiara.'));
        return [
            'AI Section Editor: ' . $message,
            'Nessuna modifica applicata.',
        ];
    }

    $section = local_aisn_cb_ai_editor_find_target_section($courseid, $data);

    if (!$section) {
        return [
            'AI Section Editor: sezione target non trovata: ' . (string)($data['target_section'] ?? ''),
            'Nessuna modifica applicata.',
        ];
    }

    $html = local_aisn_cb_ai_editor_clean_html((string)($data['html'] ?? ''));

    if ($html === '') {
        return [
            'AI Section Editor: HTML finale vuoto o non valido.',
            'Nessuna modifica applicata.',
        ];
    }

    local_aisn_cb_set_section_summary_html($section, $html);
    local_aisn_cb_sync_resources($courseid);

    $message = trim((string)($data['message'] ?? 'Contenuto aggiornato tramite AI.'));
    if ($message === '') {
        $message = 'Contenuto aggiornato tramite AI.';
    }

    return [
        'AI Section Editor attivo: prompt interpretato dall’AI.',
        $message,
        'Sezione aggiornata: "' . (string)$section->name . '".',
        'Nessuna nuova sezione creata.',
    ];
}

// AISN_AI_SECTION_EDITOR_V1
function local_aisn_cb_safe_router(int $courseid, int $userid, string $prompt, array $files): ?array {
    // AISN_AI_SECTION_EDITOR_V1_HOOK
    $aiedit = local_aisn_cb_ai_editor_try_handle($courseid, $prompt, $files);

    if ($aiedit !== null) {
        return $aiedit;
    }

    // AISN_CB_UI_CONTENT_EDITOR_V2_HOOK
    $uicontentedit = local_aisn_cb_ui_editor_v2_try_handle($courseid, $prompt, $files);

    if ($uicontentedit !== null) {
        return $uicontentedit;
    }

    // AISN_CB_BRUTAL_CONTENT_EDIT_HOOK
    $brutalcontentedit = local_aisn_cb_brutal_content_edit_try_handle($courseid, $prompt, $files);

    if ($brutalcontentedit !== null) {
        return $brutalcontentedit;
    }

    $contentedit = local_aisn_cb_content_edit_try_handle($courseid, $prompt, $files);

    if ($contentedit !== null) {
        return $contentedit;
    }

    $textrequest = local_aisn_cb_safe_extract_text_section($prompt);

    if ($textrequest !== null && empty($files)) {
        $section = local_aisn_cb_ensure_section($courseid, (string)$textrequest['title'], '');
        local_aisn_cb_safe_set_section_summary($section, local_aisn_cb_safe_body_to_html((string)$textrequest['body']));
        local_aisn_cb_sync_resources($courseid);

        return [
            'Azione diretta: sezione "' . (string)$section->name . '" creata/aggiornata con testo formattato.',
            'Nessuna sezione extra creata.',
        ];
    }

    $sectionlist = local_aisn_cb_safe_extract_section_list($prompt);

    if (!empty($sectionlist) && empty($files)) {
        $logs = [];

        foreach ($sectionlist as $title) {
            $section = local_aisn_cb_ensure_section($courseid, $title, '<p>Sezione creata da AI Course Builder tramite struttura docente.</p>');
            $logs[] = 'Sezione pronta: "' . (string)$section->name . '".';
        }

        local_aisn_cb_sync_resources($courseid);
        $logs[] = 'Struttura corso creata senza sezioni inventate.';

        return $logs;
    }

    if (empty($files)) {
        return null;
    }

    $files = local_aisn_cb_dedupe_files($files);
    $target = local_aisn_cb_safe_extract_target($prompt);
    $oneperfile = local_aisn_cb_safe_wants_one_section_per_file($prompt);
    $createTarget = local_aisn_cb_safe_wants_create_section($prompt);
    $conservative = local_aisn_cb_safe_is_conservative($prompt);

    if ($oneperfile) {
        $logs = ['Azione diretta: creo una sezione per ogni file perché richiesto esplicitamente.'];

        foreach ($files as $file) {
            $filename = clean_param((string)($file['name'] ?? ''), PARAM_FILE);
            $title = local_aisn_cb_filename_to_section_title($filename);
            $section = local_aisn_cb_ensure_section(
                $courseid,
                $title,
                '<p>Sezione creata automaticamente da AI Course Builder per il materiale: ' . s($filename) . '</p>'
            );
            $logs[] = 'Sezione pronta: "' . (string)$section->name . '".';
            $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, [$file]));
        }

        local_aisn_cb_sync_resources($courseid);
        return $logs;
    }

    if ($target !== null && $target !== '') {
        if ($createTarget && !$conservative) {
            $section = local_aisn_cb_ensure_section($courseid, $target, '<p>Sezione creata da AI Course Builder tramite prompt docente.</p>');
        } else {
            $section = local_aisn_cb_find_section($courseid, $target);

            if (!$section) {
                return [
                    'Sezione esistente "' . $target . '" non trovata.',
                    'Nessuna nuova sezione creata. Controlla il nome oppure chiedi esplicitamente di crearla.',
                ];
            }
        }

        $logs = ['Azione diretta: uso sezione "' . (string)$section->name . '".'];
        $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, $files));
        local_aisn_cb_sync_resources($courseid);
        $logs[] = 'Nessuna sezione extra creata.';

        return $logs;
    }

    return local_aisn_cb_safe_attach_existing($courseid, $userid, $files);
}

// AISN_CB_SAFE_ROUTER_PATCH
function local_aisn_cb_execute_direct(int $courseid, int $userid, string $prompt, array $files): array {
    $logs = [];
    $promptlow = local_aisn_cb_low($prompt);
    $safehandled = local_aisn_cb_safe_router($courseid, $userid, $prompt, $files);

    if ($safehandled !== null) {
        return $safehandled;
    }

    $textrequest = local_aisn_cb_extract_text_section_request($prompt);

    if ($textrequest !== null && empty($files)) {
        $section = local_aisn_cb_ensure_section($courseid, (string)$textrequest['title'], '');
        local_aisn_cb_set_section_summary_html($section, local_aisn_cb_prompt_text_to_html((string)$textrequest['body']));
        local_aisn_cb_sync_resources($courseid);

        return [
            'Azione diretta: sezione "' . (string)$section->name . '" creata/aggiornata con il testo fornito dal docente.',
            'Nessuna nuova sezione extra creata.',
        ];
    }


    if (local_aisn_cb_prompt_wants_clear_course($prompt)) {
        $logs = local_aisn_cb_delete_all_sections($courseid);
        local_aisn_cb_sync_resources($courseid);
        return $logs;
    }

    $deleteTarget = local_aisn_cb_extract_delete_target($prompt);

    if ($deleteTarget !== null && !local_aisn_cb_prompt_wants_one_section_per_file($prompt)) {
        if ($deleteTarget === '__ALL_SECTIONS__') {
            $logs = local_aisn_cb_delete_all_sections($courseid);
            local_aisn_cb_sync_resources($courseid);
            return $logs;
        }

        $section = local_aisn_cb_find_section($courseid, $deleteTarget);

        if ($section) {
            $status = local_aisn_cb_delete_section($courseid, $section);
            $label = (string)($section->name ?? '');
            if ($label === '' && (int)($section->section ?? -1) === 0) {
                $label = 'Highlights / section 0';
            }
            $logs[] = 'Azione diretta: sezione "' . $label . '" ' . $status . '.';
        } else {
            $logs[] = 'Azione diretta: sezione da togliere non trovata: ' . $deleteTarget . '.';
        }

        local_aisn_cb_sync_resources($courseid);

        return $logs;
    }

    if (preg_match('/rinomina\s+(?:la\s+)?sezione\s+["“”\'«»]?([^"“”\'«»]+)["“”\'«»]?\s+(?:in|come|a)\s+["“”\'«»]?([^"“”\'«»\r\n]+)["“”\'«»]?/iu', $prompt, $m)) {
        $old = local_aisn_cb_section_text((string)$m[1]);

        $newraw = (string)$m[2];
        $new = function_exists('local_aisn_cb_safe_title_clean')
            ? local_aisn_cb_safe_title_clean($newraw)
            : local_aisn_cb_clean_section_title($newraw);

        $section = local_aisn_cb_find_section($courseid, $old);

        if ($section) {
            $body = '';

            if (preg_match('/(?:inserisci|metti|aggiungi|mettici|mettendoci)\s+(?:questo\s+)?(?:testo|contenuto)\s*:?\s*([\s\S]+)$/iu', $prompt, $textmatches)) {
                $body = trim((string)$textmatches[1]);
            }

            $summary = (string)($section->summary ?? '');

            if ($body !== '') {
                if (function_exists('local_aisn_cb_safe_body_to_html')) {
                    $summary = local_aisn_cb_safe_body_to_html($body);
                } else {
                    $summary = '<div>' . nl2br(s($body), false) . '</div>';
                }
            }

            local_aisn_cb_update_section($section, $new, $summary);

            $logs[] = 'Azione diretta: sezione "' . $old . '" rinominata in "' . $new . '".';

            if ($body !== '') {
                $logs[] = 'Azione diretta: testo inserito nella sezione "' . $new . '".';
            } else {
                $logs[] = 'Azione diretta: contenuto precedente mantenuto.';
            }
        } else {
            $logs[] = 'Azione diretta: sezione da rinominare non trovata: ' . $old . '.';
        }

        local_aisn_cb_sync_resources($courseid);

        return $logs;
    }
    if (!empty($files)) {
        $files = local_aisn_cb_dedupe_files($files);
        $target = local_aisn_cb_extract_target_section($prompt);
        $oneperfile = local_aisn_cb_prompt_wants_one_section_per_file($prompt);
        $singlesection = local_aisn_cb_prompt_singular_section_request($prompt);
        $mentionsfiles = local_aisn_cb_prompt_mentions_files_or_materials($prompt);
        if ($target !== null && !$oneperfile && !preg_match('/\b(?:crea|aggiungi|inserisci)\s+(?:una\s+|unica\s+|sola\s+|nuova\s+)?sezione\b/iu', $prompt)) {
            $section = local_aisn_cb_find_section($courseid, $target);

            if (!$section) {
                return [
                    'Azione diretta: sezione esistente "' . $target . '" non trovata. Nessuna nuova sezione creata.',
                    'Controlla il nome della sezione oppure crea prima la sezione con Course Builder.',
                ];
            }

            $logs[] = 'Azione diretta: uso sezione esistente "' . (string)$section->name . '".';
            $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, $files));
            local_aisn_cb_sync_resources($courseid);
            $logs[] = 'RAG sync saltato in modalità demo veloce.';

            return $logs;
        }

        if (local_aisn_cb_prompt_wants_existing_file_routing($prompt)) {
            return local_aisn_cb_attach_files_to_existing_sections($courseid, $userid, $files);
        }


        // Important demo case: "crea una sezione, chiamala Introduzione e mettimi i materiali".
        // Keep all uploaded files in that single section, do NOT split by filename.
        if ($target !== null && !$oneperfile && ($singlesection || $mentionsfiles || count($files) === 1)) {
            $section = local_aisn_cb_ensure_section($courseid, $target, '<p>Sezione creata da AI Course Builder tramite prompt docente.</p>');
            $logs[] = 'Azione diretta: uso sezione "' . (string)$section->name . '".';
            $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, $files));
            local_aisn_cb_sync_resources($courseid);
            $logs[] = 'RAG sync saltato in modalità demo veloce.';

            return $logs;
        }

        if ($singlesection && $mentionsfiles) {
            $section = local_aisn_cb_ensure_section($courseid, 'Materiali docente', '<p>Sezione creata da AI Course Builder tramite prompt docente.</p>');
            $logs[] = 'Azione diretta: uso sezione "' . (string)$section->name . '".';
            $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, $files));
            local_aisn_cb_sync_resources($courseid);
            $logs[] = 'RAG sync saltato in modalità demo veloce.';

            return $logs;
        }

        if ($target !== null && !$oneperfile && (str_contains($promptlow, 'tutti') || count($files) === 1 || str_contains($promptlow, 'file caricati') || str_contains($promptlow, 'materiali'))) {
            $section = local_aisn_cb_ensure_section($courseid, $target, '<p>Sezione creata da AI Course Builder tramite prompt docente.</p>');
            $logs[] = 'Azione diretta: uso sezione "' . (string)$section->name . '".';
            $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, $files));
            local_aisn_cb_sync_resources($courseid);
            $logs[] = 'RAG sync saltato in modalità demo veloce.';

            return $logs;
        }

        // Default robusto: if the teacher explicitly wants one section per file, split.
        // Otherwise with multiple files and no title we still create sections by filename.
        if ($oneperfile) {
            $logs[] = 'Azione diretta: creo sezioni dai file caricati e collego i materiali. File ricevuti: ' . count($files) . '.';

            foreach ($files as $file) {
                $filename = clean_param((string)($file['name'] ?? ''), PARAM_FILE);
                $title = local_aisn_cb_filename_to_section_title($filename);
                $section = local_aisn_cb_ensure_section(
                    $courseid,
                    $title,
                    '<p>Sezione creata automaticamente da AI Course Builder per il materiale: ' . s($filename) . '</p>'
                );
                $logs[] = 'Sezione pronta: "' . (string)$section->name . '".';
                $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, [$file]));
            }

            local_aisn_cb_sync_resources($courseid);
            $logs[] = 'RAG sync saltato in modalità demo veloce.';

            return $logs;
        }
    }

    $titles = local_aisn_cb_extract_create_section_titles($prompt);

    if (!empty($titles)) {
        foreach ($titles as $title) {
            $section = local_aisn_cb_ensure_section($courseid, $title, '<p>Sezione creata da AI Course Builder tramite prompt docente.</p>');
            $logs[] = 'Azione diretta: sezione pronta "' . (string)$section->name . '".';
        }

        return $logs;
    }

    return [];
}

function local_aisn_cb_sections_for_ai(int $courseid): array {
    global $DB;

    $rows = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
    $out = [];

    foreach ($rows as $s) {
        $sectionnum = (int)($s->section ?? -1);
        $name = trim((string)($s->name ?? ''));

        if ($name === '') {
            $name = $sectionnum === 0 ? 'General' : ('Section ' . $sectionnum);
        }

        $summaryhtml = (string)($s->summary ?? '');
        $summarytext = trim(html_entity_decode(strip_tags($summaryhtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $summarytext = preg_replace('/\s+/u', ' ', (string)$summarytext);

        $out[] = [
            'number' => $sectionnum,
            'name' => $name,
            'visible' => !empty($s->visible),
            'summary_text' => core_text::substr((string)$summarytext, 0, 2500),
            'summary_html' => core_text::substr((string)$summaryhtml, 0, 9000),
        ];
    }

    return $out;
}

function local_aisn_cb_files_for_ai(array $files): array {
    $out = [];

    foreach ($files as $file) {
        $out[] = [
            'name' => clean_param((string)($file['name'] ?? 'file'), PARAM_FILE),
            'size' => (int)($file['size'] ?? 0),
            'type' => (string)($file['type'] ?? ''),
        ];
    }

    return $out;
}

function local_aisn_cb_extract_json_actions(string $raw): array {
    $raw = trim($raw);
    $raw = preg_replace('/^```json\s*/iu', '', $raw);
    $raw = preg_replace('/^```\s*/iu', '', $raw);
    $raw = preg_replace('/\s*```$/u', '', $raw);

    if (preg_match('/\{[\s\S]*\}/u', $raw, $matches)) {
        $raw = $matches[0];
    }

    $data = json_decode($raw, true);

    if (!is_array($data) || empty($data['actions']) || !is_array($data['actions'])) {
        return [];
    }

    return $data['actions'];
}

function local_aisn_cb_ai_datefacts_context(array $sections): array {
    $today = new DateTimeImmutable(date('Y-m-d'));

    $out = [
        'today_iso' => $today->format('Y-m-d'),
        'today_human_it' => $today->format('d/m/Y'),
        'meaning' => [
            'closest_to_today' => 'data con distanza assoluta minima da oggi',
            'farthest_from_today' => 'data con distanza assoluta massima da oggi',
            'if_all_dates_are_past_closest' => 'la più vicina a oggi è la più recente tra quelle passate',
            'if_all_dates_are_past_farthest' => 'la più lontana da oggi è la più vecchia tra quelle passate',
        ],
        'sections' => [],
    ];

    foreach ($sections as $section) {
        $name = (string)($section['name'] ?? '');
        $number = (int)($section['number'] ?? -1);
        $summaryhtml = (string)($section['summary_html'] ?? '');
        $summarytext = (string)($section['summary_text'] ?? '');

        $plain = html_entity_decode(strip_tags($summaryhtml . ' ' . $summarytext), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        preg_match_all('/\b(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/([0-9]{4})\b/u', $plain, $matches);

        $dates = [];

        foreach (array_values(array_unique($matches[0] ?? [])) as $date) {
            $dt = DateTimeImmutable::createFromFormat('!d/m/Y', $date);

            if (!$dt) {
                continue;
            }

            $signed = (int)$today->diff($dt)->format('%r%a');
            $abs = abs($signed);

            $dates[] = [
                'date' => $date,
                'iso' => $dt->format('Y-m-d'),
                'signed_distance_days_from_today' => $signed,
                'absolute_distance_days_from_today' => $abs,
                'relation_to_today' => $signed === 0 ? 'today' : ($signed > 0 ? 'future' : 'past'),
            ];
        }

        if (empty($dates)) {
            continue;
        }

        $closest = null;
        $farthest = null;

        foreach ($dates as $dateinfo) {
            if ($closest === null || $dateinfo['absolute_distance_days_from_today'] < $closest['absolute_distance_days_from_today']) {
                $closest = $dateinfo;
            }

            if ($farthest === null || $dateinfo['absolute_distance_days_from_today'] > $farthest['absolute_distance_days_from_today']) {
                $farthest = $dateinfo;
            }
        }

        $out['sections'][] = [
            'section_number' => $number,
            'section_name' => $name,
            'dates' => $dates,
            'closest_to_today' => $closest,
            'farthest_from_today' => $farthest,
        ];
    }

    return $out;
}

// AISN_AI_DATE_CONTEXT_HELPER_V1
function local_aisn_cb_ai_material_title_from_module(stdClass $row): string {
    global $DB;

    if (function_exists('local_aisn_cb_ai_mat_get_title')) {
        $title = trim((string)local_aisn_cb_ai_mat_get_title($row));

        if ($title !== '') {
            return $title;
        }
    }

    $modname = trim((string)($row->modname ?? ''));
    $instance = (int)($row->instance ?? 0);

    if ($modname === '' || $instance <= 0) {
        return '';
    }

    try {
        $record = $DB->get_record($modname, ['id' => $instance], 'id,name', IGNORE_MISSING);

        if ($record && !empty($record->name)) {
            return trim((string)$record->name);
        }
    } catch (Throwable $e) {
        debugging('AI Course Builder material title lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return '';
}

function local_aisn_cb_ai_material_catalog(int $courseid): array {
    global $DB;

    $sql = "SELECT cm.id AS cmid,
                   cm.course,
                   cm.instance,
                   cm.visible,
                   cm.section AS sectionid,
                   cs.section AS sectionnum,
                   cs.name AS sectionname,
                   m.name AS modname
              FROM {course_modules} cm
              JOIN {course_sections} cs ON cs.id = cm.section
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.deletioninprogress = 0
          ORDER BY cs.section ASC, cm.id ASC";

    $rows = $DB->get_records_sql($sql, ['courseid' => $courseid]);
    $catalog = [];

    foreach ($rows as $row) {
        $sectionnum = (int)($row->sectionnum ?? 0);
        $sectionname = trim((string)($row->sectionname ?? ''));

        if ($sectionname === '') {
            $sectionname = $sectionnum === 0 ? 'Highlights' : ('Section ' . $sectionnum);
        }

        $title = local_aisn_cb_ai_material_title_from_module($row);

        if ($title === '') {
            continue;
        }

        $catalog[] = [
            'cmid' => (int)$row->cmid,
            'title' => $title,
            'modname' => (string)$row->modname,
            'section_number' => $sectionnum,
            'section_name' => $sectionname,
            'visible' => !empty($row->visible),
        ];
    }

    return $catalog;
}

function local_aisn_cb_ai_clean_json_response(string $raw): string {
    $clean = trim($raw);
    $clean = preg_replace('/^```json\s*/i', '', $clean);
    $clean = preg_replace('/^```\s*/i', '', $clean);
    $clean = preg_replace('/\s*```$/', '', $clean);

    $start = strpos($clean, '{');
    $end = strrpos($clean, '}');

    if ($start === false || $end === false || $end <= $start) {
        return '';
    }

    return trim(substr($clean, $start, $end - $start + 1));
}

function local_aisn_cb_ai_decode_actions(string $raw): array {
    $json = local_aisn_cb_ai_clean_json_response($raw);

    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        return [];
    }

    if (isset($decoded['actions']) && is_array($decoded['actions'])) {
        return array_values(array_filter($decoded['actions'], 'is_array'));
    }

    if (array_is_list($decoded)) {
        return array_values(array_filter($decoded, 'is_array'));
    }

    return [];
}

function local_aisn_cb_ai_normalize_material_actions(array $actions, array $materialcatalog): array {
    $bycmid = [];

    foreach ($materialcatalog as $material) {
        $cmid = (int)($material['cmid'] ?? 0);

        if ($cmid > 0) {
            $bycmid[$cmid] = $material;
        }
    }

    foreach ($actions as $i => $action) {
        if (!is_array($action)) {
            continue;
        }

        $type = trim((string)($action['action'] ?? ''));
        $isMaterialAction = in_array($type, [
            'delete_material',
            'move_material',
            'rename_material',
            'hide_material',
            'show_material',
            'set_material_visibility',
        ], true);

        if (!$isMaterialAction) {
            continue;
        }

        $cmid = 0;

        foreach (['cmid', 'course_module_id', 'material_cmid', 'materialid', 'material_id'] as $key) {
            if (!empty($action[$key])) {
                $cmid = (int)$action[$key];
                break;
            }
        }

        if ($cmid > 0 && isset($bycmid[$cmid])) {
            $material = $bycmid[$cmid];

            $actions[$i]['cmid'] = $cmid;
            $actions[$i]['material'] = (string)$material['title'];

            if (empty($actions[$i]['target']) && empty($actions[$i]['section'])) {
                $actions[$i]['target'] = (string)$material['section_name'];
            }

            if (empty($actions[$i]['from'])) {
                $actions[$i]['from'] = (string)$material['section_name'];
            }
        }
    }

    return $actions;
}

function local_aisn_cb_ai_plan_actions(int $courseid, string $teacherprompt, array $files): array {
    if (!class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
        return [];
    }

    $sections = local_aisn_cb_sections_for_ai($courseid);
    $datefacts = local_aisn_cb_ai_datefacts_context($sections);
    $fileinfo = local_aisn_cb_files_for_ai($files);
    $materialcatalog = local_aisn_cb_ai_material_catalog($courseid);
    $todayiso = date('Y-m-d');

    $system = 'You are an AI Moodle Course Builder semantic planner. Return ONLY valid JSON. No markdown. No prose.';

    $prompt = "Sei il cervello AI di un Course Builder Moodle.\n"
        . "Il docente scrive in linguaggio naturale, anche con errori, abbreviazioni, sinonimi o parole inventate.\n"
        . "Devi capire semanticamente cosa intende, usando il catalogo reale del corso.\n"
        . "NON usare regole rigide. NON richiedere il nome preciso del file.\n"
        . "Rispondi solo con JSON valido.\n\n"

        . "SEZIONI ATTUALI DEL CORSO:\n"
        . json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"

        . "MATERIALI/RISORSE REALI DEL CORSO:\n"
        . json_encode($materialcatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"

        . "FILE CARICATI NEL FORM IN QUESTO PROMPT:\n"
        . json_encode($fileinfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"

        . "DATE_CONTEXT_CALCOLATO_DAL_SERVER:\n"
        . json_encode($datefacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"

        . "DATA DI OGGI SERVER MOODLE: " . $todayiso . "\n\n"

        . "AZIONI AMMESSE:\n"
        . "1. create_section: {\"action\":\"create_section\",\"title\":\"Nome sezione\",\"summary_html\":\"HTML opzionale\"}\n"
        . "2. rename_section: {\"action\":\"rename_section\",\"target\":\"Nome o numero sezione\",\"title\":\"Nuovo nome\"}\n"
        . "3. update_section_html: {\"action\":\"update_section_html\",\"target\":\"Nome o numero sezione\",\"html\":\"HTML finale completo\"}\n"
        . "4. delete_section: {\"action\":\"delete_section\",\"target\":\"Nome o numero sezione\"}\n"
        . "5. delete_all_sections: {\"action\":\"delete_all_sections\"}\n"
        . "6. clear_section_zero: {\"action\":\"clear_section_zero\"}\n"
        . "7. hide_section: {\"action\":\"hide_section\",\"target\":\"Nome o numero sezione\"}\n"
        . "8. show_section: {\"action\":\"show_section\",\"target\":\"Nome o numero sezione\"}\n"
        . "9. duplicate_section: {\"action\":\"duplicate_section\",\"target\":\"Nome o numero sezione\",\"new_title\":\"Nome copia\"}\n"
        . "10. move_section: {\"action\":\"move_section\",\"target\":\"Nome o numero sezione\",\"destination\":2}\n"
        . "11. attach_files: {\"action\":\"attach_files\",\"target\":\"Nome o numero sezione\",\"files\":[\"parte nome file\"]}\n"
        . "12. delete_material: {\"action\":\"delete_material\",\"cmid\":123,\"target\":\"Nome sezione\",\"material\":\"titolo esatto dal catalogo\"}\n"
        . "13. move_material: {\"action\":\"move_material\",\"cmid\":123,\"from\":\"sezione sorgente\",\"destination\":\"sezione destinazione\",\"material\":\"titolo esatto dal catalogo\"}\n"
        . "14. rename_material: {\"action\":\"rename_material\",\"cmid\":123,\"target\":\"Nome sezione\",\"material\":\"titolo esatto dal catalogo\",\"new_name\":\"nuovo nome\"}\n"
        . "15. hide_material: {\"action\":\"hide_material\",\"cmid\":123,\"target\":\"Nome sezione\",\"material\":\"titolo esatto dal catalogo\"}\n"
        . "16. show_material: {\"action\":\"show_material\",\"cmid\":123,\"target\":\"Nome sezione\",\"material\":\"titolo esatto dal catalogo\"}\n"
        . "17. clear_section_content: {\"action\":\"clear_section_content\",\"target\":\"Nome o numero sezione\"}\n\n"

        . "REGOLE SEMANTICHE OBBLIGATORIE:\n"
        . "- Se il prompt parla di un materiale/file/risorsa/pdf/ppt/slide/documento, guarda PRIMA MATERIALI/RISORSE REALI DEL CORSO.\n"
        . "- Se il docente scrive male il nome, usa il materiale semanticamente più vicino nel catalogo.\n"
        . "- Esempio: se scrive 'tabellozza' e nel catalogo esiste 'tabella ascii.pdf', considera 'tabellozza' un riferimento probabile a quel PDF.\n"
        . "- Se il docente dice 'elimina/togli/rimuovi X dalla sezione Y' e X è simile a un materiale nel catalogo, usa delete_material, NON update_section_html.\n"
        . "- Se scegli un materiale dal catalogo, copia SEMPRE cmid e title esatti dal catalogo.\n"
        . "- Se più materiali sono plausibili nella stessa sezione, scegli quello più semanticamente vicino al prompt.\n"
        . "- Se il prompt riguarda testo/date/colori della sezione e NON un materiale, allora usa update_section_html.\n"
        . "- Non creare sezioni se il docente chiede solo di modificare/eliminare un materiale in una sezione esistente.\n"
        . "- Non inventare nomi di file: usa solo titoli presenti nel catalogo o file caricati nel form.\n\n"

        . "PROMPT DOCENTE:\n"
        . $teacherprompt . "\n\n"

        . "RISPOSTA JSON OBBLIGATORIA:\n"
        . "{\n"
        . "  \"actions\": [\n"
        . "    {\"action\":\"delete_material\",\"cmid\":123,\"target\":\"Exams\",\"material\":\"Materiale - tabella ascii.pdf\"}\n"
        . "  ]\n"
        . "}\n";

    try {
        $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
        $raw = $provider->generate($prompt, 5000, $system);
        $actions = local_aisn_cb_ai_decode_actions($raw);

        if (empty($actions)) {
            return [];
        }

        return local_aisn_cb_ai_normalize_material_actions($actions, $materialcatalog);
    } catch (Throwable $e) {
        debugging('AI Course Builder planner failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }
}

function local_aisn_cb_pick_files(array $files, array $wanted): array {
    if (empty($wanted)) {
        return $files;
    }

    $selected = [];

    foreach ($files as $file) {
        $name = local_aisn_cb_low(clean_param((string)($file['name'] ?? ''), PARAM_FILE));

        foreach ($wanted as $needle) {
            $needle = local_aisn_cb_low((string)$needle);

            if ($needle !== '' && str_contains($name, $needle)) {
                $selected[] = $file;
                break;
            }
        }
    }

    return !empty($selected) ? $selected : $files;
}

function local_aisn_cb_ai_clean_safe_html(string $html): string {
    $html = trim((string)$html);

    if ($html === '') {
        return '';
    }

    $html = preg_replace('#<(script|iframe|object|embed|form|input|button|meta|link)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|iframe|object|embed|form|input|button|meta|link)[^>]*/?>#is', '', (string)$html);
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', (string)$html);
    $html = preg_replace('/javascript\s*:/iu', '', (string)$html);
    $html = preg_replace('/expression\s*\(/iu', '', (string)$html);
    $html = preg_replace('/url\s*\(/iu', '', (string)$html);

    $allowed = '<h3><h4><h5><p><br><ul><ol><li><strong><b><em><i><span><div><table><thead><tbody><tr><th><td>';
    $html = strip_tags((string)$html, $allowed);

    return trim((string)$html);
}

function local_aisn_cb_ai_section_from_action(int $courseid, array $action): ?stdClass {
    $target = trim((string)($action['target'] ?? ''));

    if ($target === '' && isset($action['section_number'])) {
        $target = (string)$action['section_number'];
    }

    if ($target === '') {
        return null;
    }

    return local_aisn_cb_find_section($courseid, $target);
}

function local_aisn_cb_delete_material_normalize(string $value): string {
    $value = trim((string)$value);
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = core_text::strtolower(trim((string)$value));

    $value = preg_replace('/^materiale\s*-\s*/iu', '', $value);
    $value = preg_replace('/\.(pptx|ppt|pdf|txt|docx|doc|xlsx|xls)$/iu', '', $value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$value);

    return trim((string)$value);
}

function local_aisn_cb_delete_material_tokens(string $value): array {
    $value = trim((string)$value);
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/^materiale\s*-\s*/iu', '', $value);
    $value = preg_replace('/\.(pptx|ppt|pdf|txt|docx|doc|xlsx|xls)$/iu', '', (string)$value);
    $value = core_text::strtolower((string)$value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string)$value);
    $parts = preg_split('/\s+/u', trim((string)$value));

    return array_values(array_filter((array)$parts, static function($part): bool {
        $part = trim((string)$part);

        if ($part === '') {
            return false;
        }

        if (in_array($part, ['materiale', 'file', 'da', 'dal', 'dalla', 'lecture', 'lezione', 'sezione'], true)) {
            return false;
        }

        return core_text::strlen($part) >= 2;
    }));
}

function local_aisn_cb_delete_material_score(string $needle, string $candidate): int {
    $needlekey = local_aisn_cb_delete_material_normalize($needle);
    $candidatekey = local_aisn_cb_delete_material_normalize($candidate);

    if ($needlekey === '' || $candidatekey === '') {
        return 0;
    }

    if ($needlekey === $candidatekey) {
        return 1000;
    }

    $score = 0;

    if (str_contains($candidatekey, $needlekey) || str_contains($needlekey, $candidatekey)) {
        $score += 500;
    }

    foreach (local_aisn_cb_delete_material_tokens($needle) as $token) {
        if (str_contains($candidatekey, $token)) {
            $score += 220;
        }
    }

    return $score;
}

function local_aisn_cb_delete_material_find_cm(int $courseid, string $sectionref, string $materialname): ?stdClass {
    global $DB;

    $params = ['courseid' => $courseid];

    $sql = "SELECT cm.id AS cmid,
                   cm.course,
                   cm.section AS sectionid,
                   cs.section AS sectionnum,
                   cs.name AS sectionname,
                   m.name AS modname,
                   r.name AS resourcename
              FROM {course_modules} cm
              JOIN {course_sections} cs ON cs.id = cm.section
              JOIN {modules} m ON m.id = cm.module
         LEFT JOIN {resource} r ON r.id = cm.instance AND m.name = 'resource'
             WHERE cm.course = :courseid
               AND cm.deletioninprogress = 0
          ORDER BY cs.section ASC, cm.id ASC";

    $rows = $DB->get_records_sql($sql, $params);

    $section = null;

    if (trim($sectionref) !== '') {
        $section = local_aisn_cb_find_section($courseid, $sectionref);
    }

    $best = null;
    $bestscore = 0;

    foreach ($rows as $row) {
        if ($section && (int)$row->sectionid !== (int)$section->id) {
            continue;
        }

        $title = trim((string)($row->resourcename ?? ''));

        if ($title === '') {
            continue;
        }

        $score = local_aisn_cb_delete_material_score($materialname, $title);

        if ($score > $bestscore) {
            $best = $row;
            $bestscore = $score;
        }
    }

    if ($best && $bestscore >= 160) {
        return $best;
    }

    return null;
}

function local_aisn_cb_delete_material_from_course(int $courseid, string $sectionref, string $materialname): string {
    global $DB;

    $cm = local_aisn_cb_delete_material_find_cm($courseid, $sectionref, $materialname);

    if (!$cm) {
        return 'materiale non trovato: ' . $materialname;
    }

    $cmid = (int)$cm->cmid;
    $label = (string)($cm->resourcename ?? $materialname);

    try {
        if (function_exists('local_aisn_course_material_set_excluded')) {
            local_aisn_course_material_set_excluded($courseid, $cmid, true);
        }

        if ($DB->get_manager()->table_exists(new xmldb_table('local_aiskillnav_material'))) {
            $table = new xmldb_table('local_aiskillnav_material');
            $field = new xmldb_field('sourcecmid');

            if ($DB->get_manager()->field_exists($table, $field)) {
                $materials = $DB->get_records('local_aiskillnav_material', [
                    'courseid' => $courseid,
                    'sourcecmid' => $cmid,
                ]);

                foreach ($materials as $material) {
                    if (function_exists('local_aisn_kg_delete_material')) {
                        local_aisn_kg_delete_material((int)$material->id);
                    }

                    $DB->delete_records('local_aiskillnav_material', ['id' => (int)$material->id]);
                }
            }
        }

        course_delete_module($cmid);
        rebuild_course_cache($courseid, true);

        return 'materiale eliminato dal corso: ' . $label;
    } catch (Throwable $e) {
        debugging('AI Course Builder delete material failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return 'errore eliminazione materiale "' . $label . '": ' . $e->getMessage();
    }
}

// AISN_AI_DELETE_MATERIAL_HELPERS_V1

function local_aisn_cb_ai_mat_norm(string $value): string {
    $value = trim((string)$value);
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/^materiale\s*-\s*/iu', '', (string)$value);
    $value = preg_replace('/\.(pptx|ppt|pdf|txt|docx|doc|xlsx|xls|zip)$/iu', '', (string)$value);
    $value = core_text::strtolower((string)$value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$value);

    return trim((string)$value);
}

function local_aisn_cb_ai_mat_tokens(string $value): array {
    $value = trim((string)$value);
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/^materiale\s*-\s*/iu', '', (string)$value);
    $value = preg_replace('/\.(pptx|ppt|pdf|txt|docx|doc|xlsx|xls|zip)$/iu', '', (string)$value);
    $value = core_text::strtolower((string)$value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string)$value);
    $parts = preg_split('/\s+/u', trim((string)$value));

    return array_values(array_filter((array)$parts, static function($part): bool {
        $part = trim((string)$part);

        if ($part === '') {
            return false;
        }

        if (in_array($part, ['materiale', 'file', 'da', 'dal', 'dalla', 'lecture', 'lezione', 'sezione', 'pptx', 'pdf'], true)) {
            return false;
        }

        return core_text::strlen($part) >= 2;
    }));
}

function local_aisn_cb_ai_mat_score(string $needle, string $candidate): int {
    $needlekey = local_aisn_cb_ai_mat_norm($needle);
    $candidatekey = local_aisn_cb_ai_mat_norm($candidate);

    if ($needlekey === '' || $candidatekey === '') {
        return 0;
    }

    if ($needlekey === $candidatekey) {
        return 1000;
    }

    $score = 0;

    if (str_contains($candidatekey, $needlekey) || str_contains($needlekey, $candidatekey)) {
        $score += 500;
    }

    foreach (local_aisn_cb_ai_mat_tokens($needle) as $token) {
        if (str_contains($candidatekey, $token)) {
            $score += 220;
        }
    }

    return $score;
}

function local_aisn_cb_ai_mat_get_title(stdClass $cmrow): string {
    global $DB;

    $modname = trim((string)($cmrow->modname ?? ''));
    $instance = (int)($cmrow->instance ?? 0);

    if ($modname === '' || $instance <= 0) {
        return '';
    }

    try {
        $manager = $DB->get_manager();
        $table = new xmldb_table($modname);
        $field = new xmldb_field('name');

        if ($manager->table_exists($table) && $manager->field_exists($table, $field)) {
            $record = $DB->get_record($modname, ['id' => $instance], 'id,name', IGNORE_MISSING);

            if ($record && isset($record->name)) {
                return trim((string)$record->name);
            }
        }
    } catch (Throwable $e) {
        debugging('AI Course Builder material title lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return '';
}

function local_aisn_cb_ai_mat_find_cm(int $courseid, string $sectionref, string $materialname): ?stdClass {
    global $DB;

    $sql = "SELECT cm.id AS cmid,
                   cm.course,
                   cm.instance,
                   cm.visible,
                   cm.section AS sectionid,
                   cs.section AS sectionnum,
                   cs.name AS sectionname,
                   m.name AS modname
              FROM {course_modules} cm
              JOIN {course_sections} cs ON cs.id = cm.section
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.deletioninprogress = 0
          ORDER BY cs.section ASC, cm.id ASC";

    $rows = $DB->get_records_sql($sql, ['courseid' => $courseid]);

    $section = null;

    if (trim($sectionref) !== '') {
        $section = local_aisn_cb_find_section($courseid, $sectionref);
    }

    $best = null;
    $bestscore = 0;

    foreach ($rows as $row) {
        if ($section && (int)$row->sectionid !== (int)$section->id) {
            continue;
        }

        $title = local_aisn_cb_ai_mat_get_title($row);

        if ($title === '') {
            continue;
        }

        $score = local_aisn_cb_ai_mat_score($materialname, $title);

        if ($score > $bestscore) {
            $row->title = $title;
            $best = $row;
            $bestscore = $score;
        }
    }

    if ($best && $bestscore >= 160) {
        return $best;
    }

    return null;
}

function local_aisn_cb_ai_mat_remove_from_sequence(string $sequence, int $cmid): string {
    $items = array_values(array_filter(explode(',', trim($sequence)), static function($item) use ($cmid): bool {
        return (int)trim((string)$item) !== $cmid && trim((string)$item) !== '';
    }));

    return implode(',', $items);
}

function local_aisn_cb_ai_mat_add_to_sequence(string $sequence, int $cmid): string {
    $items = array_values(array_filter(explode(',', trim($sequence)), static function($item): bool {
        return trim((string)$item) !== '';
    }));

    if (!in_array((string)$cmid, $items, true)) {
        $items[] = (string)$cmid;
    }

    return implode(',', $items);
}

function local_aisn_cb_ai_mat_update_material_record_title(int $courseid, int $cmid, string $newname): void {
    global $DB;

    try {
        $manager = $DB->get_manager();
        $table = new xmldb_table('local_aiskillnav_material');

        if (!$manager->table_exists($table)) {
            return;
        }

        $field = new xmldb_field('sourcecmid');

        if (!$manager->field_exists($table, $field)) {
            return;
        }

        $materials = $DB->get_records('local_aiskillnav_material', [
            'courseid' => $courseid,
            'sourcecmid' => $cmid,
        ]);

        foreach ($materials as $material) {
            $material->title = $newname;

            if (property_exists($material, 'timemodified')) {
                $material->timemodified = time();
            }

            $DB->update_record('local_aiskillnav_material', $material);
        }
    } catch (Throwable $e) {
        debugging('AI Course Builder material table title update failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

function local_aisn_cb_ai_delete_material(int $courseid, string $sectionref, string $materialname): string {
    global $DB;

    $cm = local_aisn_cb_ai_mat_find_cm($courseid, $sectionref, $materialname);

    if (!$cm) {
        return 'materiale non trovato: ' . $materialname;
    }

    $cmid = (int)$cm->cmid;
    $label = (string)($cm->title ?? $materialname);

    try {
        if (function_exists('local_aisn_course_material_set_excluded')) {
            local_aisn_course_material_set_excluded($courseid, $cmid, true);
        }

        $manager = $DB->get_manager();
        $table = new xmldb_table('local_aiskillnav_material');

        if ($manager->table_exists($table)) {
            $field = new xmldb_field('sourcecmid');

            if ($manager->field_exists($table, $field)) {
                $materials = $DB->get_records('local_aiskillnav_material', [
                    'courseid' => $courseid,
                    'sourcecmid' => $cmid,
                ]);

                foreach ($materials as $material) {
                    if (function_exists('local_aisn_kg_delete_material')) {
                        local_aisn_kg_delete_material((int)$material->id);
                    }

                    $DB->delete_records('local_aiskillnav_material', ['id' => (int)$material->id]);
                }
            }
        }

        course_delete_module($cmid);
        rebuild_course_cache($courseid, true);

        return 'materiale eliminato dal corso: ' . $label;
    } catch (Throwable $e) {
        debugging('AI Course Builder delete material failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return 'errore eliminazione materiale "' . $label . '": ' . $e->getMessage();
    }
}

function local_aisn_cb_ai_move_material(int $courseid, string $fromsection, string $destinationsection, string $materialname): string {
    global $DB;

    $cm = local_aisn_cb_ai_mat_find_cm($courseid, $fromsection, $materialname);

    if (!$cm) {
        return 'materiale da spostare non trovato: ' . $materialname;
    }

    $destination = local_aisn_cb_find_section($courseid, $destinationsection);

    if (!$destination) {
        return 'sezione destinazione non trovata: ' . $destinationsection;
    }

    $oldsection = $DB->get_record('course_sections', ['id' => (int)$cm->sectionid], '*', MUST_EXIST);
    $cmid = (int)$cm->cmid;

    $oldsection->sequence = local_aisn_cb_ai_mat_remove_from_sequence((string)$oldsection->sequence, $cmid);
    $destination->sequence = local_aisn_cb_ai_mat_add_to_sequence((string)$destination->sequence, $cmid);

    $DB->update_record('course_sections', $oldsection);
    $DB->update_record('course_sections', $destination);
    $DB->set_field('course_modules', 'section', (int)$destination->id, ['id' => $cmid]);

    rebuild_course_cache($courseid, true);

    return 'materiale "' . (string)$cm->title . '" spostato in "' . (string)$destination->name . '"';
}

function local_aisn_cb_ai_rename_material(int $courseid, string $sectionref, string $materialname, string $newname): string {
    global $DB;

    $cm = local_aisn_cb_ai_mat_find_cm($courseid, $sectionref, $materialname);

    if (!$cm) {
        return 'materiale da rinominare non trovato: ' . $materialname;
    }

    $newname = trim($newname);

    if ($newname === '') {
        return 'nuovo nome materiale vuoto.';
    }

    try {
        $manager = $DB->get_manager();
        $table = new xmldb_table((string)$cm->modname);
        $field = new xmldb_field('name');

        if (!$manager->table_exists($table) || !$manager->field_exists($table, $field)) {
            return 'il modulo "' . (string)$cm->modname . '" non supporta rinomina automatica.';
        }

        $DB->set_field((string)$cm->modname, 'name', $newname, ['id' => (int)$cm->instance]);
        local_aisn_cb_ai_mat_update_material_record_title($courseid, (int)$cm->cmid, $newname);

        rebuild_course_cache($courseid, true);

        return 'materiale rinominato da "' . (string)$cm->title . '" a "' . $newname . '"';
    } catch (Throwable $e) {
        debugging('AI Course Builder rename material failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return 'errore rinomina materiale: ' . $e->getMessage();
    }
}

function local_aisn_cb_ai_set_material_visibility(int $courseid, string $sectionref, string $materialname, bool $visible): string {
    global $DB;

    $cm = local_aisn_cb_ai_mat_find_cm($courseid, $sectionref, $materialname);

    if (!$cm) {
        return 'materiale non trovato: ' . $materialname;
    }

    $cmid = (int)$cm->cmid;

    try {
        if (function_exists('set_coursemodule_visible')) {
            set_coursemodule_visible($cmid, $visible ? 1 : 0);
        } else {
            $DB->set_field('course_modules', 'visible', $visible ? 1 : 0, ['id' => $cmid]);
        }

        rebuild_course_cache($courseid, true);

        return 'materiale "' . (string)$cm->title . '" ' . ($visible ? 'reso visibile' : 'nascosto');
    } catch (Throwable $e) {
        debugging('AI Course Builder material visibility failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return 'errore visibilità materiale: ' . $e->getMessage();
    }
}

function local_aisn_cb_ai_clear_section_content(int $courseid, string $sectionref): string {
    global $DB;

    $section = local_aisn_cb_find_section($courseid, $sectionref);

    if (!$section) {
        return 'sezione non trovata: ' . $sectionref;
    }

    if (function_exists('local_aisn_cb_set_section_summary_html')) {
        local_aisn_cb_set_section_summary_html($section, '');
    } else {
        $section->summary = '';
        $section->summaryformat = FORMAT_HTML;
        $DB->update_record('course_sections', $section);
        rebuild_course_cache($courseid, true);
    }

    return 'contenuto testuale svuotato nella sezione "' . (string)$section->name . '"';
}

// AISN_AI_MATERIAL_ACTIONS_FULL_HELPERS_V1

function local_aisn_cb_execute_ai_actions(int $courseid, int $userid, array $actions, array $files): array {
    $logs = [];
    $lastsection = null;

    foreach ($actions as $a) {
        if (!is_array($a)) {
            continue;
        }

        $type = local_aisn_cb_low((string)($a['action'] ?? ''));

        if ($type === 'delete_all_sections') {
            $logs = array_merge($logs, local_aisn_cb_delete_all_sections($courseid));
            $lastsection = null;
            continue;
        }

        if ($type === 'clear_section_zero') {
            $section = local_aisn_cb_get_section($courseid, 0);

            if ($section) {
                $logs[] = 'AI: section 0 / General ' . local_aisn_cb_delete_section($courseid, $section) . '.';
            } else {
                $logs[] = 'AI: section 0 non trovata.';
            }

            continue;
        }

        if ($type === 'create_section') {
            $title = trim((string)($a['title'] ?? 'Nuova sezione'));
            $html = '';

            if (!empty($a['summary_html'])) {
                $html = local_aisn_cb_ai_clean_safe_html((string)$a['summary_html']);
            } else if (!empty($a['summary'])) {
                $html = '<p>' . s((string)$a['summary']) . '</p>';
            }

            $section = local_aisn_cb_ensure_section($courseid, $title, $html);
            $lastsection = $section;
            $logs[] = 'AI: sezione pronta "' . (string)$section->name . '".';
            continue;
        }

        if ($type === 'rename_section') {
            $section = local_aisn_cb_ai_section_from_action($courseid, $a);

            if ($section) {
                local_aisn_cb_update_section($section, (string)($a['title'] ?? ''), '');
                $logs[] = 'AI: sezione rinominata in "' . (string)($a['title'] ?? '') . '".';
            } else {
                $logs[] = 'AI: sezione da rinominare non trovata: ' . (string)($a['target'] ?? '');
            }

            continue;
        }

        if ($type === 'update_section_html' || $type === 'update_summary') {
            $section = local_aisn_cb_ai_section_from_action($courseid, $a);

            if (!$section) {
                $logs[] = 'AI: sezione da aggiornare non trovata: ' . (string)($a['target'] ?? '');
                continue;
            }

            $html = '';

            if (!empty($a['html'])) {
                $html = local_aisn_cb_ai_clean_safe_html((string)$a['html']);
            } else if (!empty($a['summary_html'])) {
                $html = local_aisn_cb_ai_clean_safe_html((string)$a['summary_html']);
            } else if (!empty($a['summary'])) {
                $html = local_aisn_cb_ai_clean_safe_html((string)$a['summary']);
            }

            if ($html === '') {
                $logs[] = 'AI: HTML vuoto, sezione non aggiornata: ' . (string)$section->name . '.';
                continue;
            }

            local_aisn_cb_set_section_summary_html($section, $html);
            $logs[] = 'AI: contenuto aggiornato nella sezione "' . (string)$section->name . '".';
            continue;
        }

        if ($type === 'delete_section') {
            $section = local_aisn_cb_ai_section_from_action($courseid, $a);

            if ($section) {
                $logs[] = 'AI: sezione "' . (string)($section->name ?: ('Section ' . (int)$section->section)) . '" ' . local_aisn_cb_delete_section($courseid, $section) . '.';
            } else {
                $logs[] = 'AI: sezione da eliminare non trovata: ' . (string)($a['target'] ?? '');
            }

            continue;
        }

        if ($type === 'hide_section') {
            $section = local_aisn_cb_ai_section_from_action($courseid, $a);

            if ($section) {
                local_aisn_cb_set_visibility($section, false);
                $logs[] = 'AI: sezione "' . (string)$section->name . '" nascosta.';
            } else {
                $logs[] = 'AI: sezione da nascondere non trovata: ' . (string)($a['target'] ?? '');
            }

            continue;
        }

        if ($type === 'show_section') {
            $section = local_aisn_cb_ai_section_from_action($courseid, $a);

            if ($section) {
                local_aisn_cb_set_visibility($section, true);
                $logs[] = 'AI: sezione "' . (string)$section->name . '" resa visibile.';
            } else {
                $logs[] = 'AI: sezione da mostrare non trovata: ' . (string)($a['target'] ?? '');
            }

            continue;
        }

        if ($type === 'duplicate_section') {
            $section = local_aisn_cb_ai_section_from_action($courseid, $a);

            if ($section) {
                $copy = local_aisn_cb_duplicate_section($courseid, $section, (string)($a['new_title'] ?? ''));
                $lastsection = $copy;
                $logs[] = 'AI: sezione duplicata come "' . (string)$copy->name . '".';
            } else {
                $logs[] = 'AI: sezione da duplicare non trovata: ' . (string)($a['target'] ?? '');
            }

            continue;
        }

        if ($type === 'move_section') {
            $section = local_aisn_cb_ai_section_from_action($courseid, $a);

            if ($section) {
                $destination = (int)($a['destination'] ?? 1);
                $logs[] = 'AI: sezione "' . (string)$section->name . '" ' . local_aisn_cb_move_section($courseid, $section, $destination) . '.';
            } else {
                $logs[] = 'AI: sezione da spostare non trovata: ' . (string)($a['target'] ?? '');
            }

            continue;
        }

        // AISN_AI_DELETE_MATERIAL_EXECUTOR_V1
        if ($type === 'delete_material') {
            $sectiontarget = (string)($a['target'] ?? '');
            $materialname = (string)($a['material'] ?? $a['file'] ?? $a['filename'] ?? $a['name'] ?? '');

            if (trim($materialname) === '') {
                $logs[] = 'AI: delete_material senza nome materiale.';
                continue;
            }

            $logs[] = 'AI: ' . local_aisn_cb_ai_delete_material($courseid, $sectiontarget, $materialname);
            continue;
        }
        // AISN_AI_MATERIAL_ACTIONS_FULL_EXECUTOR_V1
        if ($type === 'delete_material') {
            $sectiontarget = (string)($a['target'] ?? $a['section'] ?? '');
            $materialname = (string)($a['material'] ?? $a['file'] ?? $a['filename'] ?? $a['name'] ?? '');

            if (trim($materialname) === '') {
                $logs[] = 'AI: delete_material senza nome materiale.';
                continue;
            }

            $logs[] = 'AI: ' . local_aisn_cb_ai_delete_material($courseid, $sectiontarget, $materialname);
            continue;
        }

        if ($type === 'move_material') {
            $from = (string)($a['from'] ?? $a['source'] ?? $a['target'] ?? '');
            $destination = (string)($a['destination'] ?? $a['to'] ?? '');
            $materialname = (string)($a['material'] ?? $a['file'] ?? $a['filename'] ?? $a['name'] ?? '');

            if (trim($materialname) === '' || trim($destination) === '') {
                $logs[] = 'AI: move_material richiede material e destination.';
                continue;
            }

            $logs[] = 'AI: ' . local_aisn_cb_ai_move_material($courseid, $from, $destination, $materialname);
            continue;
        }

        if ($type === 'rename_material') {
            $sectiontarget = (string)($a['target'] ?? $a['section'] ?? '');
            $materialname = (string)($a['material'] ?? $a['file'] ?? $a['filename'] ?? $a['name'] ?? '');
            $newname = (string)($a['new_name'] ?? $a['newname'] ?? $a['title'] ?? '');

            if (trim($materialname) === '' || trim($newname) === '') {
                $logs[] = 'AI: rename_material richiede material e new_name.';
                continue;
            }

            $logs[] = 'AI: ' . local_aisn_cb_ai_rename_material($courseid, $sectiontarget, $materialname, $newname);
            continue;
        }

        if ($type === 'hide_material' || $type === 'show_material' || $type === 'set_material_visibility') {
            $sectiontarget = (string)($a['target'] ?? $a['section'] ?? '');
            $materialname = (string)($a['material'] ?? $a['file'] ?? $a['filename'] ?? $a['name'] ?? '');
            $visible = $type === 'show_material';

            if ($type === 'set_material_visibility' && array_key_exists('visible', $a)) {
                $visible = !empty($a['visible']);
            }

            if (trim($materialname) === '') {
                $logs[] = 'AI: visibilità materiale senza nome materiale.';
                continue;
            }

            $logs[] = 'AI: ' . local_aisn_cb_ai_set_material_visibility($courseid, $sectiontarget, $materialname, $visible);
            continue;
        }

        if ($type === 'clear_section_content') {
            $sectiontarget = (string)($a['target'] ?? $a['section'] ?? '');

            if (trim($sectiontarget) === '') {
                $logs[] = 'AI: clear_section_content senza sezione target.';
                continue;
            }

            $logs[] = 'AI: ' . local_aisn_cb_ai_clear_section_content($courseid, $sectiontarget);
            continue;
        }
        if ($type === 'attach_files') {
            $section = local_aisn_cb_ai_section_from_action($courseid, $a);

            if (!$section && $lastsection) {
                $section = $lastsection;
            }

            if (!$section) {
                $logs[] = 'AI: sezione target non trovata per collegare file: ' . (string)($a['target'] ?? '') . '. Nessuna nuova sezione creata automaticamente.';
                continue;
            }

            $wanted = !empty($a['files']) && is_array($a['files']) ? $a['files'] : [];
            $logs = array_merge($logs, local_aisn_cb_attach_files_to_section($courseid, $userid, $section, local_aisn_cb_pick_files($files, $wanted)));
            continue;
        }

        $logs[] = 'AI: azione non supportata: ' . $type . '.';
    }

    if (!empty($logs)) {
        local_aisn_cb_sync_resources($courseid);
    }

    return $logs;
}


function local_aisn_cb_execute_prompt(int $courseid, int $userid, string $prompt, array $files): array {
    $actions = local_aisn_cb_ai_plan_actions($courseid, $prompt, $files);

    if (!empty($actions)) {
        return array_merge(
            ['AI planner FIRST: prompt interpretato dall’AI con catalogo reale di sezioni e materiali.'],
            local_aisn_cb_execute_ai_actions($courseid, $userid, $actions, $files)
        );
    }

    $direct = local_aisn_cb_execute_direct($courseid, $userid, $prompt, $files);

    if (!empty($direct)) {
        return array_merge(
            ['Fallback locale usato perché l’AI non ha restituito azioni valide.'],
            $direct
        );
    }

    return [
        'Nessuna azione eseguita: AI non ha restituito azioni valide e il fallback locale non ha riconosciuto il prompt.',
    ];
}

// AISN_EXECUTE_PROMPT_AI_FIRST_STABLE

$result = [];
$error = '';

if ($action === 'build') {
    if (!confirm_sesskey()) {
        $error = 'Sessione non valida. Ricarica la pagina.';
    } else if (trim($prompt) === '') {
        $error = 'Inserisci un prompt.';
    } else {
        try {
            $result = local_aisn_cb_execute_prompt($courseid, (int)$USER->id, $prompt, local_aisn_cb_uploaded_files());
            // AISN_EMERGENCY_FAST_FINAL_REBUILD
            rebuild_course_cache($courseid, true);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();

if (function_exists('local_aiskillnavigator_print_inline_styles')) {
    local_aiskillnavigator_print_inline_styles();
}

echo html_writer::tag('style', '
.aisn-p2m-hero {
    background: linear-gradient(135deg,#0f6cbf,#2563eb);
    color: #fff;
    border-radius: 24px;
    padding: 26px 30px;
    margin-bottom: 18px;
    box-shadow: 0 18px 40px rgba(15,108,191,.22);
}
.aisn-p2m-hero h2 { margin: 0 0 8px; font-weight: 900; }
.aisn-p2m-card {
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:22px;
    padding:24px;
    box-shadow:0 14px 34px rgba(15,23,42,.07);
    margin-bottom:18px;
}
.aisn-p2m-help {
    background:#eff6ff;
    border:1px solid #bfdbfe;
    border-radius:18px;
    padding:16px;
    margin-bottom:18px;
}
.aisn-p2m-result {
    border-left:6px solid #16a34a;
    background:#f0fdf4;
    border-radius:16px;
    padding:16px;
    margin-bottom:18px;
}
.aisn-p2m-muted { color:#64748b; }
');

echo html_writer::start_div('container-fluid');

echo html_writer::start_div('aisn-p2m-hero');
echo html_writer::tag('h2', 'AI Course Builder');
echo html_writer::tag('p', 'Prompt-to-Moodle AI editor: crea, modifica, collega materiali e gestisce sezioni Moodle tramite prompt docente.');
echo html_writer::end_div();

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

if (!empty($result)) {
    echo html_writer::start_div('aisn-p2m-result');
    echo html_writer::tag('h3', 'Operazioni eseguite');
    echo html_writer::start_tag('ul');
    foreach ($result as $line) {
        echo html_writer::tag('li', s($line));
    }
    echo html_writer::end_tag('ul');
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Vai al corso e verifica', ['class' => 'btn btn-success']);
    echo html_writer::end_div();
}

echo html_writer::start_div('aisn-p2m-card');
echo html_writer::tag('h3', 'Prompt unico per modificare il corso');
echo html_writer::tag('p', 'Scrivi un prompt naturale. Se carichi file, il Course Builder organizza e collega i materiali anche senza dipendere dal planner AI.', ['class' => 'aisn-p2m-muted']);

echo html_writer::start_div('aisn-p2m-help');
echo html_writer::tag('strong', 'Esempi validi:');
echo html_writer::tag('pre',
'Crea sezione "Exams" con questo testo: ...
Metti tutti i file caricati nella sezione "Lecture 02 - Business Intelligence e Big Data"
Inserisci i file caricati nelle sezioni già create in base al nome del file. Non creare nuove sezioni.
Togli la sezione "simulazione"
Rinomina sezione "HTML base" in "HTML e CSS base"'
);
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'method' => 'post',
    'enctype' => 'multipart/form-data',
    'action' => new moodle_url('/local/aiskillnavigator/pages/course_builder.php', ['courseid' => $courseid]),
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'build']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'MAX_FILE_SIZE', 'value' => 512 * 1024 * 1024]);

echo html_writer::tag('label', 'Prompt docente', ['for' => 'prompt']);
echo html_writer::tag('textarea', s($prompt), [
    'id' => 'prompt',
    'name' => 'prompt',
    'class' => 'form-control mb-3',
    'rows' => 10,
    'required' => 'required',
    'placeholder' => 'Esempio: crea una sezione per ogni file e metti un file per sezione'
]);

echo html_writer::tag('label', 'Materiali da usare nel prompt', ['for' => 'materials']);
echo html_writer::empty_tag('input', [
    'type' => 'file',
    'id' => 'materials',
    'name' => 'materials[]',
    'class' => 'form-control mb-3',
    'multiple' => 'multiple',
    'accept' => '.txt,.md,.csv,.json,.xml,.html,.htm,.css,.js,.ts,.sql,.cs,.java,.py,.cpp,.c,.pptx,.docx,.pdf,.png,.jpg,.jpeg,.bmp,.webp,.tif,.tiff'
]);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => 'Esegui prompt sul corso Moodle'
]);

echo ' ';
echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Torna al corso', ['class' => 'btn btn-secondary']);

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

if (function_exists('local_aisn_back_to_course_autofix')) {
    echo local_aisn_back_to_course_autofix((int)$courseid);
}

echo $OUTPUT->footer();