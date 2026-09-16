<?php

define('MOODLE_INTERNAL', true);
$testconfig = [];
function get_config($component, $name) { return $GLOBALS['testconfig'][$name] ?? false; }
function set_config($name, $value, $component) { $GLOBALS['testconfig'][$name] = $value; }
require_once(__DIR__ . '/../plugins/aiskillnavigator/includes/material_ai_policy.php');
require_once(__DIR__ . '/../plugins/aiskillnavigator/includes/course_resource_sync.php');
function check($expected, $actual, $message) {
    if ($expected !== $actual) { throw new RuntimeException($message . ': ' . var_export($actual, true)); }
}
$material = (object)['id' => 4, 'courseid' => 7, 'sourcecmid' => 11, 'materialtype' => 'course_resource',
    'title' => 'Renamed slides', 'externalaiallowed' => 1, 'aipolicy' => 'external_allowed'];
class policy_test_db {
    public $material;
    public function get_record($table, $where) {
        return $where['id'] == $this->material->id && $where['courseid'] == $this->material->courseid ? clone $this->material : false;
    }
    public function update_record($table, $record) { $this->material = clone $record; }
}
$DB = new policy_test_db();
$DB->material = clone $material;
foreach (['prototype', 'ollama', 'local'] as $provider) {
    $testconfig = ['provider' => $provider];
    check(true, local_aiskillnavigator_material_can_be_sent_to_current_ai($material), 'Local default can use course material');
}
$testconfig = ['provider' => 'ollama', 'endpoint' => 'https://remote.example.test'];
check(false, local_aiskillnavigator_material_can_be_sent_to_current_ai($material), 'Remote Ollama requires global approval');
$testconfig['externalaiapproved'] = 1;
check(true, local_aiskillnavigator_material_can_be_sent_to_current_ai($material), 'Approved remote material is usable');
$denied = clone $material;
$denied->externalaiallowed = 0;
check(false, local_aiskillnavigator_material_can_be_sent_to_current_ai($denied), 'Remote Ollama still requires per-material approval');
$testconfig = ['provider' => 'openai_compatible', 'endpoint' => 'http://[::1]:1234'];
check(true, local_aiskillnavigator_current_ai_is_local(), 'IPv6 loopback is local');
$testconfig = [];
check([1, 'external_allowed'], local_aisn_crs_policy_for_cmid(11, $material), 'Legacy approval survives sync');
check([0, 'local_only'], local_aisn_crs_policy_for_cmid(12), 'New materials default to local only');
check(false, local_aiskillnavigator_set_material_ai_policy(4, 999, true), 'Cross-course policy updates are rejected');
check(true, local_aiskillnavigator_set_material_ai_policy(4, 7, true), 'Policy updates succeed');
check('1', $testconfig['cm_external_ai_11'], 'Renamed source persists module policy by sourcecmid');
local_aiskillnavigator_set_material_ai_policy(4, 7, false);
check('0', $testconfig['cm_external_ai_11'], 'Revocation is persisted for sync');
check([0, 'local_only'], local_aisn_crs_policy_for_cmid(11, $material), 'Explicit revocation wins over an old approved row');
echo "material_policy_test: OK\n";
