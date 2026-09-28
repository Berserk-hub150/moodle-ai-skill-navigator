# AI Skill Navigator — local plugin

Course-aware tutoring, quizzes, mind maps, assessments and teaching tools for Moodle.

Install this directory as `local/aiskillnavigator` and visit **Site administration → Notifications**. The optional `block_aiskillnavigator` component is distributed separately and installs as `blocks/aiskillnavigator`.

Minimum declared Moodle version: 4.4. The integration workflow targets Moodle 4.5; check the current release and deployment requirements before upgrading.

Configure the plugin under **Site administration → Plugins → Local plugins → AI Skill Navigator**. Start with the `prototype` provider for fixed demonstration responses. Real generation requires a configured provider and model. The generation timeout is configurable from 10 to 600 seconds.

Stored course materials require site-level and per-material approval before being sent to an external provider. Text typed directly into AI tools is sent to the chosen provider. Remote Ollama endpoints count as external. Destructive Course Builder actions and automatic synchronisation are disabled by default.

Full [installation and usage documentation](https://github.com/Berserk-hub150/moodle-ai-skill-navigator#readme), [bug reports](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/issues), and [release notes](https://github.com/Berserk-hub150/moodle-ai-skill-navigator/blob/main/docs/RELEASE_NOTES.md) are maintained in the repository.

Licensed under GNU GPL v3 or later. See LICENSE.
