<?php

define('MOODLE_INTERNAL', true);
define('DEBUG_DEVELOPER', 32767);
class core_text {
    public static function substr($s, $start, $length = null) { return mb_substr($s, $start, $length); }
    public static function strtolower($s) { return mb_strtolower($s); }
}
class xmldb_table { public function __construct($name) {} }
function debugging($message, $level) { throw new RuntimeException($message); }
$fixture = sys_get_temp_dir() . '/aisn-test-' . bin2hex(random_bytes(6));
mkdir($fixture);
file_put_contents($fixture . '/ddllib.php', '<?php');
$CFG = (object)['libdir' => $fixture];
register_shutdown_function(static function () use ($fixture) { unlink($fixture . '/ddllib.php'); rmdir($fixture); });
$DB = new class {
    public $inserted = [];
    public function get_manager() { return $this; }
    public function table_exists($table) { return true; }
    public function insert_record($table, $record) { $this->inserted[] = clone $record; return count($this->inserted); }
};
require_once(__DIR__ . '/../plugins/aiskillnavigator/includes/tutor_signal_helper.php');
local_aiskillnavigator_tutor_signal_store(42, 7, 'Explain SQL joins', 'selected', ['Database slides'], 'A join combines rows.');
local_aiskillnavigator_tutor_signal_store(42, 7, '', 'manual', [], 'Answer');
local_aiskillnavigator_tutor_signal_store(42, 7, 'Question', 'manual', [], '');
if (count($DB->inserted) !== 1 || $DB->inserted[0]->courseid !== 42 || $DB->inserted[0]->userid !== 7
        || json_decode($DB->inserted[0]->materials, true) !== ['Database slides']) {
    throw new RuntimeException('Tutor signal was lost or saved with the wrong course, learner or sources');
}
echo "tutor_signal_test: OK\n";
