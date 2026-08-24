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

$string['ai_recommendation'] = 'Prototype de recommandation par l’IA';
$string['aitutor'] = 'Tuteur IA';
$string['apikey'] = 'Clé API de l’IA';
$string['apikey_desc'] = 'Clé API du fournisseur d’IA externe.';
$string['embeddingmodel'] = 'Modèle d’embedding';
$string['embeddingmodel_desc'] = 'Modèle utilisé pour générer les embeddings RAG. Pour Ollama : nomic-embed-text. Pour OpenAI : text-embedding-3-small.';
$string['local/aiskillnavigator:manageassessments'] = 'Gérer les évaluations avec l’IA';
$string['local/aiskillnavigator:managematerials'] = 'Gérer les ressources pédagogiques avec l’IA';
$string['local/aiskillnavigator:viewstudent'] = 'Utiliser les outils d’IA pour les étudiants';
$string['local/aiskillnavigator:viewteacher'] = 'Utiliser les outils d’IA pour les enseignants';
$string['main_gap'] = 'Principale lacune de compétences';
$string['mindmap_topic'] = 'Sujet de la carte mentale';
$string['mindmapgenerator'] = 'Générateur de cartes mentales IA';
$string['pluginname'] = 'AI Skill Navigator';
$string['privacy:metadata:configured_ai_provider'] = 'Fournisseur d’IA externe facultatif configuré par l’administrateur du site.';
$string['privacy:metadata:content'] = 'Contenu fourni ou extrait par l’utilisateur.';
$string['privacy:metadata:courseid'] = 'Identifiant du cours.';
$string['privacy:metadata:local_aiskillnav_ass_att'] = 'Tentatives des étudiants aux évaluations générées par l’enseignant.';
$string['privacy:metadata:local_aiskillnav_assessment'] = 'Évaluations initiales et finales générées par l’enseignant.';
$string['privacy:metadata:local_aiskillnav_attempt'] = 'Tentatives de quiz IA des étudiants.';
$string['privacy:metadata:local_aiskillnav_chunk'] = 'Segments de recherche générés à partir des ressources pédagogiques.';
$string['privacy:metadata:local_aiskillnav_material'] = 'Ressources pédagogiques stockées pour l’apprentissage assisté par l’IA.';
$string['privacy:metadata:local_aiskillnav_sim'] = 'Suggestions et activités du simulateur enregistrées.';
$string['privacy:metadata:local_aiskillnav_tutor_sig'] = 'Questions du tuteur et signaux d’interaction.';
$string['privacy:metadata:timecreated'] = 'Date et heure de création de l’enregistrement.';
$string['privacy:metadata:timemodified'] = 'Date et heure de dernière modification de l’enregistrement.';
$string['privacy:metadata:userid'] = 'Identifiant de l’utilisateur.';
$string['provider'] = 'Fournisseur d’IA';
$string['provider_desc'] = 'Sélectionnez le fournisseur d’IA utilisé par le plugin.';
$string['quiz_topic'] = 'Sujet du quiz';
$string['quizgenerator'] = 'Générateur de quiz par l’IA';
$string['recommendations'] = 'Recommandations';
$string['settings'] = 'Paramètres d’AI Skill Navigator';
$string['skills'] = 'Compétences';
$string['studentdashboard'] = 'Tableau de bord étudiant';
$string['teacherdashboard'] = 'Tableau de bord enseignant';
$string['tutor_question'] = 'Poser une question';
