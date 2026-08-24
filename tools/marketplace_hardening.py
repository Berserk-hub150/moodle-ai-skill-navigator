#!/usr/bin/env python3
"""Prepare local_aiskillnavigator source for Moodle Marketplace review.

This script is intentionally deterministic so it can be run in CI and locally.
It normalises Moodle GPL boilerplate, removes direct request-superglobal access
from the known nested-form/material helper paths, and moves admin setting labels
into the English language pack.
"""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path("plugins/aiskillnavigator")
COMPONENT = "local_aiskillnavigator"
COPYRIGHT = "2026 Luca Magrini"

LICENSE_BLOCK = """// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
"""

SETTINGS_STRINGS = {
    "AI provider configuration": ("settings_mainheading", "AI provider configuration"),
    "Configure the AI provider used by the plugin. Prototype mode works without external API keys and is recommended for first installation tests.": ("settings_mainheading_desc", "Configure the AI provider used by the plugin. Prototype mode works without external API keys and is recommended for first installation tests."),
    "Provider": ("settings_provider", "Provider"),
    "Choose where AI requests are sent.": ("settings_provider_desc", "Choose where AI requests are sent."),
    "Prototype/demo provider - no external calls": ("settings_provider_prototype", "Prototype/demo provider - no external calls"),
    "Google Gemini API": ("settings_provider_gemini", "Google Gemini API"),
    "Anthropic Claude API": ("settings_provider_anthropic", "Anthropic Claude API"),
    "OpenRouter multi-LLM gateway": ("settings_provider_openrouter", "OpenRouter multi-LLM gateway"),
    "Local Ollama": ("settings_provider_ollama", "Local Ollama"),
    "OpenAI API": ("settings_provider_openai", "OpenAI API"),
    "Generic OpenAI-compatible API": ("settings_provider_openai_compatible", "Generic OpenAI-compatible API"),
    "DeepSeek API": ("settings_provider_deepseek", "DeepSeek API"),
    "Groq API": ("settings_provider_groq", "Groq API"),
    "Mistral API": ("settings_provider_mistral", "Mistral API"),
    "Custom HTTP JSON": ("settings_provider_custom_http", "Custom HTTP JSON"),
    "Endpoint": ("settings_endpoint", "Endpoint"),
    "Leave empty to use the default endpoint for Gemini/OpenRouter/OpenAI/Groq/DeepSeek/Ollama. Required for custom HTTP or generic OpenAI-compatible providers.": ("settings_endpoint_desc", "Leave empty to use the default endpoint for Gemini/OpenRouter/OpenAI/Groq/DeepSeek/Ollama. Required for custom HTTP or generic OpenAI-compatible providers."),
    "Model": ("settings_model", "Model"),
    "Examples: gemini-1.5-flash, deepseek/deepseek-chat, gpt-4o-mini, qwen2.5:3b.": ("settings_model_desc", "Examples: gemini-1.5-flash, deepseek/deepseek-chat, gpt-4o-mini, qwen2.5:3b."),
    "API key": ("settings_apikey", "API key"),
    "Store the key only in Moodle settings. Leave empty for local/prototype providers.": ("settings_apikey_desc", "Store the key only in Moodle settings. Leave empty for local/prototype providers."),
    "Custom HTTP provider": ("settings_customheading", "Custom HTTP provider"),
    "Use this only if your provider is not directly supported.": ("settings_customheading_desc", "Use this only if your provider is not directly supported."),
    "Custom request JSON template": ("settings_customrequesttemplate", "Custom request JSON template"),
    "Available placeholders: {{model}}, {{system}}, {{prompt}}, {{max_tokens}}, {{apikey}}.": ("settings_customrequesttemplate_desc", "Available placeholders: {{model}}, {{system}}, {{prompt}}, {{max_tokens}}, {{apikey}}."),
    "Custom headers JSON": ("settings_customheadersjson", "Custom headers JSON"),
    "Example: {\"Authorization\":\"Bearer {{apikey}}\",\"Content-Type\":\"application/json\"}": ("settings_customheadersjson_desc", "Example: {\"Authorization\":\"Bearer {{apikey}}\",\"Content-Type\":\"application/json\"}"),
    "Custom response path": ("settings_customresponsepath", "Custom response path"),
    "Example: choices.0.message.content, candidates.0.content.parts.0.text, or _raw.": ("settings_customresponsepath_desc", "Example: choices.0.message.content, candidates.0.content.parts.0.text, or _raw."),
    "RAG embeddings": ("settings_embeddingheading", "RAG embeddings"),
    "Configure embeddings used by the Course materials / RAG feature. Leave fields empty to use safe defaults.": ("settings_embeddingheading_desc", "Configure embeddings used by the Course materials / RAG feature. Leave fields empty to use safe defaults."),
    "Embedding provider": ("settings_embeddingprovider", "Embedding provider"),
    "Same family as chat provider": ("settings_embeddingprovider_same", "Same family as chat provider"),
    "Local Ollama embeddings": ("settings_embeddingprovider_ollama", "Local Ollama embeddings"),
    "OpenAI-compatible embeddings": ("settings_embeddingprovider_openai", "OpenAI-compatible embeddings"),
    "Custom HTTP embeddings": ("settings_embeddingprovider_custom", "Custom HTTP embeddings"),
    "Embedding endpoint": ("settings_embeddingendpoint", "Embedding endpoint"),
    "Optional. For Ollama use http://host.docker.internal:11434. For OpenAI-compatible APIs use the base endpoint.": ("settings_embeddingendpoint_desc", "Optional. For Ollama use http://host.docker.internal:11434. For OpenAI-compatible APIs use the base endpoint."),
    "Embedding model": ("settings_embeddingmodel", "Embedding model"),
    "Examples: nomic-embed-text, text-embedding-3-small.": ("settings_embeddingmodel_desc", "Examples: nomic-embed-text, text-embedding-3-small."),
    "Embedding API key": ("settings_embeddingapikey", "Embedding API key"),
    "Optional. If empty, the main API key is reused.": ("settings_embeddingapikey_desc", "Optional. If empty, the main API key is reused."),
    "Custom embedding request template": ("settings_embeddingrequesttemplate", "Custom embedding request template"),
    "Available placeholders: {{model}}, {{input}}, {{apikey}}.": ("settings_embeddingrequesttemplate_desc", "Available placeholders: {{model}}, {{input}}, {{apikey}}."),
    "Custom embedding headers JSON": ("settings_embeddingheadersjson", "Custom embedding headers JSON"),
    "Custom embedding response path": ("settings_embeddingresponsepath", "Custom embedding response path"),
    "Default: data.0.embedding": ("settings_embeddingresponsepath_desc", "Default: data.0.embedding"),
    "Local OCR extraction": ("settings_ocrheading", "Local OCR extraction"),
    "Local OCR lets the plugin read scanned PDFs and images embedded in PPTX/DOCX. It uses local Tesseract/Poppler tools inside the server/container, not an external API.": ("settings_ocrheading_desc", "Local OCR lets the plugin read scanned PDFs and images embedded in PPTX/DOCX. It uses local Tesseract/Poppler tools inside the server/container, not an external API."),
    "Enable local OCR": ("settings_enablelocalocr", "Enable local OCR"),
    "When enabled, scanned PDFs, direct image files, and images embedded in PPTX/DOCX are processed with local OCR when possible.": ("settings_enablelocalocr_desc", "When enabled, scanned PDFs, direct image files, and images embedded in PPTX/DOCX are processed with local OCR when possible."),
    "OCR languages": ("settings_ocrlanguages", "OCR languages"),
    "Tesseract language codes. Recommended for Italian courses: ita+eng.": ("settings_ocrlanguages_desc", "Tesseract language codes. Recommended for Italian courses: ita+eng."),
    "Maximum images OCR per document": ("settings_ocrmaximages", "Maximum images OCR per document"),
    "Upper bound for images extracted from PPTX/DOCX. Higher values are slower.": ("settings_ocrmaximages_desc", "Upper bound for images extracted from PPTX/DOCX. Higher values are slower."),
    "Maximum image size for OCR in bytes": ("settings_ocrmaximagebytes", "Maximum image size for OCR in bytes"),
    "Images larger than this are skipped to avoid timeouts. Default: 18 MB.": ("settings_ocrmaximagebytes_desc", "Images larger than this are skipped to avoid timeouts. Default: 18 MB."),
    "Production safety": ("settings_productionheading", "Production safety"),
    "Safety gates for real course usage. Keep destructive actions disabled unless testing on a copied course.": ("settings_productionheading_desc", "Safety gates for real course usage. Keep destructive actions disabled unless testing on a copied course."),
    "Approve external AI for teacher materials": ("settings_externalaiapproved", "Approve external AI for teacher materials"),
    "If disabled, course materials are never sent to external AI providers. Local/prototype providers are unaffected. Per-material teacher approval is still required when this is enabled.": ("settings_externalaiapproved_desc", "If disabled, course materials are never sent to external AI providers. Local/prototype providers are unaffected. Per-material teacher approval is still required when this is enabled."),
    "Allow destructive AI Course Builder actions": ("settings_allowdestructivecoursebuilder", "Allow destructive AI Course Builder actions"),
    "If disabled, AI Course Builder can create sections and attach files, but cannot rename, hide, move, duplicate or delete existing sections.": ("settings_allowdestructivecoursebuilder_desc", "If disabled, AI Course Builder can create sections and attach files, but cannot rename, hide, move, duplicate or delete existing sections."),
    "Automatically add the AI Skill Navigator block to courses": ("settings_autoblockcourses", "Automatically add the AI Skill Navigator block to courses"),
    "If enabled, the plugin adds the AI Skill Navigator block to newly created or updated courses. Disabled by default for marketplace/production installations.": ("settings_autoblockcourses_desc", "If enabled, the plugin adds the AI Skill Navigator block to newly created or updated courses. Disabled by default for Marketplace/production installations."),
    "Automatically sync course resources on Moodle events": ("settings_autosynccourseresources", "Automatically sync course resources on Moodle events"),
    "If enabled, course resource changes are automatically indexed for AI materials. Disabled by default to avoid unexpected processing of teacher content.": ("settings_autosynccourseresources_desc", "If enabled, course resource changes are automatically indexed for AI materials. Disabled by default to avoid unexpected processing of teacher content."),
    "Enable external MathJax CDN": ("settings_enablemathjaxcdn", "Enable external MathJax CDN"),
    "If enabled, tutor pages may load MathJax from jsDelivr to render equations. Disabled by default for privacy and offline production installations.": ("settings_enablemathjaxcdn_desc", "If enabled, tutor pages may load MathJax from jsDelivr to render equations. Disabled by default for privacy and offline production installations."),
    "Maximum teacher material upload size in bytes": ("settings_maxuploadbytes", "Maximum teacher material upload size in bytes"),
    "Default production limit is 25 MB. Increase only if your PHP/Moodle upload limits and server memory allow it.": ("settings_maxuploadbytes_desc", "Default production limit is 25 MB. Increase only if your PHP/Moodle upload limits and server memory allow it."),
    "Live web search": ("settings_searchheading", "Live web search"),
    "Optional Search API used by Simulator Finder to verify online simulators/tools. Leave disabled if you do not have a Search API key.": ("settings_searchheading_desc", "Optional Search API used by Simulator Finder to verify online simulators/tools. Leave disabled if you do not have a Search API key."),
    "Search provider": ("settings_searchprovider", "Search provider"),
    "Use Tavily for AI-oriented web search, Brave for independent search, or SerpAPI for Google-style results.": ("settings_searchprovider_desc", "Use Tavily for AI-oriented web search, Brave for independent search, or SerpAPI for Google-style results."),
    "Disabled": ("settings_searchprovider_none", "Disabled"),
    "Tavily Search API": ("settings_searchprovider_tavily", "Tavily Search API"),
    "Brave Search API": ("settings_searchprovider_brave", "Brave Search API"),
    "SerpAPI Google Search": ("settings_searchprovider_serpapi", "SerpAPI Google Search"),
    "Search endpoint": ("settings_searchendpoint", "Search endpoint"),
    "Optional. Leave empty for default endpoint of the selected provider.": ("settings_searchendpoint_desc", "Optional. Leave empty for default endpoint of the selected provider."),
    "Search API key": ("settings_searchapikey", "Search API key"),
    "Do not put this key in code or GitHub. Store it only in Moodle settings.": ("settings_searchapikey_desc", "Do not put this key in code or GitHub. Store it only in Moodle settings."),
}


def normalise_php_boilerplate(path: Path) -> bool:
    text = path.read_text(encoding="utf-8")
    if not text.startswith("<?php"):
        return False

    head = text[:2500]
    if "GNU GPL v3 or later" in head and "@copyright" in head and "@license" in head:
        return False

    body = text[len("<?php"):]
    body = re.sub(
        r"^\s*// This file is part of Moodle - https://moodle\.org/\s*\n",
        "\n",
        body,
        count=1,
    )
    text = "<?php\n" + LICENSE_BLOCK + body.lstrip("\n")
    path.write_text(text, encoding="utf-8")
    return True


def replace_superglobal_access() -> list[str]:
    changed: list[str] = []

    assessments = ROOT / "pages/teacher_assessments.php"
    text = assessments.read_text(encoding="utf-8")
    old = "$post = $_POST;"
    new = "$submitted = data_submitted();\n    $post = $submitted === false ? [] : (array) $submitted;"
    if old in text:
        assessments.write_text(text.replace(old, new, 1), encoding="utf-8")
        changed.append(str(assessments))

    helper = ROOT / "includes/simulator_materials_helper.php"
    text = helper.read_text(encoding="utf-8")
    pattern = re.compile(
        r"function local_aisn_sim_require_materials_for_post\(int \$courseid\): void \{.*?\n\}\n\nfunction local_aisn_sim_material_selector_html",
        re.S,
    )
    replacement = """function local_aisn_sim_require_materials_for_post(int $courseid): void {
    $submitted = data_submitted();
    if ($submitted === false) {
        return;
    }

    $ids = local_aisn_sim_selected_ids();
    if (empty($ids)) {
        redirect(
            new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
            get_string('simulator_select_material', 'local_aiskillnavigator'),
            3,
            \\core\\output\\notification::NOTIFY_ERROR
        );
    }

    $context = local_aisn_sim_material_context($courseid, $ids);
    if ($context === '') {
        redirect(
            new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
            get_string('simulator_material_unreadable', 'local_aiskillnavigator'),
            3,
            \\core\\output\\notification::NOTIFY_ERROR
        );
    }
}

function local_aisn_sim_material_selector_html"""
    newtext, count = pattern.subn(lambda _match: replacement, text, count=1)
    if count:
        helper.write_text(newtext, encoding="utf-8")
        changed.append(str(helper))

    return changed


def localise_settings() -> list[str]:
    changed: list[str] = []
    settings = ROOT / "settings.php"
    text = settings.read_text(encoding="utf-8")

    # Prefer the existing canonical pluginname string for the admin page title.
    text = text.replace("'AI Skill Navigator'", "get_string('pluginname', 'local_aiskillnavigator')")

    # Longest-first prevents shorter values from matching inside longer descriptions.
    for literal, (key, _value) in sorted(SETTINGS_STRINGS.items(), key=lambda item: len(item[0]), reverse=True):
        php_literal = "'" + literal.replace("\\", "\\\\").replace("'", "\\'") + "'"
        replacement = f"get_string('{key}', 'local_aiskillnavigator')"
        text = text.replace(php_literal, replacement)

    original = settings.read_text(encoding="utf-8")
    if text != original:
        settings.write_text(text, encoding="utf-8")
        changed.append(str(settings))

    lang = ROOT / "lang/en/local_aiskillnavigator.php"
    langtext = lang.read_text(encoding="utf-8")
    additions: list[str] = []
    extra = {
        "simulator_select_material": "Select at least one course material before generating a simulator exercise.",
        "simulator_material_unreadable": "The selected material has no readable text.",
    }
    combined = {key: value for _literal, (key, value) in SETTINGS_STRINGS.items()}
    combined.update(extra)

    for key, value in sorted(combined.items()):
        marker = f"$string['{key}']"
        if marker in langtext:
            continue
        escaped = value.replace("\\", "\\\\").replace("'", "\\'")
        additions.append(f"$string['{key}'] = '{escaped}';")

    if additions:
        langtext = langtext.rstrip() + "\n\n// Marketplace/admin settings strings.\n" + "\n".join(additions) + "\n"
        lang.write_text(langtext, encoding="utf-8")
        changed.append(str(lang))

    return changed


def scan_remaining_superglobals() -> list[str]:
    findings: list[str] = []
    pattern = re.compile(r"\$_(?:GET|POST|REQUEST|COOKIE|FILES|SERVER)\b")
    for path in ROOT.rglob("*.php"):
        text = path.read_text(encoding="utf-8")
        if pattern.search(text):
            findings.append(str(path))
    return findings


def main() -> int:
    changed: set[str] = set()

    for path in ROOT.rglob("*.php"):
        if normalise_php_boilerplate(path):
            changed.add(str(path))

    changed.update(replace_superglobal_access())
    changed.update(localise_settings())

    remaining = scan_remaining_superglobals()
    print(f"Changed {len(changed)} files.")
    for path in sorted(changed):
        print(f"  updated: {path}")

    if remaining:
        print("Remaining direct request superglobal access:")
        for path in remaining:
            print(f"  {path}")
        return 2

    print("Marketplace hardening checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
