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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

$string['ai_recommendation'] = 'KI-Empfehlungsprototyp';
$string['aitutor'] = 'KI-Tutor';
$string['apikey'] = 'KI-API-Schlüssel';
$string['apikey_desc'] = 'API-Schlüssel für den externen KI-Anbieter.';
$string['embeddingmodel'] = 'Einbettungsmodell';
$string['embeddingmodel_desc'] = 'Modell für die Generierung von RAG-Einbettungen. Für Ollama: nomic-embed-text. Für OpenAI: text-embedding-3-small.';
$string['local/aiskillnavigator:manageassessments'] = 'KI-Bewertungen verwalten';
$string['local/aiskillnavigator:managematerials'] = 'Lehrer-KI-Materialien verwalten';
$string['local/aiskillnavigator:viewstudent'] = 'Studenten-KI-Tools verwenden';
$string['local/aiskillnavigator:viewteacher'] = 'Lehrer-KI-Tools verwenden';
$string['main_gap'] = 'Hauptkompetenzlücke';
$string['mindmap_topic'] = 'Gehirnstorm-Thema';
$string['mindmapgenerator'] = 'KI-Gehirnstorm-Generator';
$string['pluginname'] = 'AI Skill Navigator';
$string['privacy:metadata:configured_ai_provider'] = 'Optionaler externer KI-Anbieter, konfiguriert vom Site-Administrator.';
$string['privacy:metadata:content'] = 'Vom Benutzer bereitgestellter oder extrahierter Inhalt.';
$string['privacy:metadata:courseid'] = 'Die Kurskennung.';
$string['privacy:metadata:local_aiskillnav_ass_att'] = 'Studentenversuche bei lehrergenerierten Bewertungen.';
$string['privacy:metadata:local_aiskillnav_assessment'] = 'Von Lehrern generierte Anfangs- und Abschlussbewertungen.';
$string['privacy:metadata:local_aiskillnav_attempt'] = 'Studenten-KI-Quiz-Versuche.';
$string['privacy:metadata:local_aiskillnav_chunk'] = 'Aus Kursmaterialien generierte Suchabschnitte.';
$string['privacy:metadata:local_aiskillnav_material'] = 'Kursmaterialien für KI-gestütztes Lernen gespeichert.';
$string['privacy:metadata:local_aiskillnav_sim'] = 'Gespeicherte Simulationsvorschläge und Aktivitäten.';
$string['privacy:metadata:local_aiskillnav_tutor_sig'] = 'Tutor-Fragen und Interaktionssignale.';
$string['privacy:metadata:timecreated'] = 'Der Zeitpunkt der Datensatzerstellung.';
$string['privacy:metadata:timemodified'] = 'Der Zeitpunkt der letzten Datensatzänderung.';
$string['privacy:metadata:userid'] = 'Die Benutzerkennung.';
$string['provider'] = 'KI-Anbieter';
$string['provider_desc'] = 'Wählen Sie den KI-Anbieter für das Plugin.';
$string['quiz_topic'] = 'Quiz-Thema';
$string['quizgenerator'] = 'KI-Quiz-Generator';
$string['recommendations'] = 'Empfehlungen';
$string['settings'] = 'AI Skill Navigator Einstellungen';
$string['skills'] = 'Fähigkeiten';
$string['studentdashboard'] = 'Studenten-Dashboard';
$string['teacherdashboard'] = 'Lehrer-Dashboard';
$string['tutor_question'] = 'Eine Frage stellen';
