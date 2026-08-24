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

if (empty($hassiteconfig)) {
    return;
}

$settings = new admin_settingpage(
    'local_aiskillnavigator',
    get_string('pluginname', 'local_aiskillnavigator')
);

$ADMIN->add('localplugins', $settings);

$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/mainheading',
    get_string('settings_mainheading', 'local_aiskillnavigator'),
    get_string('settings_mainheading_desc', 'local_aiskillnavigator')
));

$settings->add(new admin_setting_configselect(
    'local_aiskillnavigator/provider',
    get_string('settings_provider', 'local_aiskillnavigator'),
    get_string('settings_provider_desc', 'local_aiskillnavigator'),
    'prototype',
    [
        'prototype' => get_string('settings_provider_prototype', 'local_aiskillnavigator'),
        'gemini' => get_string('settings_provider_gemini', 'local_aiskillnavigator'),
        'anthropic' => get_string('settings_provider_anthropic', 'local_aiskillnavigator'),
        'openrouter' => get_string('settings_provider_openrouter', 'local_aiskillnavigator'),
        'ollama' => get_string('settings_provider_ollama', 'local_aiskillnavigator'),
        'openai' => get_string('settings_provider_openai', 'local_aiskillnavigator'),
        'openai_compatible' => get_string('settings_provider_openai_compatible', 'local_aiskillnavigator'),
        'deepseek' => get_string('settings_provider_deepseek', 'local_aiskillnavigator'),
        'groq' => get_string('settings_provider_groq', 'local_aiskillnavigator'),
        'mistral' => get_string('settings_provider_mistral', 'local_aiskillnavigator'),
        'custom_http' => get_string('settings_provider_custom_http', 'local_aiskillnavigator'),
    ]
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/endpoint',
    get_string('settings_endpoint', 'local_aiskillnavigator'),
    get_string('settings_endpoint_desc', 'local_aiskillnavigator'),
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/model',
    get_string('settings_model', 'local_aiskillnavigator'),
    get_string('settings_model_desc', 'local_aiskillnavigator'),
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_aiskillnavigator/apikey',
    get_string('settings_apikey', 'local_aiskillnavigator'),
    get_string('settings_apikey_desc', 'local_aiskillnavigator'),
    ''
));

$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/customheading',
    get_string('settings_customheading', 'local_aiskillnavigator'),
    get_string('settings_customheading_desc', 'local_aiskillnavigator')
));

$settings->add(new admin_setting_configtextarea(
    'local_aiskillnavigator/customrequesttemplate',
    get_string('settings_customrequesttemplate', 'local_aiskillnavigator'),
    get_string('settings_customrequesttemplate_desc', 'local_aiskillnavigator'),
    '',
    PARAM_RAW
));

$settings->add(new admin_setting_configtextarea(
    'local_aiskillnavigator/customheadersjson',
    get_string('settings_customheadersjson', 'local_aiskillnavigator'),
    get_string('settings_customheadersjson_desc', 'local_aiskillnavigator'),
    '',
    PARAM_RAW
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/customresponsepath',
    get_string('settings_customresponsepath', 'local_aiskillnavigator'),
    get_string('settings_customresponsepath_desc', 'local_aiskillnavigator'),
    'choices.0.message.content',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/embeddingheading',
    get_string('settings_embeddingheading', 'local_aiskillnavigator'),
    get_string('settings_embeddingheading_desc', 'local_aiskillnavigator')
));

$settings->add(new admin_setting_configselect(
    'local_aiskillnavigator/embeddingprovider',
    get_string('settings_embeddingprovider', 'local_aiskillnavigator'),
    '',
    'same_as_chat',
    [
        'same_as_chat' => get_string('settings_embeddingprovider_same', 'local_aiskillnavigator'),
        'ollama' => get_string('settings_embeddingprovider_ollama', 'local_aiskillnavigator'),
        'openai' => get_string('settings_embeddingprovider_openai', 'local_aiskillnavigator'),
        'custom_http' => get_string('settings_embeddingprovider_custom', 'local_aiskillnavigator'),
    ]
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/embeddingendpoint',
    get_string('settings_embeddingendpoint', 'local_aiskillnavigator'),
    get_string('settings_embeddingendpoint_desc', 'local_aiskillnavigator'),
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/embeddingmodel',
    get_string('settings_embeddingmodel', 'local_aiskillnavigator'),
    get_string('settings_embeddingmodel_desc', 'local_aiskillnavigator'),
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_aiskillnavigator/embeddingapikey',
    get_string('settings_embeddingapikey', 'local_aiskillnavigator'),
    get_string('settings_embeddingapikey_desc', 'local_aiskillnavigator'),
    ''
));

$settings->add(new admin_setting_configtextarea(
    'local_aiskillnavigator/embeddingrequesttemplate',
    get_string('settings_embeddingrequesttemplate', 'local_aiskillnavigator'),
    get_string('settings_embeddingrequesttemplate_desc', 'local_aiskillnavigator'),
    '',
    PARAM_RAW
));

$settings->add(new admin_setting_configtextarea(
    'local_aiskillnavigator/embeddingheadersjson',
    get_string('settings_embeddingheadersjson', 'local_aiskillnavigator'),
    '',
    '',
    PARAM_RAW
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/embeddingresponsepath',
    get_string('settings_embeddingresponsepath', 'local_aiskillnavigator'),
    get_string('settings_embeddingresponsepath_desc', 'local_aiskillnavigator'),
    'data.0.embedding',
    PARAM_RAW_TRIMMED
));



$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/ocrheading',
    get_string('settings_ocrheading', 'local_aiskillnavigator'),
    get_string('settings_ocrheading_desc', 'local_aiskillnavigator')
));

$settings->add(new admin_setting_configcheckbox(
    'local_aiskillnavigator/enablelocalocr',
    get_string('settings_enablelocalocr', 'local_aiskillnavigator'),
    get_string('settings_enablelocalocr_desc', 'local_aiskillnavigator'),
    1
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/ocrlanguages',
    get_string('settings_ocrlanguages', 'local_aiskillnavigator'),
    get_string('settings_ocrlanguages_desc', 'local_aiskillnavigator'),
    'ita+eng',
    PARAM_TEXT
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/ocrmaximages',
    get_string('settings_ocrmaximages', 'local_aiskillnavigator'),
    get_string('settings_ocrmaximages_desc', 'local_aiskillnavigator'),
    '120',
    PARAM_INT
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/ocrmaximagebytes',
    get_string('settings_ocrmaximagebytes', 'local_aiskillnavigator'),
    get_string('settings_ocrmaximagebytes_desc', 'local_aiskillnavigator'),
    '18874368',
    PARAM_INT
));


$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/productionheading',
    get_string('settings_productionheading', 'local_aiskillnavigator'),
    get_string('settings_productionheading_desc', 'local_aiskillnavigator')
));

$settings->add(new admin_setting_configcheckbox(
    'local_aiskillnavigator/externalaiapproved',
    get_string('settings_externalaiapproved', 'local_aiskillnavigator'),
    get_string('settings_externalaiapproved_desc', 'local_aiskillnavigator'),
    0
));

$settings->add(new admin_setting_configcheckbox(
    'local_aiskillnavigator/allowdestructivecoursebuilder',
    get_string('settings_allowdestructivecoursebuilder', 'local_aiskillnavigator'),
    get_string('settings_allowdestructivecoursebuilder_desc', 'local_aiskillnavigator'),
    0
));

$settings->add(new admin_setting_configcheckbox(
    'local_aiskillnavigator/autoblockcourses',
    get_string('settings_autoblockcourses', 'local_aiskillnavigator'),
    get_string('settings_autoblockcourses_desc', 'local_aiskillnavigator'),
    0
));

$settings->add(new admin_setting_configcheckbox(
    'local_aiskillnavigator/autosynccourseresources',
    get_string('settings_autosynccourseresources', 'local_aiskillnavigator'),
    get_string('settings_autosynccourseresources_desc', 'local_aiskillnavigator'),
    0
));

$settings->add(new admin_setting_configcheckbox(
    'local_aiskillnavigator/enablemathjaxcdn',
    get_string('settings_enablemathjaxcdn', 'local_aiskillnavigator'),
    get_string('settings_enablemathjaxcdn_desc', 'local_aiskillnavigator'),
    0
));


$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/maxuploadbytes',
    get_string('settings_maxuploadbytes', 'local_aiskillnavigator'),
    get_string('settings_maxuploadbytes_desc', 'local_aiskillnavigator'),
    '26214400',
    PARAM_INT
));


$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/searchheading',
    get_string('settings_searchheading', 'local_aiskillnavigator'),
    get_string('settings_searchheading_desc', 'local_aiskillnavigator')
));

$settings->add(new admin_setting_configselect(
    'local_aiskillnavigator/searchprovider',
    get_string('settings_searchprovider', 'local_aiskillnavigator'),
    get_string('settings_searchprovider_desc', 'local_aiskillnavigator'),
    'none',
    [
        'none' => get_string('settings_searchprovider_none', 'local_aiskillnavigator'),
        'tavily' => get_string('settings_searchprovider_tavily', 'local_aiskillnavigator'),
        'brave' => get_string('settings_searchprovider_brave', 'local_aiskillnavigator'),
        'serpapi' => get_string('settings_searchprovider_serpapi', 'local_aiskillnavigator'),
    ]
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/searchendpoint',
    get_string('settings_searchendpoint', 'local_aiskillnavigator'),
    get_string('settings_searchendpoint_desc', 'local_aiskillnavigator'),
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_aiskillnavigator/searchapikey',
    get_string('settings_searchapikey', 'local_aiskillnavigator'),
    get_string('settings_searchapikey_desc', 'local_aiskillnavigator'),
    ''
));
