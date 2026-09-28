<?php
/** Execute repository SQL on SQLite, including course, permission and group isolation. */
define('MOODLE_INTERNAL', true);
define('SQL_PARAMS_NAMED', 2);
define('SEPARATEGROUPS', 1);
$USER = (object)['id' => 50];
$allowed = [101 => true, 102 => true, 103 => false, 104 => true];
$allgroups = false;
$groups = [7 => (object)['id' => 7]];
class context_course { public static function instance($id) { return (object)['id' => $id]; } }
function get_course($id) { return (object)['id' => $id, 'groupmode' => 0]; }
function groups_get_course_groupmode($course) { return $course->groupmode; }
class context_module { public static function instance($id) { return (object)['id' => $id]; } }
function has_capability($capability, $context) {
    if ($capability === 'local/aiskillnavigator:viewteacher') { return true; }
    return $capability === 'mod/quiz:viewreports' ? ($GLOBALS['allowed'][$context->id] ?? false) : $GLOBALS['allgroups'];
}
function groups_get_activity_groupmode($cm) { return $cm->groupmode; }
function groups_get_all_groups($courseid, $userid, $groupingid, $fields) { return $GLOBALS['groups']; }
function get_fast_modinfo($courseid) {
    return new class {
        public function get_instances_of($name) {
            return array_map(static function ($i) {
                return (object)['id' => 100 + $i, 'instance' => $i, 'uservisible' => true,
                    'groupmode' => $i === 4 ? SEPARATEGROUPS : 0, 'groupingid' => 0];
            }, [1, 2, 3, 4]);
        }
    };
}
class sqlite_moodle_db {
    public PDO $pdo;
    public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    public function get_records_sql($sql, $params) {
        $sql = preg_replace('/\{([a-z_]+)\}/', '$1', $sql);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_OBJ) as $row) { $rows[$row->id] = $row; }
        return $rows;
    }
    public function get_in_or_equal($ids, $type, $prefix) {
        $params = []; $names = [];
        foreach (array_values($ids) as $i => $id) { $key = $prefix . $i; $names[] = ':' . $key; $params[$key] = $id; }
        return ['IN (' . implode(', ', $names) . ')', $params];
    }
}
$DB = new sqlite_moodle_db();
$DB->pdo->exec(<<<SQL
CREATE TABLE user (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, deleted INTEGER);
CREATE TABLE local_aiskillnavigator_attempt (id INTEGER PRIMARY KEY, courseid INTEGER, userid INTEGER, topic TEXT, difficulty TEXT,
    score REAL, maxscore REAL, percentage INTEGER, timecreated INTEGER);
CREATE TABLE quiz (id INTEGER PRIMARY KEY, course INTEGER, name TEXT, sumgrades REAL);
CREATE TABLE quiz_attempts (id INTEGER PRIMARY KEY, quiz INTEGER, userid INTEGER, sumgrades REAL, timefinish INTEGER, state TEXT, preview INTEGER);
CREATE TABLE groups_members (groupid INTEGER, userid INTEGER);
INSERT INTO user VALUES (1, 'Student', 'One', 'one@example.test', 0), (2, 'Student', 'Two', 'two@example.test', 0), (3, 'Deleted', 'User', 'deleted@example.test', 1);
INSERT INTO groups_members VALUES (7, 1), (8, 2);
INSERT INTO local_aiskillnavigator_attempt VALUES (1, 42, 1, 'AI topic', 'easy', 2, 4, 50, 100), (2, 99, 1, 'Other course', 'easy', 1, 1, 100, 999);
INSERT INTO quiz VALUES (1, 42, 'Databases', 10), (2, 42, 'Zero grade', 0), (3, 42, 'Restricted', 10), (4, 42, 'Grouped quiz', 10), (5, 99, 'Other course', 10);
INSERT INTO quiz_attempts VALUES
 (1, 1, 1, 7.5, 300, 'finished', 0),
 (2, 1, 1, 9, 400, 'finished', 1),
 (3, 1, 1, 8, 500, 'inprogress', 0),
 (4, 1, 1, NULL, 600, 'finished', 0),
 (5, 2, 1, 0, 700, 'finished', 0),
 (6, 3, 1, 10, 800, 'finished', 0),
 (7, 4, 1, 9, 250, 'finished', 0),
 (8, 4, 2, 8, 260, 'finished', 0),
 (9, 5, 1, 10, 900, 'finished', 0),
 (10, 1, 3, 10, 950, 'finished', 0);
SQL);
require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/report_access.php');
require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/quiz_attempt_repository.php');
$repository = new local_aiskillnavigator\service\quiz_attempt_repository();
$attempts = $repository->for_course(42);
if (count($attempts) !== 3 || $attempts[0]->percentage !== 75 || $attempts[0]->source !== 'Moodle quiz'
        || (int)$attempts[1]->id !== 7 || $attempts[2]->source !== 'AI Skill Navigator') {
    throw new RuntimeException('Wrong course, grade normalisation, order, ID merge or group filtering: ' . json_encode($attempts));
}
$groups = [];
if (count($repository->for_course(42)) !== 2) { throw new RuntimeException('A teacher without a group must not see grouped quiz attempts'); }
$allgroups = true;
if (count($repository->for_course(42)) !== 4) { throw new RuntimeException('Access-all-groups capability should allow both groups'); }
$allowed = [];
$attempts = $repository->for_course(42);
if (count($attempts) !== 1 || $attempts[0]->source !== 'AI Skill Navigator') {
    throw new RuntimeException('Native reports must require mod/quiz:viewreports');
}
echo "quiz_attempt_repository_test: OK\n";
