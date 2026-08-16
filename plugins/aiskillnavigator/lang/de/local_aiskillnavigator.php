<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Skill Navigator';

$string['studentdashboard'] = 'Studenten-Dashboard';
$string['teacherdashboard'] = 'Lehrer-Dashboard';
$string['skills'] = 'Fähigkeiten';
$string['recommendations'] = 'Empfehlungen';
$string['main_gap'] = 'Hauptkompetenzlücke';
$string['ai_recommendation'] = 'KI-Empfehlungsprototyp';

$string['aitutor'] = 'KI-Tutor';
$string['quizgenerator'] = 'KI-Quiz-Generator';
$string['mindmapgenerator'] = 'KI-Gehirnstorm-Generator';

$string['tutor_question'] = 'Eine Frage stellen';
$string['quiz_topic'] = 'Quiz-Thema';
$string['mindmap_topic'] = 'Gehirnstorm-Thema';

$string['settings'] = 'AI Skill Navigator Einstellungen';
$string['provider'] = 'KI-Anbieter';
$string['provider_desc'] = 'Wählen Sie den KI-Anbieter für das Plugin.';
$string['apikey'] = 'KI-API-Schlüssel';
$string['apikey_desc'] = 'API-Schlüssel für den externen KI-Anbieter.';
$string['embeddingmodel'] = 'Einbettungsmodell';
$string['embeddingmodel_desc'] = 'Modell für die Generierung von RAG-Einbettungen. Für Ollama: nomic-embed-text. Für OpenAI: text-embedding-3-small.';
$string['local/aiskillnavigator:viewstudent'] = 'Studenten-KI-Tools verwenden';
$string['local/aiskillnavigator:viewteacher'] = 'Lehrer-KI-Tools verwenden';
$string['local/aiskillnavigator:managematerials'] = 'Lehrer-KI-Materialien verwalten';
$string['local/aiskillnavigator:manageassessments'] = 'KI-Bewertungen verwalten';
$string['privacy:metadata:configured_ai_provider'] = 'Optionaler externer KI-Anbieter, konfiguriert vom Site-Administrator.';
$string['privacy:metadata:local_aiskillnav_material'] = 'Kursmaterialien für KI-gestütztes Lernen gespeichert.';
$string['privacy:metadata:local_aiskillnav_attempt'] = 'Studenten-KI-Quiz-Versuche.';
$string['privacy:metadata:local_aiskillnav_chunk'] = 'Aus Kursmaterialien generierte Suchabschnitte.';
$string['privacy:metadata:local_aiskillnav_assessment'] = 'Von Lehrern generierte Anfangs- und Abschlussbewertungen.';
$string['privacy:metadata:local_aiskillnav_ass_att'] = 'Studentenversuche bei lehrergenerierten Bewertungen.';
$string['privacy:metadata:local_aiskillnav_sim'] = 'Gespeicherte Simulationsvorschläge und Aktivitäten.';
$string['privacy:metadata:local_aiskillnav_tutor_sig'] = 'Tutor-Fragen und Interaktionssignale.';
$string['privacy:metadata:userid'] = 'Die Benutzerkennung.';
$string['privacy:metadata:courseid'] = 'Die Kurskennung.';
$string['privacy:metadata:content'] = 'Vom Benutzer bereitgestellter oder extrahierter Inhalt.';
$string['privacy:metadata:timecreated'] = 'Der Zeitpunkt der Datensatzerstellung.';
$string['privacy:metadata:timemodified'] = 'Der Zeitpunkt der letzten Datensatzänderung.';
