<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../classes/service/web_search_service.php');

global $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);
$title = required_param('title', PARAM_TEXT);
$topic = optional_param('topic', '', PARAM_TEXT);

$course = get_course($courseid);
require_login($course);
require_sesskey();

$context = context_course::instance($courseid);

local_aisn_require_student_area($context);
if (
    !has_capability('local/aiskillnavigator:viewstudent', $context) &&
    !has_capability('local/aiskillnavigator:viewteacher', $context) &&
    !has_capability('moodle/course:view', $context) &&
    !is_siteadmin()
) {
    throw new required_capability_exception($context, 'moodle/course:view', 'nopermissions', '');
}

header('Content-Type: application/json; charset=utf-8');

try {
    $service = new \local_aiskillnavigator\service\web_search_service();

    if (!$service->is_enabled()) {
        echo json_encode([
            'ok' => false,
            'message' => 'Search API non attiva. Configura Tavily/Search API nelle impostazioni del plugin.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $query = trim($topic . ' ' . $title . ' educational example tutorial simulator official');
    $results = $service->search($query, 3);

    if (empty($results)) {
        echo json_encode([
            'ok' => false,
            'message' => 'Nessun esempio online trovato per "' . $title . '".',
            'provider' => $service->provider_name(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $r = $results[0];

    echo json_encode([
        'ok' => true,
        'provider' => $service->provider_name(),
        'concept' => $title,
        'title' => (string)($r['title'] ?? 'Risorsa online'),
        'url' => (string)($r['url'] ?? ''),
        'snippet' => (string)($r['content'] ?? $r['snippet'] ?? ''),
        // phpcs:ignore moodle.Files.LineLength
        'activity' => 'Apri la risorsa, osserva un esempio pratico del concetto "' . $title . '" e scrivi 3 righe su come si collega alla mappa mentale.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'message' => 'Errore ricerca web: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
