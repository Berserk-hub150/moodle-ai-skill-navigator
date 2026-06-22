# Architecture overview

AI Skill Navigator is a Moodle local plugin that integrates Generative AI into course activities.

The plugin is organised into four main areas:

- Moodle pages, used as entry points for teachers and students.
- Shared helpers, used for rendering, materials, RAG, simulations and UI utilities.
- Service classes, used for AI providers, prompt construction, workflows and embedding/RAG support.
- Moodle database files, used for install and upgrade schema management.

## Main layers

```text
pages/
  Moodle page controllers and user interfaces

includes/
  shared helper functions and rendering utilities

classes/service/
  AI providers, prompt builders, workflow services and RAG services

assets/
  CSS and JavaScript resources

db/
  Moodle install and upgrade definitions
```

## Main idea

The public Moodle pages remain stable, while the AI-related logic is moved into reusable services. This improves maintainability, extensibility and portability across different AI providers.
