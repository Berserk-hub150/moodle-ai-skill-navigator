# AI Skill Navigator

<p align="center">
  <img src="assets/readme/hero-banner.png" alt="AI Skill Navigator banner" width="100%">
</p>

<p align="center">
  AI-powered Moodle plugin for tutoring, quizzes, mind maps, XR scenarios and teacher analytics.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Moodle-Plugin-orange" alt="Moodle Plugin">
  <img src="https://img.shields.io/badge/License-GPL--3.0--or--later-blue" alt="License: GPL-3.0-or-later">
  <img src="https://img.shields.io/badge/PHP-8%2B-blue" alt="PHP 8+">
  <img src="https://img.shields.io/badge/AI-DeepSeek%20API%20%7C%20OpenAI--compatible-green" alt="DeepSeek API | OpenAI-compatible">
</p>
AI Skill Navigator is an AI-powered Moodle local plugin developed a prototype for AI-supported learning inside a university LMS.

It adds tutoring, course-material-grounded assistance, quiz generation, mind map generation, XR scenario generation and teacher-oriented analytics to Moodle.

> Academic prototype focused on Generative AI, RAG, Digital Twin, Virtual Worlds and educational technology.

## Highlights

- AI Tutor for open learning questions.
- Course AI Tutor grounded on teacher materials.
- Quiz Generator for formative micro-tests.
- Mind Map Generator for concept visualisation.
- XR Scenario Generator for Virtual Worlds learning activities.
- Teacher Materials area for course knowledge management.
- Student dashboard with learning progress indicators.
- Teacher dashboard with weak-topic overview.
- RAG-oriented support for selected course materials.

## Screenshots

Add screenshots inside:

```text
assets/screenshots/
```

Recommended files:

```text
dashboard.png
ai-tutor.png
quiz-generator.png
mind-map-generator.png
xr-scenario-generator.png
teacher-materials.png
```

## Demo flow

1. Open the AI Skill Navigator dashboard.
2. Ask a question to the AI Tutor.
3. Generate a quiz from a topic or teacher materials.
4. Generate a mind map.
5. Generate an XR scenario.
6. Explain the architecture: Strategy, Factory Method and Facade.

## Architecture

The plugin is organised around Moodle pages and service classes.

The refactored AI layer contains:

- AI provider strategies;
- a provider factory;
- a prompt builder;
- a workflow facade;
- backward-compatible Moodle page integrations.

## Design patterns

### Strategy

The AI provider logic is abstracted through a common interface.

Implemented strategies:

- Ollama provider;
- OpenAI-compatible provider;
- prototype/demo provider.

### Factory Method

The provider factory creates the correct AI provider based on Moodle plugin settings.

### Facade

The AI workflow facade exposes high-level operations such as asking the tutor, generating quizzes, generating mind maps, generating XR scenarios and summarising materials.

## SOLID principles

| Principle | Application |
|---|---|
| Single Responsibility | Provider calls, prompt construction and workflow orchestration are separated. |
| Open/Closed | New AI providers can be added by implementing the provider interface. |
| Liskov Substitution | Provider implementations can be used interchangeably through the same contract. |
| Interface Segregation | Pages depend on a small text-generation interface instead of concrete API classes. |
| Dependency Inversion | High-level workflows depend on abstractions rather than concrete provider classes. |

## Software quality attributes

| Quality attribute | Improvement |
|---|---|
| Maintainability | Smaller service classes and clearer responsibilities. |
| Reusability | AI services are reused by tutor, quiz, mind map and XR modules. |
| Portability | Provider, endpoint and model are configurable. |
| Robustness | Prototype mode keeps the demo usable without an external AI service. |
| Verifiability | PHP files can be linted and tested independently. |
| Comprehensibility | Documentation explains the role of each component. |
| Interoperability | The plugin remains integrated with Moodle courses and pages. |

## Local development

Start Moodle:

```powershell
cd $env:USERPROFILE\Desktop\TESI-MOODLE
docker compose up -d
```

Deploy plugin changes:

```powershell
.\plugins\aiskillnavigator\scripts\deploy-plugin.ps1
```

Run PHP lint checks:

```powershell
.\plugins\aiskillnavigator\scripts\lint-plugin.ps1
```

## Main plugin pages

```text
http://localhost:8080/local/aiskillnavigator/index.php
http://localhost:8080/local/aiskillnavigator/pages/tutor.php?courseid=1
http://localhost:8080/local/aiskillnavigator/pages/quizgenerator.php?courseid=1
http://localhost:8080/local/aiskillnavigator/pages/mindmapgenerator.php?courseid=1
http://localhost:8080/local/aiskillnavigator/pages/scenariogenerator.php?courseid=1
```

## AI provider configuration

The current demo setup uses the **DeepSeek API**.

DeepSeek is configured as a dedicated provider name, while internally it reuses the OpenAI-compatible provider strategy.

Supported provider names include:

- `deepseek`;
- `openai`;
- `openai_compatible`;
- `openrouter`;
- `groq`;
- `ollama`;
- `prototype`;
- `mock`;
- `demo`.

Current demo configuration:

| Setting | Value |
|---|---|
| Provider | `deepseek` |
| Endpoint | `https://api.deepseek.com` |
| Model | `deepseek-chat` |

API keys are not stored in the repository. Credentials must be configured through Moodle/plugin settings or local environment configuration.

Ollama remains supported as an optional local provider, but it is not the default provider for the current demo.



## Plugin structure

The root PHP files are lightweight Moodle-compatible wrappers.

The main implementation is organised as follows:

```text
pages/
  Moodle page implementations

includes/
  shared page helpers

assets/css/
  plugin stylesheets

classes/service/
  AI providers, prompt builders, workflow facade and services
```

This keeps public Moodle URLs stable while making the repository structure easier to navigate.
## Project documentation

- [Roadmap](docs/ROADMAP.md)
- [Release notes](docs/RELEASE_NOTES.md)
- [Contributing guide](.github/CONTRIBUTING.md)

## Plugin structure

The root PHP files are lightweight Moodle-compatible wrappers.

The main implementation is organised as follows:

```text
pages/
  Moodle page implementations

includes/
  shared page helpers

assets/css/
  plugin stylesheets

classes/service/
  AI providers, prompt builders, workflow facade and services
```

This keeps public Moodle URLs stable while making the repository structure easier to navigate.
## Roadmap

- Add real screenshot gallery.
- Add a short demo GIF.
- Improve PDF material extraction.
- Add JSON schema validation for XR blueprints.
- Improve quiz difficulty calibration.
- Add bilingual Italian/English UI strings.
- Add automated tests for provider factory and prompt builder.



## Plugin structure

The root PHP files are lightweight Moodle-compatible wrappers.

The main implementation is organised as follows:

```text
pages/
  Moodle page implementations

includes/
  shared page helpers

assets/css/
  plugin stylesheets

classes/service/
  AI providers, prompt builders, workflow facade and services
```

This keeps public Moodle URLs stable while making the repository structure easier to navigate.
## Project documentation

- [Roadmap](docs/ROADMAP.md)
- [Release notes](docs/RELEASE_NOTES.md)
- [Contributing guide](.github/CONTRIBUTING.md)

## Plugin structure

The root PHP files are lightweight Moodle-compatible wrappers.

The main implementation is organised as follows:

```text
pages/
  Moodle page implementations

includes/
  shared page helpers

assets/css/
  plugin stylesheets

classes/service/
  AI providers, prompt builders, workflow facade and services
```

This keeps public Moodle URLs stable while making the repository structure easier to navigate.
## Status

This repository contains an academic thesis prototype focused on feasibility, architecture, extensibility and software quality improvements.


## License

This project is licensed under the **GNU General Public License v3.0 or later**.

See the [LICENSE](LICENSE) file for details.

SPDX-License-Identifier: `GPL-3.0-or-later`

