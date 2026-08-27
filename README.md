# AI Skill Navigator

<p align="center">
  <img
    src="assets/readme/hero-banner.png"
    alt="AI Skill Navigator - AI-powered Moodle learning tools"
    width="100%"
  >
</p>

<p align="center">
  <a href="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/stargazers">
    <img src="https://img.shields.io/github/stars/Berserk-hub150/moodle-ai-skill-navigator?style=social" alt="GitHub stars">
  </a>
  <a href="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/forks">
    <img src="https://img.shields.io/github/forks/Berserk-hub150/moodle-ai-skill-navigator?style=social" alt="GitHub forks">
  </a>
  <a href="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22">
    <img src="https://img.shields.io/github/issues-search/Berserk-hub150/moodle-ai-skill-navigator?query=is%3Aopen%20label%3A%22good%20first%20issue%22&label=good%20first%20issues" alt="Good first issues">
  </a>
  <a href="https://www.codetriage.com/Berserk-hub150/moodle-ai-skill-navigator">
    <img src="https://www.codetriage.com/berserk-hub150/moodle-ai-skill-navigator/badges/users.svg" alt="CodeTriage">
  </a>
</p>

<!-- MICRO-CONTRIBUTIONS-START -->

<h2 align="center">🏆 GitHub Rankings</h2>

<p align="center">
  <img
    src="https://img.shields.io/badge/%231-AI--POWERED%20MOODLE%20PLUGIN-00B894?style=for-the-badge"
    alt="#1 AI-powered Moodle plugin on GitHub"
    height="42"
  >
</p>

<h3 align="center">
  🤖 #1 most-starred AI-powered Moodle plugin repository on GitHub
</h3>

<p align="center">
  <a href="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/stargazers">
    <img src="https://img.shields.io/github/stars/Berserk-hub150/moodle-ai-skill-navigator?style=for-the-badge&label=Stars" alt="GitHub Stars">
  </a>
  <a href="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/forks">
    <img src="https://img.shields.io/github/forks/Berserk-hub150/moodle-ai-skill-navigator?style=for-the-badge&label=Forks" alt="GitHub Forks">
  </a>
  <a href="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/issues">
    <img src="https://img.shields.io/github/issues/Berserk-hub150/moodle-ai-skill-navigator?style=for-the-badge&label=Open%20Issues" alt="Open Issues">
  </a>
  <a href="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/graphs/contributors">
    <img src="https://img.shields.io/github/contributors/Berserk-hub150/moodle-ai-skill-navigator?style=for-the-badge&label=Contributors" alt="Contributors">
  </a>
</p>

<p align="center">
  <img
    src="https://img.shields.io/badge/%235-MOST--STARRED%20MOODLE%20PLUGIN-6C5CE7?style=for-the-badge"
    alt="#5 most-starred Moodle plugin repository on GitHub"
    height="36"
  >
</p>

<p align="center">
  <strong>🏆 #5 most-starred Moodle plugin repository on GitHub</strong>
</p>

<p align="center">
  <sub>Rankings based on GitHub stars — August 2026</sub>
</p>

> **AI-powered Moodle learning tools:** course-aware tutoring, quizzes, mind maps, assessment, adaptive review, RAG, analytics and course-building helpers.

---

## 🚀 Make your first open-source PR in 2–5 minutes

New to open source? Start with a **browser-only micro-contribution**.

- ✅ No Moodle installation.
- ✅ No local development setup.
- ✅ No coding required for many tasks.
- ✅ One tiny JSON file per issue.
- ✅ Small first-time-contributor PRs are prioritized.

### 👉 [Browse 2-5 minute issues](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/issues?q=is%3Aissue+is%3Aopen+label%3Amicro-contribution)

**Pick an issue → Fork → Create one tiny file → Pull Request → Contributor**

⭐ If the project is useful to you, a star helps other Moodle developers discover it. Stars are appreciated, never required.

<!-- MICRO-CONTRIBUTIONS-END -->

---

<p align="center">
  <a href="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/actions/workflows/ci.yml">
    <img
      src="https://github.com/Berserk-hub150/moodle-ai-skill-navigator/actions/workflows/ci.yml/badge.svg"
      alt="Plugin CI"
    >
  </a>
</p>

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

## Frequently Asked Questions

### What Moodle versions are supported?

Moodle 4.4 or later.

### Is external AI mandatory?

No. The default AI provider is `prototype`, which performs no external AI calls. External AI is disabled by default and requires an administrator to explicitly enable it.

### Where can course data be sent?

Course data is never sent to external AI providers unless an administrator explicitly approves it. Per-material approval is required before any teacher materials can be forwarded to external providers.

### Where should I start as a first-time contributor?

Start with our [good first issues](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22) or read [BEGINNER_CONTRIBUTING.md](BEGINNER_CONTRIBUTING.md) for a step-by-step walkthrough.

## Development validation

The repository includes automated checks for PHP 8.1–8.3, XMLDB parsing, JavaScript syntax, UTF-8 BOM regressions, RAG API compatibility, and safe embedding defaults.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the local commands and `docs/manual-test-checklist.md` for Moodle runtime scenarios.

## Packaging

For a Moodle installation package:

- Place `plugins/aiskillnavigator` at `local/aiskillnavigator`.
- Place `plugins/block_aiskillnavigator` at `blocks/aiskillnavigator`.

Do not package the repository root as a single Moodle plugin directory.

## License

GPL v3 or later.
