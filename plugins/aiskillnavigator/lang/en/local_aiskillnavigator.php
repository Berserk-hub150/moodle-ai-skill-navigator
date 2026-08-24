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

$string['ai_recommendation'] = 'AI recommendation prototype';
$string['aitutor'] = 'AI Tutor';
$string['apikey'] = 'AI API key';
$string['apikey_desc'] = 'API key for the external AI provider.';
$string['embeddingmodel'] = 'Embedding model';
$string['embeddingmodel_desc'] = 'Model used for generating RAG embeddings. For Ollama: nomic-embed-text. For OpenAI: text-embedding-3-small.';
$string['local/aiskillnavigator:manageassessments'] = 'Manage AI assessments';
$string['local/aiskillnavigator:managematerials'] = 'Manage teacher AI materials';
$string['local/aiskillnavigator:viewstudent'] = 'Use student AI tools';
$string['local/aiskillnavigator:viewteacher'] = 'Use teacher AI tools';
$string['main_gap'] = 'Main skill gap';
$string['mindmap_topic'] = 'Mind map topic';
$string['mindmapgenerator'] = 'AI Mind Map Generator';
$string['page_adaptive_review_heading'] = 'Adaptive review';
$string['page_adaptive_review_title'] = 'Adaptive review';
$string['page_assessment_heading'] = 'AI assessments';
$string['page_assessment_title'] = 'AI assessments';
$string['page_course_builder_heading'] = 'AI Course Builder';
$string['page_course_builder_title'] = 'AI Course Builder';
$string['page_gap_analysis_heading'] = 'AI learning-gap analysis';
$string['page_gap_analysis_title'] = 'AI learning-gap analysis';
$string['page_index_heading_1'] = 'AI Skill Navigator';
$string['page_index_heading_2'] = 'AI Skill Navigator';
$string['page_index_title_1'] = 'AI Skill Navigator';
$string['page_index_title_2'] = 'AI Skill Navigator';
$string['page_mindmapgenerator_heading'] = 'AI Mind Map Generator';
$string['page_mindmapgenerator_title'] = 'AI Mind Map Generator';
$string['page_simulator_finder_heading'] = 'AI Simulator Finder';
$string['page_simulator_finder_title'] = 'AI Simulator Finder';
$string['page_teacher_assessments_heading'] = 'Initial/final tests';
$string['page_teacher_assessments_title'] = 'Initial/final tests';
$string['page_teacher_heading'] = 'Teacher dashboard';
$string['page_teacher_materials_heading'] = 'Course materials / RAG';
$string['page_teacher_materials_title'] = 'Course materials / RAG';
$string['page_teacher_simulations_heading'] = 'Saved simulations';
$string['page_teacher_simulations_title'] = 'Saved simulations';
$string['page_teacher_title'] = 'Teacher dashboard';
$string['page_tutor_analytics_heading'] = 'Tutor analyst';
$string['page_tutor_analytics_title'] = 'Tutor analyst';
$string['page_tutor_heading'] = 'AI Tutor';
$string['page_tutor_title'] = 'AI Tutor';
$string['pluginname'] = 'AI Skill Navigator';
$string['privacy:metadata:configured_ai_provider'] = 'Optional external AI provider configured by the site administrator.';
$string['privacy:metadata:content'] = 'User-provided or extracted content.';
$string['privacy:metadata:courseid'] = 'The course identifier.';
$string['privacy:metadata:local_aiskillnav_ass_att'] = 'Student attempts on teacher-generated assessments.';
$string['privacy:metadata:local_aiskillnav_assessment'] = 'Teacher-generated initial and final assessments.';
$string['privacy:metadata:local_aiskillnav_attempt'] = 'Student AI quiz attempts.';
$string['privacy:metadata:local_aiskillnav_chunk'] = 'Search chunks generated from course materials.';
$string['privacy:metadata:local_aiskillnav_material'] = 'Course materials stored for AI-assisted learning.';
$string['privacy:metadata:local_aiskillnav_sim'] = 'Saved simulator suggestions and activities.';
$string['privacy:metadata:local_aiskillnav_tutor_sig'] = 'Tutor questions and interaction signals.';
$string['privacy:metadata:timecreated'] = 'The time the record was created.';
$string['privacy:metadata:timemodified'] = 'The time the record was last modified.';
$string['privacy:metadata:userid'] = 'The user identifier.';
$string['provider'] = 'AI provider';
$string['provider_desc'] = 'Select the AI provider used by the plugin.';
$string['quiz_topic'] = 'Quiz topic';
$string['quizgenerator'] = 'AI Quiz Generator';
$string['recommendations'] = 'Recommendations';
$string['settings'] = 'AI Skill Navigator settings';
$string['settings_allowdestructivecoursebuilder'] = 'Allow destructive AI Course Builder actions';
$string['settings_allowdestructivecoursebuilder_desc'] = 'If disabled, AI Course Builder can create sections and attach files, but cannot rename, hide, move, duplicate or delete existing sections.';
$string['settings_apikey'] = 'API key';
$string['settings_apikey_desc'] = 'Store the key only in Moodle settings. Leave empty for local/prototype providers.';
$string['settings_autoblockcourses'] = 'Automatically add the AI Skill Navigator block to courses';
$string['settings_autoblockcourses_desc'] = 'If enabled, the plugin adds the AI Skill Navigator block to newly created or updated courses. Disabled by default for Marketplace/production installations.';
$string['settings_autosynccourseresources'] = 'Automatically sync course resources on Moodle events';
$string['settings_autosynccourseresources_desc'] = 'If enabled, course resource changes are automatically indexed for AI materials. Disabled by default to avoid unexpected processing of teacher content.';
$string['settings_customheadersjson'] = 'Custom headers JSON';
$string['settings_customheadersjson_desc'] = 'Example: {"Authorization":"Bearer {{apikey}}","Content-Type":"application/json"}';
$string['settings_customheading'] = 'Custom HTTP provider';
$string['settings_customheading_desc'] = 'Use this only if your provider is not directly supported.';
$string['settings_customrequesttemplate'] = 'Custom request JSON template';
$string['settings_customrequesttemplate_desc'] = 'Available placeholders: {{model}}, {{system}}, {{prompt}}, {{max_tokens}}, {{apikey}}.';
$string['settings_customresponsepath'] = 'Custom response path';
$string['settings_customresponsepath_desc'] = 'Example: choices.0.message.content, candidates.0.content.parts.0.text, or _raw.';
$string['settings_embeddingapikey'] = 'Embedding API key';
$string['settings_embeddingapikey_desc'] = 'Optional. If empty, the main API key is reused.';
$string['settings_embeddingendpoint'] = 'Embedding endpoint';
$string['settings_embeddingendpoint_desc'] = 'Optional. For Ollama use http://host.docker.internal:11434. For OpenAI-compatible APIs use the base endpoint.';
$string['settings_embeddingheadersjson'] = 'Custom embedding headers JSON';
$string['settings_embeddingheading'] = 'RAG embeddings';
$string['settings_embeddingheading_desc'] = 'Configure embeddings used by the Course materials / RAG feature. Leave fields empty to use safe defaults.';
$string['settings_embeddingmodel'] = 'Embedding model';
$string['settings_embeddingmodel_desc'] = 'Examples: nomic-embed-text, text-embedding-3-small.';
$string['settings_embeddingprovider'] = 'Embedding provider';
$string['settings_embeddingprovider_custom'] = 'Custom HTTP embeddings';
$string['settings_embeddingprovider_ollama'] = 'Local Ollama embeddings';
$string['settings_embeddingprovider_openai'] = 'OpenAI-compatible embeddings';
$string['settings_embeddingprovider_same'] = 'Same family as chat provider';
$string['settings_embeddingrequesttemplate'] = 'Custom embedding request template';
$string['settings_embeddingrequesttemplate_desc'] = 'Available placeholders: {{model}}, {{input}}, {{apikey}}.';
$string['settings_embeddingresponsepath'] = 'Custom embedding response path';
$string['settings_embeddingresponsepath_desc'] = 'Default: data.0.embedding';
$string['settings_enablelocalocr'] = 'Enable local OCR';
$string['settings_enablelocalocr_desc'] = 'When enabled, scanned PDFs, direct image files, and images embedded in PPTX/DOCX are processed with local OCR when possible.';
$string['settings_enablemathjaxcdn'] = 'Enable external MathJax CDN';
$string['settings_enablemathjaxcdn_desc'] = 'If enabled, tutor pages may load MathJax from jsDelivr to render equations. Disabled by default for privacy and offline production installations.';
$string['settings_endpoint'] = 'Endpoint';
$string['settings_endpoint_desc'] = 'Leave empty to use the default endpoint for Gemini/OpenRouter/OpenAI/Groq/DeepSeek/Ollama. Required for custom HTTP or generic OpenAI-compatible providers.';
$string['settings_externalaiapproved'] = 'Approve external AI for teacher materials';
$string['settings_externalaiapproved_desc'] = 'If disabled, course materials are never sent to external AI providers. Local/prototype providers are unaffected. Per-material teacher approval is still required when this is enabled.';
$string['settings_mainheading'] = 'AI provider configuration';
$string['settings_mainheading_desc'] = 'Configure the AI provider used by the plugin. Prototype mode works without external API keys and is recommended for first installation tests.';
$string['settings_maxuploadbytes'] = 'Maximum teacher material upload size in bytes';
$string['settings_maxuploadbytes_desc'] = 'Default production limit is 25 MB. Increase only if your PHP/Moodle upload limits and server memory allow it.';
$string['settings_model'] = 'Model';
$string['settings_model_desc'] = 'Examples: gemini-1.5-flash, deepseek/deepseek-chat, gpt-4o-mini, qwen2.5:3b.';
$string['settings_ocrheading'] = 'Local OCR extraction';
$string['settings_ocrheading_desc'] = 'Local OCR lets the plugin read scanned PDFs and images embedded in PPTX/DOCX. It uses local Tesseract/Poppler tools inside the server/container, not an external API.';
$string['settings_ocrlanguages'] = 'OCR languages';
$string['settings_ocrlanguages_desc'] = 'Tesseract language codes. Recommended for Italian courses: ita+eng.';
$string['settings_ocrmaximagebytes'] = 'Maximum image size for OCR in bytes';
$string['settings_ocrmaximagebytes_desc'] = 'Images larger than this are skipped to avoid timeouts. Default: 18 MB.';
$string['settings_ocrmaximages'] = 'Maximum images OCR per document';
$string['settings_ocrmaximages_desc'] = 'Upper bound for images extracted from PPTX/DOCX. Higher values are slower.';
$string['settings_productionheading'] = 'Production safety';
$string['settings_productionheading_desc'] = 'Safety gates for real course usage. Keep destructive actions disabled unless testing on a copied course.';
$string['settings_provider'] = 'Provider';
$string['settings_provider_anthropic'] = 'Anthropic Claude API';
$string['settings_provider_custom_http'] = 'Custom HTTP JSON';
$string['settings_provider_deepseek'] = 'DeepSeek API';
$string['settings_provider_desc'] = 'Choose where AI requests are sent.';
$string['settings_provider_gemini'] = 'Google Gemini API';
$string['settings_provider_groq'] = 'Groq API';
$string['settings_provider_mistral'] = 'Mistral API';
$string['settings_provider_ollama'] = 'Local Ollama';
$string['settings_provider_openai'] = 'OpenAI API';
$string['settings_provider_openai_compatible'] = 'Generic OpenAI-compatible API';
$string['settings_provider_openrouter'] = 'OpenRouter multi-LLM gateway';
$string['settings_provider_prototype'] = 'Prototype/demo provider - no external calls';
$string['settings_searchapikey'] = 'Search API key';
$string['settings_searchapikey_desc'] = 'Do not put this key in code or GitHub. Store it only in Moodle settings.';
$string['settings_searchendpoint'] = 'Search endpoint';
$string['settings_searchendpoint_desc'] = 'Optional. Leave empty for default endpoint of the selected provider.';
$string['settings_searchheading'] = 'Live web search';
$string['settings_searchheading_desc'] = 'Optional Search API used by Simulator Finder to verify online simulators/tools. Leave disabled if you do not have a Search API key.';
$string['settings_searchprovider'] = 'Search provider';
$string['settings_searchprovider_brave'] = 'Brave Search API';
$string['settings_searchprovider_desc'] = 'Use Tavily for AI-oriented web search, Brave for independent search, or SerpAPI for Google-style results.';
$string['settings_searchprovider_none'] = 'Disabled';
$string['settings_searchprovider_serpapi'] = 'SerpAPI Google Search';
$string['settings_searchprovider_tavily'] = 'Tavily Search API';
$string['simulator_material_unreadable'] = 'The selected material has no readable text.';
$string['simulator_select_material'] = 'Select at least one course material before generating a simulator exercise.';
$string['skills'] = 'Skills';
$string['studentdashboard'] = 'Student dashboard';
$string['teacherdashboard'] = 'Teacher dashboard';
$string['tutor_question'] = 'Ask a question';
