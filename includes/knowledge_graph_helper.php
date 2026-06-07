<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/material_exclusion_helper.php');
require_once(__DIR__ . '/material_ai_policy.php');

function local_aisn_kg_table_exists(string $name): bool {
    global $DB;
    return $DB->get_manager()->table_exists(new xmldb_table($name));
}

function local_aisn_kg_ensure_schema(): void {
    global $DB;

    $dbman = $DB->get_manager();

    $concept = new xmldb_table('local_aisn_kg_concept');

    if (!$dbman->table_exists($concept)) {
        $concept->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $concept->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $concept->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $concept->add_field('normalizedname', XMLDB_TYPE_CHAR, '191', null, XMLDB_NOTNULL, null, '');
        $concept->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $concept->add_field('confidence', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $concept->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $concept->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $concept->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $concept->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $concept->add_index('norm_ix', XMLDB_INDEX_NOTUNIQUE, ['normalizedname']);
        $dbman->create_table($concept);
    }

    $source = new xmldb_table('local_aisn_kg_source');

    if (!$dbman->table_exists($source)) {
        $source->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $source->add_field('conceptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $source->add_field('materialid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $source->add_field('chunkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $source->add_field('evidence', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $source->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $source->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $source->add_index('concept_ix', XMLDB_INDEX_NOTUNIQUE, ['conceptid']);
        $source->add_index('material_ix', XMLDB_INDEX_NOTUNIQUE, ['materialid']);
        $source->add_index('chunk_ix', XMLDB_INDEX_NOTUNIQUE, ['chunkid']);
        $dbman->create_table($source);
    }

    $relation = new xmldb_table('local_aisn_kg_relation');

    if (!$dbman->table_exists($relation)) {
        $relation->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $relation->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $relation->add_field('sourceconceptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $relation->add_field('targetconceptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $relation->add_field('relationtype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'related_to');
        $relation->add_field('confidence', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $relation->add_field('materialid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $relation->add_field('chunkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $relation->add_field('evidence', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $relation->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $relation->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $relation->add_index('course_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $relation->add_index('source_ix', XMLDB_INDEX_NOTUNIQUE, ['sourceconceptid']);
        $relation->add_index('target_ix', XMLDB_INDEX_NOTUNIQUE, ['targetconceptid']);
        $relation->add_index('material_ix', XMLDB_INDEX_NOTUNIQUE, ['materialid']);
        $dbman->create_table($relation);
    }
}


function local_aisn_kg_material_allowed_for_graph(stdClass $material): bool {
    if (function_exists('local_aiskillnavigator_material_external_allowed')) {
        return local_aiskillnavigator_material_external_allowed($material);
    }

    if (isset($material->externalaiallowed)) {
        return ((int)$material->externalaiallowed) === 1;
    }

    if (isset($material->aipolicy)) {
        return ((string)$material->aipolicy) === 'external_allowed';
    }

    return false;
}

function local_aisn_kg_text_len(string $text): int {
    return class_exists('core_text') ? core_text::strlen($text) : strlen($text);
}

function local_aisn_kg_substr(string $text, int $start, int $length): string {
    return class_exists('core_text') ? core_text::substr($text, $start, $length) : substr($text, $start, $length);
}

function local_aisn_kg_lower(string $text): string {
    return class_exists('core_text') ? core_text::strtolower($text) : strtolower($text);
}

function local_aisn_kg_normalize(string $name): string {
    $name = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = preg_replace('/[_\-]+/u', ' ', $name);
    $name = preg_replace('/[^\p{L}\p{N}\s\+\#\.]/u', '', (string)$name);
    $name = preg_replace('/\s+/u', ' ', (string)$name);
    $name = trim((string)$name);

    if ($name === '') {
        return '';
    }

    return local_aisn_kg_substr(local_aisn_kg_lower($name), 0, 191);
}

function local_aisn_kg_clean_text(string $text): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/u', ' ', (string)$text);
    $text = preg_replace("/\n{3,}/u", "\n\n", (string)$text);
    return trim((string)$text);
}

function local_aisn_kg_sentence_excerpt(string $text, string $term): string {
    $text = local_aisn_kg_clean_text($text);
    $termnorm = local_aisn_kg_normalize($term);
    $sentences = preg_split('/(?<=[\.\!\?\:\;])\s+/u', $text) ?: [$text];

    foreach ($sentences as $sentence) {
        if ($termnorm !== '' && str_contains(local_aisn_kg_normalize($sentence), $termnorm)) {
            return local_aisn_kg_substr(trim($sentence), 0, 420);
        }
    }

    return local_aisn_kg_substr($text, 0, 420);
}

function local_aisn_kg_stopwords(): array {
    $words = [
        'alla','allo','alle','agli','dalla','dello','delle','degli','nella','nello','nelle','negli',
        'con','che','per','una','uno','del','dei','dal','sul','sui','sua','suo','sue','suoi',
        'sono','essere','come','questo','questa','questi','queste','quello','quella','quelli','quelle',
        'viene','vengono','puo','può','deve','devono','fare','fatto','parte','modo','caso',
        'the','and','for','with','from','this','that','into','are','was','were','have','has','can',
        'file','materiale','lezione','pagina','sezione','obiettivo','spiegazione','studenti','docente',
        'course','resource','moodle','test','quiz','domanda','risposta',
    ];

    return array_fill_keys($words, true);
}

function local_aisn_kg_extract_terms(string $text, int $limit = 36): array {
    $text = local_aisn_kg_clean_text($text);

    if ($text === '') {
        return [];
    }

    $stop = local_aisn_kg_stopwords();
    $scores = [];
    $evidence = [];

    $lines = preg_split('/\n+/u', $text) ?: [];

    foreach ($lines as $line) {
        $line = trim((string)$line);

        if ($line === '') {
            continue;
        }

        $cleanline = preg_replace('/^\s*(file|sezione|capitolo|chapter)\s*[:\-]?\s*/iu', '', $line);
        $wordcount = count(preg_split('/\s+/u', trim((string)$cleanline)) ?: []);

        if ($wordcount >= 2 && $wordcount <= 9 && local_aisn_kg_text_len($cleanline) <= 90) {
            $norm = local_aisn_kg_normalize($cleanline);

            if ($norm !== '') {
                $scores[$norm] = ($scores[$norm] ?? 0) + 7;
                $evidence[$norm] = $evidence[$norm] ?? $line;
            }
        }
    }

    $sentences = preg_split('/(?<=[\.\!\?\:\;])\s+/u', $text) ?: [$text];

    foreach ($sentences as $sentence) {
        $sentence = trim((string)$sentence);

        if ($sentence === '') {
            continue;
        }

        if (preg_match_all('/\b(?:concetto|argomento|abilità|ability|competenza|tema|modulo|funzione|classe|metodo|tabella|query|formula|dominio|codominio)\s+(?:di|del|della|su|per)?\s*([A-ZÀ-Ýa-zà-ÿ0-9][A-ZÀ-Ýa-zà-ÿ0-9\s\+\#\._-]{2,70})/iu', $sentence, $matches)) {
            foreach ($matches[1] as $match) {
                $candidate = trim((string)$match);
                $candidate = preg_replace('/\s+(è|sono|ha|hanno|descrive|serve|permette).*$/iu', '', (string)$candidate);
                $norm = local_aisn_kg_normalize($candidate);

                if ($norm !== '') {
                    $scores[$norm] = ($scores[$norm] ?? 0) + 8;
                    $evidence[$norm] = $evidence[$norm] ?? $sentence;
                }
            }
        }

        $tokens = preg_split('/[^\p{L}\p{N}\+\#\.]+/u', $sentence) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens), function($token) use ($stop) {
            $norm = local_aisn_kg_normalize($token);

            if ($norm === '' || isset($stop[$norm])) {
                return false;
            }

            if (preg_match('/^\d+$/', $norm)) {
                return false;
            }

            return local_aisn_kg_text_len($norm) >= 4;
        }));

        $max = count($tokens);

        for ($i = 0; $i < $max; $i++) {
            for ($n = 1; $n <= 3; $n++) {
                if ($i + $n > $max) {
                    continue;
                }

                $candidate = implode(' ', array_slice($tokens, $i, $n));
                $norm = local_aisn_kg_normalize($candidate);

                if ($norm === '') {
                    continue;
                }

                $score = $n === 1 ? 1 : ($n === 2 ? 3 : 5);
                $scores[$norm] = ($scores[$norm] ?? 0) + $score;
                $evidence[$norm] = $evidence[$norm] ?? $sentence;
            }
        }
    }

    arsort($scores);

    $result = [];

    foreach ($scores as $norm => $score) {
        if (count($result) >= $limit) {
            break;
        }

        $name = local_aisn_kg_substr($norm, 0, 255);

        if ($name === '' || local_aisn_kg_text_len($name) < 4) {
            continue;
        }

        $result[$norm] = [
            'name' => $name,
            'normalizedname' => $norm,
            'confidence' => min(98, max(45, (int)$score * 6)),
            'evidence' => local_aisn_kg_substr((string)($evidence[$norm] ?? ''), 0, 500),
        ];
    }

    return $result;
}

function local_aisn_kg_get_chunks_for_material(stdClass $material): array {
    global $DB;

    $chunks = [];

    if (local_aisn_kg_table_exists('local_aiskillnav_chunk')) {
        $records = $DB->get_records('local_aiskillnav_chunk', ['materialid' => (int)$material->id], 'chunkindex ASC, id ASC');

        foreach ($records as $record) {
            $text = trim((string)($record->chunktext ?? ''));

            if ($text !== '') {
                $chunks[] = [
                    'chunkid' => (int)$record->id,
                    'text' => $text,
                ];
            }
        }
    }

    if (!empty($chunks)) {
        return $chunks;
    }

    $content = local_aisn_kg_clean_text((string)($material->content ?? ''));

    if ($content === '') {
        return [];
    }

    $parts = preg_split('/\n\s*\n/u', $content) ?: [$content];
    $i = 0;

    foreach ($parts as $part) {
        $part = trim((string)$part);

        if ($part === '') {
            continue;
        }

        $chunks[] = [
            'chunkid' => 0,
            'text' => local_aisn_kg_substr($part, 0, 2200),
        ];

        $i++;

        if ($i >= 16) {
            break;
        }
    }

    return $chunks;
}

function local_aisn_kg_find_concept(int $courseid, string $normalizedname): ?stdClass {
    global $DB;

    $records = $DB->get_records(
        'local_aisn_kg_concept',
        ['courseid' => $courseid, 'normalizedname' => $normalizedname],
        'id ASC',
        '*',
        0,
        1
    );

    if (empty($records)) {
        return null;
    }

    return reset($records);
}

function local_aisn_kg_upsert_concept(int $courseid, array $term): int {
    global $DB;

    $normalized = (string)($term['normalizedname'] ?? '');

    if ($normalized === '') {
        return 0;
    }

    $now = time();
    $existing = local_aisn_kg_find_concept($courseid, $normalized);

    if ($existing) {
        $changed = false;

        if ((int)$existing->confidence < (int)$term['confidence']) {
            $existing->confidence = (int)$term['confidence'];
            $changed = true;
        }

        if (trim((string)$existing->description) === '' && !empty($term['evidence'])) {
            $existing->description = local_aisn_kg_substr((string)$term['evidence'], 0, 900);
            $changed = true;
        }

        if ($changed) {
            $existing->timemodified = $now;
            $DB->update_record('local_aisn_kg_concept', $existing);
        }

        return (int)$existing->id;
    }

    $record = new stdClass();
    $record->courseid = $courseid;
    $record->name = local_aisn_kg_substr((string)$term['name'], 0, 255);
    $record->normalizedname = local_aisn_kg_substr($normalized, 0, 191);
    $record->description = local_aisn_kg_substr((string)($term['evidence'] ?? ''), 0, 900);
    $record->confidence = (int)($term['confidence'] ?? 50);
    $record->timecreated = $now;
    $record->timemodified = $now;

    return (int)$DB->insert_record('local_aisn_kg_concept', $record);
}

function local_aisn_kg_add_source(int $conceptid, int $materialid, int $chunkid, string $evidence): void {
    global $DB;

    if ($conceptid <= 0 || $materialid <= 0) {
        return;
    }

    if ($DB->record_exists('local_aisn_kg_source', [
        'conceptid' => $conceptid,
        'materialid' => $materialid,
        'chunkid' => $chunkid,
    ])) {
        return;
    }

    $record = new stdClass();
    $record->conceptid = $conceptid;
    $record->materialid = $materialid;
    $record->chunkid = $chunkid;
    $record->evidence = local_aisn_kg_substr($evidence, 0, 900);
    $record->timecreated = time();

    $DB->insert_record('local_aisn_kg_source', $record);
}

function local_aisn_kg_add_relation(
    int $courseid,
    int $sourceid,
    int $targetid,
    string $type,
    int $confidence,
    int $materialid,
    int $chunkid,
    string $evidence
): void {
    global $DB;

    if ($sourceid <= 0 || $targetid <= 0 || $sourceid === $targetid) {
        return;
    }

    if ($sourceid > $targetid && $type === 'related_to') {
        [$sourceid, $targetid] = [$targetid, $sourceid];
    }

    if ($DB->record_exists('local_aisn_kg_relation', [
        'courseid' => $courseid,
        'sourceconceptid' => $sourceid,
        'targetconceptid' => $targetid,
        'relationtype' => $type,
        'materialid' => $materialid,
        'chunkid' => $chunkid,
    ])) {
        return;
    }

    $record = new stdClass();
    $record->courseid = $courseid;
    $record->sourceconceptid = $sourceid;
    $record->targetconceptid = $targetid;
    $record->relationtype = local_aisn_kg_substr($type, 0, 40);
    $record->confidence = max(0, min(100, $confidence));
    $record->materialid = $materialid;
    $record->chunkid = $chunkid;
    $record->evidence = local_aisn_kg_substr($evidence, 0, 900);
    $record->timecreated = time();

    $DB->insert_record('local_aisn_kg_relation', $record);
}

function local_aisn_kg_delete_orphan_concepts(int $courseid): void {
    global $DB;

    $sql = "SELECT c.id
              FROM {local_aisn_kg_concept} c
         LEFT JOIN {local_aisn_kg_source} s ON s.conceptid = c.id
             WHERE c.courseid = :courseid AND s.id IS NULL";

    $orphans = $DB->get_fieldset_sql($sql, ['courseid' => $courseid]);

    if (empty($orphans)) {
        return;
    }

    list($insql, $params) = $DB->get_in_or_equal($orphans, SQL_PARAMS_NAMED, 'kgc');
    $DB->delete_records_select('local_aisn_kg_relation', "sourceconceptid {$insql}", $params);
    $DB->delete_records_select('local_aisn_kg_relation', "targetconceptid {$insql}", $params);
    $DB->delete_records_select('local_aisn_kg_concept', "id {$insql}", $params);
}

function local_aisn_kg_delete_material(int $materialid): void {
    global $DB;

    local_aisn_kg_ensure_schema();

    if ($materialid <= 0) {
        return;
    }

    $material = $DB->get_record('local_aiskillnav_material', ['id' => $materialid]);
    $courseid = $material ? (int)$material->courseid : 0;

    $DB->delete_records('local_aisn_kg_relation', ['materialid' => $materialid]);
    $DB->delete_records('local_aisn_kg_source', ['materialid' => $materialid]);

    if ($courseid > 0) {
        local_aisn_kg_delete_orphan_concepts($courseid);
    }
}

function local_aisn_kg_rebuild_material(int $materialid): array {
    global $DB;

    local_aisn_kg_ensure_schema();

    $material = $DB->get_record('local_aiskillnav_material', ['id' => $materialid]);

    if (!$material) {
        local_aisn_kg_delete_material($materialid);
        return ['concepts' => 0, 'relations' => 0];
    }

    $courseid = (int)$material->courseid;

    if (!local_aisn_kg_material_allowed_for_graph($material)) {
        local_aisn_kg_delete_material($materialid);
        return ['concepts' => 0, 'relations' => 0];
    }

    local_aisn_kg_delete_material($materialid);

    $chunks = local_aisn_kg_get_chunks_for_material($material);
    $conceptids = [];
    $chunkconcepts = [];

    foreach ($chunks as $chunk) {
        $terms = local_aisn_kg_extract_terms((string)$chunk['text'], 24);

        foreach ($terms as $term) {
            $conceptid = local_aisn_kg_upsert_concept($courseid, $term);

            if ($conceptid <= 0) {
                continue;
            }

            $conceptids[$term['normalizedname']] = $conceptid;
            $chunkconcepts[(int)$chunk['chunkid']][$term['normalizedname']] = [
                'id' => $conceptid,
                'name' => $term['name'],
                'evidence' => $term['evidence'] !== '' ? $term['evidence'] : local_aisn_kg_sentence_excerpt($chunk['text'], $term['name']),
                'confidence' => (int)$term['confidence'],
            ];

            local_aisn_kg_add_source(
                $conceptid,
                (int)$material->id,
                (int)$chunk['chunkid'],
                $term['evidence'] !== '' ? $term['evidence'] : local_aisn_kg_sentence_excerpt($chunk['text'], $term['name'])
            );
        }
    }

    $relationcount = 0;

    foreach ($chunkconcepts as $chunkid => $items) {
        $items = array_slice($items, 0, 12, true);
        $keys = array_keys($items);

        for ($i = 0; $i < count($keys); $i++) {
            for ($j = $i + 1; $j < count($keys); $j++) {
                $a = $items[$keys[$i]];
                $b = $items[$keys[$j]];

                $type = 'related_to';
                $confidence = min(90, (int)(($a['confidence'] + $b['confidence']) / 2));

                if (str_contains($keys[$i], $keys[$j]) && $keys[$i] !== $keys[$j]) {
                    $type = 'is_a';
                } else if (str_contains($keys[$j], $keys[$i]) && $keys[$i] !== $keys[$j]) {
                    $type = 'is_a';
                    [$a, $b] = [$b, $a];
                }

                local_aisn_kg_add_relation(
                    $courseid,
                    (int)$a['id'],
                    (int)$b['id'],
                    $type,
                    $confidence,
                    (int)$material->id,
                    (int)$chunkid,
                    (string)$a['evidence']
                );

                $relationcount++;
            }
        }
    }

    return ['concepts' => count($conceptids), 'relations' => $relationcount];
}

function local_aisn_kg_material_cmid(stdClass $material): int {
    $title = (string)($material->title ?? '');

    if (function_exists('local_aisn_course_cm_id_from_material_title')) {
        return local_aisn_course_cm_id_from_material_title($title);
    }

    if (preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', $title, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

function local_aisn_kg_course_materials(int $courseid): array {
    global $DB;

    if (!local_aisn_kg_table_exists('local_aiskillnav_material')) {
        return [];
    }

    $records = $DB->get_records('local_aiskillnav_material', [
        'courseid' => $courseid,
        'materialtype' => 'course_resource',
    ], 'timemodified DESC, id DESC');

    $modinfo = get_fast_modinfo($courseid);
    $materials = [];

    foreach ($records as $record) {
        $cmid = local_aisn_kg_material_cmid($record);

        if ($cmid <= 0) {
            continue;
        }

        if (function_exists('local_aisn_course_material_is_excluded') &&
            local_aisn_course_material_is_excluded($courseid, $cmid)) {
            continue;
        }

        // KG uses only teacher AI/OCR allowed materials, never local-only materials.
        if (!local_aisn_kg_material_allowed_for_graph($record)) {
            continue;
        }

        if (empty($modinfo->cms[$cmid]) || empty($modinfo->cms[$cmid]->visible)) {
            continue;
        }

        if (trim((string)($record->content ?? '')) === '') {
            continue;
        }

        $materials[(int)$record->id] = $record;
    }

    return $materials;
}

function local_aisn_kg_cleanup_stale_course(int $courseid): void {
    $materials = local_aisn_kg_course_materials($courseid);
    $validids = array_fill_keys(array_map('intval', array_keys($materials)), true);

    global $DB;

    if (!local_aisn_kg_table_exists('local_aiskillnav_material')) {
        return;
    }

    $all = $DB->get_records('local_aiskillnav_material', [
        'courseid' => $courseid,
        'materialtype' => 'course_resource',
    ], 'id ASC');

    foreach ($all as $material) {
        if (!isset($validids[(int)$material->id])) {
            local_aisn_kg_delete_material((int)$material->id);
        }
    }
}

function local_aisn_kg_rebuild_course(int $courseid): array {
    global $DB;

    local_aisn_kg_ensure_schema();

    $DB->delete_records('local_aisn_kg_relation', ['courseid' => $courseid]);
    $conceptids = $DB->get_fieldset_select('local_aisn_kg_concept', 'id', 'courseid = :courseid', ['courseid' => $courseid]);

    if (!empty($conceptids)) {
        list($insql, $params) = $DB->get_in_or_equal($conceptids, SQL_PARAMS_NAMED, 'kgc');
        $DB->delete_records_select('local_aisn_kg_source', "conceptid {$insql}", $params);
        $DB->delete_records_select('local_aisn_kg_concept', "id {$insql}", $params);
    }

    $materials = local_aisn_kg_course_materials($courseid);
    $summary = ['materials' => count($materials), 'concepts' => 0, 'relations' => 0];

    foreach ($materials as $material) {
        $result = local_aisn_kg_rebuild_material((int)$material->id);
        $summary['concepts'] += (int)$result['concepts'];
        $summary['relations'] += (int)$result['relations'];
    }

    return $summary;
}

function local_aisn_kg_sync_course_after_material_sync(int $courseid, array $changedids = []): void {
    try {
        local_aisn_kg_ensure_schema();
        local_aisn_kg_cleanup_stale_course($courseid);

        foreach (array_unique(array_map('intval', $changedids)) as $materialid) {
            if ($materialid > 0) {
                local_aisn_kg_rebuild_material($materialid);
            }
        }

        local_aisn_kg_delete_orphan_concepts($courseid);
    } catch (Throwable $e) {
        debugging('AI Skill Navigator knowledge graph sync skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

function local_aisn_kg_stats(int $courseid): array {
    global $DB;

    local_aisn_kg_ensure_schema();

    return [
        'concepts' => $DB->count_records('local_aisn_kg_concept', ['courseid' => $courseid]),
        'relations' => $DB->count_records('local_aisn_kg_relation', ['courseid' => $courseid]),
        'sources' => (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_aisn_kg_source} s
               JOIN {local_aisn_kg_concept} c ON c.id = s.conceptid
              WHERE c.courseid = :courseid",
            ['courseid' => $courseid]
        ),
    ];
}

function local_aisn_kg_graph_data(int $courseid, int $limitnodes = 80, int $limitedges = 160): array {
    global $DB;

    local_aisn_kg_ensure_schema();

    $concepts = $DB->get_records_sql(
        "SELECT c.*,
                COUNT(DISTINCT s.id) AS sourcecount,
                COUNT(DISTINCT r.id) AS relationcount
           FROM {local_aisn_kg_concept} c
      LEFT JOIN {local_aisn_kg_source} s ON s.conceptid = c.id
      LEFT JOIN {local_aisn_kg_relation} r ON r.sourceconceptid = c.id OR r.targetconceptid = c.id
          WHERE c.courseid = :courseid
       GROUP BY c.id, c.courseid, c.name, c.normalizedname, c.description, c.confidence, c.timecreated, c.timemodified
       ORDER BY sourcecount DESC, relationcount DESC, c.confidence DESC, c.name ASC",
        ['courseid' => $courseid],
        0,
        $limitnodes
    );

    $ids = array_map('intval', array_keys($concepts));
    $nodes = [];
    $edges = [];

    foreach ($concepts as $concept) {
        $nodes[] = [
            'id' => (int)$concept->id,
            'label' => format_string($concept->name),
            'confidence' => (int)$concept->confidence,
            'sources' => (int)$concept->sourcecount,
            'relations' => (int)$concept->relationcount,
            'description' => local_aisn_kg_substr((string)$concept->description, 0, 300),
        ];
    }

    if (!empty($ids)) {
        list($sourceinsql, $sourceparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'kgsrc');
        list($targetinsql, $targetparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'kgtgt');
        $params = array_merge(['courseid' => $courseid], $sourceparams, $targetparams);

        $relations = $DB->get_records_sql(
            "SELECT r.*
               FROM {local_aisn_kg_relation} r
              WHERE r.courseid = :courseid
                AND r.sourceconceptid {$sourceinsql}
                AND r.targetconceptid {$targetinsql}
           ORDER BY r.confidence DESC, r.id DESC",
            $params,
            0,
            $limitedges
        );

        foreach ($relations as $relation) {
            $edges[] = [
                'id' => (int)$relation->id,
                'from' => (int)$relation->sourceconceptid,
                'to' => (int)$relation->targetconceptid,
                'type' => (string)$relation->relationtype,
                'confidence' => (int)$relation->confidence,
                'evidence' => local_aisn_kg_substr((string)$relation->evidence, 0, 300),
            ];
        }
    }

    return ['nodes' => $nodes, 'edges' => $edges];
}

function local_aisn_kg_prompt_context(int $courseid, string $focus = '', int $limit = 28): string {
    global $DB;

    local_aisn_kg_ensure_schema();

    $focusnorm = local_aisn_kg_normalize($focus);
    $params = ['courseid' => $courseid];
    $where = "c.courseid = :courseid";

    if ($focusnorm !== '') {
        $params['focus1'] = '%' . $focusnorm . '%';
        $params['focus2'] = '%' . $focusnorm . '%';
        $where .= " AND (c.normalizedname LIKE :focus1 OR LOWER(c.description) LIKE :focus2)";
    }

    $concepts = $DB->get_records_sql(
        "SELECT c.*,
                COUNT(DISTINCT s.id) AS sourcecount,
                COUNT(DISTINCT r.id) AS relationcount
           FROM {local_aisn_kg_concept} c
      LEFT JOIN {local_aisn_kg_source} s ON s.conceptid = c.id
      LEFT JOIN {local_aisn_kg_relation} r ON r.sourceconceptid = c.id OR r.targetconceptid = c.id
          WHERE {$where}
       GROUP BY c.id, c.courseid, c.name, c.normalizedname, c.description, c.confidence, c.timecreated, c.timemodified
       ORDER BY sourcecount DESC, relationcount DESC, c.confidence DESC, c.name ASC",
        $params,
        0,
        $limit
    );

    if (empty($concepts) && $focusnorm !== '') {
        return local_aisn_kg_prompt_context($courseid, '', $limit);
    }

    if (empty($concepts)) {
        return '';
    }

    $ids = array_map('intval', array_keys($concepts));
    $lines = [];
    $lines[] = "COURSE KNOWLEDGE GRAPH";
    $lines[] = "Use these teacher-approved concepts to choose abilities, prerequisites and distractors. Do not invent facts outside the course materials.";

    foreach ($concepts as $concept) {
        $line = "- Concept: " . format_string($concept->name) .
            " | confidence: " . (int)$concept->confidence . "%" .
            " | sources: " . (int)$concept->sourcecount;

        if (trim((string)$concept->description) !== '') {
            $line .= " | evidence: " . local_aisn_kg_substr(local_aisn_kg_clean_text((string)$concept->description), 0, 180);
        }

        $lines[] = $line;
    }

    if (!empty($ids)) {
        list($sourceinsql, $sourceparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'kgpsrc');
        list($targetinsql, $targetparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'kgptgt');
        $relparams = array_merge(['courseid' => $courseid], $sourceparams, $targetparams);

        $relations = $DB->get_records_sql(
            "SELECT r.relationtype, r.confidence, sc.name AS sourcename, tc.name AS targetname
               FROM {local_aisn_kg_relation} r
               JOIN {local_aisn_kg_concept} sc ON sc.id = r.sourceconceptid
               JOIN {local_aisn_kg_concept} tc ON tc.id = r.targetconceptid
              WHERE r.courseid = :courseid
                AND r.sourceconceptid {$sourceinsql}
                AND r.targetconceptid {$targetinsql}
           ORDER BY r.confidence DESC, r.id DESC",
            $relparams,
            0,
            24
        );

        if (!empty($relations)) {
            $lines[] = "Relations:";
            foreach ($relations as $relation) {
                $lines[] = "- " . format_string($relation->sourcename) .
                    " --" . s($relation->relationtype) . "--> " .
                    format_string($relation->targetname) .
                    " (" . (int)$relation->confidence . "%)";
            }
        }
    }

    return implode("\n", $lines);
}
