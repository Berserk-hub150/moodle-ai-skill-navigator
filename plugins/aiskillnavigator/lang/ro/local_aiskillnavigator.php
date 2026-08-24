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

$string['ai_recommendation'] = 'Prototip de recomandare AI';
$string['aitutor'] = 'Tutor AI';
$string['apikey'] = 'Cheie API AI';
$string['apikey_desc'] = 'Cheia API pentru furnizorul AI extern.';
$string['embeddingmodel'] = 'Model de încorporare';
$string['embeddingmodel_desc'] = 'Model utilizat pentru generarea încorporărilor RAG. Pentru Ollama: nomic-embed-text. Pentru OpenAI: text-embedding-3-small.';
$string['local/aiskillnavigator:manageassessments'] = 'Gestionați evaluările AI';
$string['local/aiskillnavigator:managematerials'] = 'Gestionați materialele AI pentru profesori';
$string['local/aiskillnavigator:viewstudent'] = 'Utilizați instrumentele AI pentru studenți';
$string['local/aiskillnavigator:viewteacher'] = 'Utilizați instrumentele AI pentru profesori';
$string['main_gap'] = 'Principala lacună de competențe';
$string['mindmap_topic'] = 'Subiectul hărții mentale';
$string['mindmapgenerator'] = 'Generator de hărți mentale AI';
$string['pluginname'] = 'AI Skill Navigator';
$string['privacy:metadata:configured_ai_provider'] = 'Furnizor AI extern configurat de administratorul site-ului.';
$string['privacy:metadata:content'] = 'Conținut furnizat de utilizator sau extras.';
$string['privacy:metadata:courseid'] = 'Identificatorul cursului.';
$string['privacy:metadata:local_aiskillnav_ass_att'] = 'Încercări ale studenților la evaluările generate de profesori.';
$string['privacy:metadata:local_aiskillnav_assessment'] = 'Evaluări inițiale și finale generate de profesori.';
$string['privacy:metadata:local_aiskillnav_attempt'] = 'Încercări de chestionare AI ale studenților.';
$string['privacy:metadata:local_aiskillnav_chunk'] = 'Fragmente de căutare generate din materialele de curs.';
$string['privacy:metadata:local_aiskillnav_material'] = 'Materiale de curs stocate pentru învățarea asistată de AI.';
$string['privacy:metadata:local_aiskillnav_sim'] = 'Sugestii de simulare și activități salvate.';
$string['privacy:metadata:local_aiskillnav_tutor_sig'] = 'Întrebări tutor și semnale de interacțiune.';
$string['privacy:metadata:timecreated'] = 'Momentul creării înregistrării.';
$string['privacy:metadata:timemodified'] = 'Momentul ultimei modificări a înregistrării.';
$string['privacy:metadata:userid'] = 'Identificatorul utilizatorului.';
$string['provider'] = ' Furnizor AI';
$string['provider_desc'] = 'Selectați furnizorul AI utilizat de plugin.';
$string['quiz_topic'] = 'Subiectul chestionarului';
$string['quizgenerator'] = 'Generator de chestionare AI';
$string['recommendations'] = 'Recomandări';
$string['settings'] = 'Setări AI Skill Navigator';
$string['skills'] = 'Competențe';
$string['studentdashboard'] = 'Panoul studentului';
$string['teacherdashboard'] = 'Panoul profesorului';
$string['tutor_question'] = 'Pune o întrebare';
