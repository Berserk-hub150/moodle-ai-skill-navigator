# AI Skill Navigator

<p align="center">
  <img src="assets/readme/hero-banner.png" alt="AI Skill Navigator - AI-powered Moodle learning tools" width="100%">
</p>


<!-- MICRO-CONTRIBUTIONS-START -->

[![GitHub stars](https://img.shields.io/github/stars/Berserk-hub150/moodle-ai-skill-navigator?style=social)](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/Berserk-hub150/moodle-ai-skill-navigator?style=social)](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/forks)
[![Good First Issues](https://img.shields.io/github/issues-search/Berserk-hub150/moodle-ai-skill-navigator?query=is%3Aopen%20label%3A%22good%20first%20issue%22&label=good%20first%20issues)](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22)
[![CodeTriage](https://www.codetriage.com/berserk-hub150/moodle-ai-skill-navigator/badges/users.svg)](https://www.codetriage.com/Berserk-hub150/moodle-ai-skill-navigator)

> AI-powered Moodle learning tools: course-aware tutoring, quizzes, mind maps, assessment, adaptive review, RAG, analytics and course-building helpers.

## 🚀 Make your first open-source PR in 2–5 minutes

New to open source? Start with a **browser-only micro-contribution**.

- ✅ No Moodle installation.
- ✅ No local development setup.
- ✅ No coding required for many tasks.
- ✅ One tiny JSON file per issue.
- ✅ Small first-time-contributor PRs are prioritized.

**[Browse 2–5 minute issues](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/issues?q=is%3Aissue+is%3Aopen+label%3Amicro-contribution)**

**Pick an issue → Fork → Create one tiny file → Pull Request → Contributor**

⭐ If the project is useful to you, a star helps other Moodle developers discover it. Stars are appreciated, never required.

<!-- MICRO-CONTRIBUTIONS-END -->

[![Plugin CI](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/actions/workflows/ci.yml/badge.svg)](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/actions/workflows/ci.yml)

AI Skill Navigator is a Moodle plugin suite that adds course-aware AI learning tools for students and teachers.

The package contains:

- `local_aiskillnavigator`: the main local plugin with AI tutor, quiz generation, mind maps, assessments, material/RAG tools, learning-gap analysis, simulator suggestions and course-building helpers.
- `block_aiskillnavigator`: an optional course block that links users to the tools available for their role.

## Production defaults

The plugin is designed to install safely with conservative defaults:

- The default AI provider is `prototype`, which performs no external AI calls.
- External AI use for course materials is disabled until an administrator enables it.
- Per-material approval is required before teacher materials can be sent to external providers.
- Destructive AI Course Builder actions are disabled by default.
- Automatic course-resource synchronisation on Moodle events is disabled by default.
- Automatic block insertion into courses is disabled by default.
- External MathJax CDN loading is disabled by default.

Administrators can enable optional external services from the plugin settings.

## Main features

- Course-aware AI Tutor.
- AI Quiz Generator.
- AI Mind Map Generator.
- Initial and final assessments.
- Adaptive review for weak skills.
- Teacher dashboard and tutor analytics.
- Course Materials / RAG management.
- Learning-gap analysis.
- AI Course Builder with production safety gates.
- Simulator Finder and saved simulation activities.

## Installation

Install the local plugin in:

```text
local/aiskillnavigator
```

Install the optional block in:

```text
blocks/aiskillnavigator
```

Then visit:

```text
Site administration > Notifications
```

## Configuration

Open:

```text
Site administration > Plugins > Local plugins > AI Skill Navigator
```

Important production settings:

- `Provider`: keep `prototype` for first installation checks.
- `Approve external AI for teacher materials`: disabled by default.
- `Allow destructive AI Course Builder actions`: disabled by default.
- `Automatically sync course resources on Moodle events`: disabled by default.
- `Automatically add the AI Skill Navigator block to courses`: disabled by default.
- `Enable external MathJax CDN`: disabled by default.

## Privacy

The plugin stores course materials, quiz attempts, assessment attempts, saved simulations and tutor interaction signals. It implements Moodle's Privacy API for metadata, export and deletion of user data. External AI providers are optional and disabled for course materials unless explicitly approved.

## Requirements

- Moodle 4.4 or later.
- PHP version supported by the target Moodle version.
- Optional cURL support for external AI/search providers.

## Development validation

The repository includes automated checks for PHP 8.1-8.3, XMLDB parsing, JavaScript syntax, UTF-8 BOM regressions, RAG API compatibility, and safe embedding defaults. See [CONTRIBUTING.md](CONTRIBUTING.md) for the local commands and `docs/manual-test-checklist.md` for Moodle runtime scenarios.

## Packaging

For a Moodle installation package, place `plugins/aiskillnavigator` at `local/aiskillnavigator` and `plugins/block_aiskillnavigator` at `blocks/aiskillnavigator`. Do not package the repository root as a single Moodle plugin directory.

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=Berserk-hub150/moodle-ai-skill-navigator&type=Date)](https://star-history.com/#Berserk-hub150/moodle-ai-skill-navigator&Date)

## License

GPL v3 or later.
