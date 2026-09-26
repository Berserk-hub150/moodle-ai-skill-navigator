# AI Skill Navigator

[![Plugin CI](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/actions/workflows/ci.yml/badge.svg)](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/actions/workflows/ci.yml)
[![License: GPL v3 or later](https://img.shields.io/badge/license-GPL--3.0--or--later-blue)](LICENSE)

Course-aware tutoring, assessment and teaching tools for Moodle. Teachers choose the course materials available to the AI; students can use them for questions, practice quizzes and revision.

## Components

| Component | Repository directory | Moodle installation path |
| --- | --- | --- |
| Main plugin | `plugins/aiskillnavigator` | `local/aiskillnavigator` |
| Optional course block | `plugins/block_aiskillnavigator` | `blocks/aiskillnavigator` |

The block provides links to tools according to the user's course permissions. Each component is installed separately.

## Features

| For students | For teachers |
| --- | --- |
| Tutor grounded in selected course materials | Material synchronisation and per-material AI permissions |
| Practice quiz and mind-map generation | Initial and final assessment authoring |
| Initial/final assessments and adaptive review | Quiz performance dashboard and tutor question analytics |
| Course learning tools from one entry point | Learning-gap analysis, Course Builder and Simulator Finder |

The Teacher dashboard reads both plugin quiz attempts and scored, finished Moodle quiz attempts, subject to quiz reporting permissions and separate-group restrictions. Tutor Analytics summarises successful tutor interactions; its skill and difficulty labels are keyword-based indicators, not grades.

## Requirements

- Minimum declared Moodle version: **4.4**. The Moodle integration workflow targets **4.5**.
- PHP supported by the installed Moodle version. Repository regression checks cover **PHP 8.1–8.3**.
- PHP cURL for HTTP AI providers; optional document extraction tools depend on the file formats used.
- An AI provider configured by the site administrator for generated answers. The default `prototype` provider returns fixed demonstration responses and makes no AI network calls.

## Installation

From a clone of this repository, copy the two plugin directories into an existing Moodle installation:

```bash
MOODLE_PATH=/path/to/moodle
cp -R plugins/aiskillnavigator "$MOODLE_PATH/local/aiskillnavigator"
cp -R plugins/block_aiskillnavigator "$MOODLE_PATH/blocks/aiskillnavigator"
```

For Windows PowerShell:

```powershell
$MoodlePath = 'C:\path\to\moodle'
Copy-Item plugins/aiskillnavigator "$MoodlePath/local/aiskillnavigator" -Recurse
Copy-Item plugins/block_aiskillnavigator "$MoodlePath/blocks/aiskillnavigator" -Recurse
```

Visit **Site administration → Notifications** to install or upgrade. For an existing installation, back up the database and replace the installed component's files with the new version before running the upgrade.

Version 1.0.5 migrates the plugin's legacy table names to the `local_aiskillnavigator_` prefix while preserving their data. Update custom SQL reports that reference `local_aiskillnav_*` or `local_aisn_kg_*` tables.

The repository ZIP is not an installable Moodle plugin. See [packaging instructions](MARKETPLACE.md) for separate component ZIPs.

## First run

1. Open **Site administration → Plugins → Local plugins → AI Skill Navigator** and keep `prototype` selected for the initial interface check.
2. Add the **AI Skill Navigator** block to a test course, or open `/local/aiskillnavigator/pages/index.php?courseid=COURSE_ID`.
3. As a teacher, open **Manage teacher materials** and synchronise the course resources. Confirm that each intended resource has readable text.
4. Configure the provider, endpoint, model and credentials in the plugin settings. Local Ollama and supported external HTTP providers are available.
5. Test a tutor question and a practice quiz with a student account. Return to the Teacher dashboard and Tutor Analytics to inspect the results.

For external providers, course materials require both **Approve external AI for teacher materials** in site settings and **Allow external AI** on each material. An Ollama service at a remote URL is treated as external.

**AI request timeout (seconds)** controls generation requests across providers. The default is 60 seconds; the supported range is 10–600. A proxy or web server may impose a shorter limit.

## Data and defaults

The plugin stores course materials, plugin quiz/assessment attempts, saved simulations and tutor interaction signals. It implements Moodle Privacy API metadata, export and deletion. Native Moodle quiz attempts remain in Moodle's own tables.

Material approval controls the use of stored course materials. Text typed or pasted directly into an AI tool is sent to the configured provider when that tool is used. Optional web search, OCR and external MathJax have separate settings.

Destructive Course Builder actions, automatic course-resource synchronisation on events, automatic block insertion and the external MathJax CDN are disabled by default. Review generated assessments and course changes before using them with students.

## Troubleshooting and limits

- **No materials:** synchronise from Manage teacher materials, check resource visibility and verify text extraction. Scanned documents may need OCR.
- **Material cannot be selected:** check the site-level external AI approval and the individual material permission. The Simulator Finder displays the active provider's policy.
- **API timeout:** increase the request timeout, check the endpoint/model and inspect server or proxy limits. Provider failures are reported separately from invalid assessment JSON.
- **Empty Tutor Analytics:** ask a question as a student and refresh the report after a successful response. Existing conversations are not retroactively imported.
- **Missing Moodle quiz result:** previews, unfinished or unscored attempts are excluded. The viewer needs quiz report permission and appropriate group access.
- **AI output:** model responses, extracted text and suggested simulations need human review. Changing the provider can change output quality and format.

Installation and regression checks do not establish compatibility with every Moodle release, theme, provider or production environment. Run the [manual test checklist](docs/manual-test-checklist.md) on your deployment target.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for setup, validation and issue triage, [architecture](docs/architecture-overview.md) for the code structure, and [release notes](docs/RELEASE_NOTES.md) for changes.

Bug reports should include reproduction steps, Moodle/PHP versions and relevant provider configuration, with secrets and student data removed.

## License

[GNU GPL v3 or later](LICENSE).
