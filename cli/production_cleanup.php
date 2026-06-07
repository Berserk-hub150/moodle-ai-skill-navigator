<?php
define('CLI_SCRIPT', true);

$configcandidates = [
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    '/opt/bitnami/moodle/config.php',
    '/bitnami/moodle/config.php',
];

$configloaded = false;

foreach ($configcandidates as $candidate) {
    if ($candidate !== '' && file_exists($candidate)) {
        require_once($candidate);
        $configloaded = true;
        break;
    }
}

if (!$configloaded) {
    fwrite(STDERR, "Cannot find Moodle config.php from production_cleanup.php\n");
    exit(1);
}

require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');

$kghelper = $CFG->dirroot . '/local/aiskillnavigator/includes/knowledge_graph_helper.php';

if (file_exists($kghelper)) {
    require_once($kghelper);
}

list($options, $unrecognized) = cli_get_params(
    [
        'courseid' => 0,
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($options['help'])) {
    mtrace("Usage: php local/aiskillnavigator/cli/production_cleanup.php --courseid=2");
    exit(0);
}

function aisn_prod_identity(string $name): string {
    $name = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = core_text::strtolower(trim((string)$name));
    $name = preg_replace('/^\s*materiale\s*-\s*/iu', '', (string)$name);
    $name = preg_replace('/^\s*file\s*[:\-]?\s*/iu', '', (string)$name);
    $name = preg_replace('/\s+/u', ' ', (string)$name);
    $name = trim((string)$name);

    return preg_replace('/[^\p{L}\p{N}\.]+/u', '', (string)$name);
}

function aisn_prod_table_exists(string $table): bool {
    global $DB;

    try {
        return $DB->get_manager()->table_exists(new xmldb_table($table));
    } catch (Throwable $e) {
        return false;
    }
}

function aisn_prod_cleanup_course_modules(int $courseid): int {
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
        $identity = aisn_prod_identity((string)$record->resourcename);

        if ($identity === '') {
            continue;
        }

        $key = (int)$record->sectionid . '|' . $identity;

        if (!isset($seen[$key])) {
            $seen[$key] = (int)$record->cmid;
            continue;
        }

        mtrace("Deleting duplicate resource cmid=" . (int)$record->cmid . " name=" . (string)$record->resourcename);

        try {
            course_delete_module((int)$record->cmid);
            $deleted++;
        } catch (Throwable $e) {
            mtrace("ERROR deleting cmid " . (int)$record->cmid . ": " . $e->getMessage());
        }
    }

    if ($deleted > 0) {
        rebuild_course_cache($courseid, true);
    }

    return $deleted;
}

function aisn_prod_cleanup_material_table(int $courseid): int {
    global $DB;

    if (!aisn_prod_table_exists('local_aiskillnav_material')) {
        return 0;
    }

    $records = $DB->get_records(
        'local_aiskillnav_material',
        ['courseid' => $courseid],
        'id ASC'
    );

    $seen = [];
    $deleted = 0;

    foreach ($records as $record) {
        $sourcecmid = property_exists($record, 'sourcecmid') ? (int)$record->sourcecmid : 0;
        $title = (string)($record->title ?? '');
        $type = (string)($record->materialtype ?? '');
        $contenthash = property_exists($record, 'contenthash') ? (string)$record->contenthash : '';

        if ($sourcecmid > 0) {
            $key = 'cmid:' . $sourcecmid;
        } else {
            $key = 'fallback:' . $type . '|' . aisn_prod_identity($title) . '|' . $contenthash;
        }

        if ($key === 'fallback:||') {
            continue;
        }

        if (!isset($seen[$key])) {
            $seen[$key] = (int)$record->id;
            continue;
        }

        mtrace("Deleting duplicate material record id=" . (int)$record->id . " title=" . $title);

        try {
            if (function_exists('local_aisn_kg_delete_material')) {
                local_aisn_kg_delete_material((int)$record->id);
            }

            $DB->delete_records('local_aiskillnav_material', ['id' => (int)$record->id]);
            $deleted++;
        } catch (Throwable $e) {
            mtrace("ERROR deleting material id " . (int)$record->id . ": " . $e->getMessage());
        }
    }

    return $deleted;
}

$courseid = (int)$options['courseid'];

if ($courseid <= 0) {
    $courses = $DB->get_records_select('course', 'id > 1', [], 'id ASC', 'id');
} else {
    $courses = [$courseid => (object)['id' => $courseid]];
}

$totalmodules = 0;
$totalmaterials = 0;

foreach ($courses as $course) {
    $cid = (int)$course->id;

    mtrace("Cleaning course id=" . $cid);

    $totalmodules += aisn_prod_cleanup_course_modules($cid);
    $totalmaterials += aisn_prod_cleanup_material_table($cid);

    rebuild_course_cache($cid, true);
}

mtrace("DONE. Duplicate Moodle resources deleted: " . $totalmodules);
mtrace("DONE. Duplicate AI material records deleted: " . $totalmaterials);