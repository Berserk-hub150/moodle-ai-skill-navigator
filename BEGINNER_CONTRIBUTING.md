# Beginner Contributing Guide

<!-- MICRO-GUIDE-START -->

## Fastest first contribution: 2–5 minutes

For the smallest browser-only contribution:

1. Open the issues labelled `micro-contribution`.
2. Pick one unassigned task.
3. Fork the repository.
4. Create the exact JSON file requested by the issue using GitHub's web interface.
5. Replace the placeholder with your own concise content.
6. Commit in your fork.
7. Open a Pull Request with `Closes #ISSUE_NUMBER`.

No Moodle installation, PHP setup or local clone is needed for these tasks.

A star is appreciated if the project is useful to you, but it is never required for review or merge.

See `community/micro-contributions/README.md` for the content rules.

<!-- MICRO-GUIDE-END -->

Welcome to AI Skill Navigator.

This guide is designed for people making their first open-source contribution. You do not need to be an expert in Moodle, PHP, JavaScript, AI, or Git.

## Start here

1. Star our repo.
2. Fork the repository.
3. Pick an open issue labelled good first issue.
4. Comment on the issue if you want to work on it.
5. Make the requested change.
6. Open a Pull Request that links the issue.

The maintainer workflow does not automatically verify stars. Pull Requests are reviewed on the submitted change.

## Browser-only contribution

Some documentation, translation, and Flashcards content tasks can be completed from GitHub without installing Moodle.

1. Open the file linked from the issue.
2. Click the edit button.
3. GitHub can create a fork for you.
4. Make the requested change.
5. Commit it to your fork.
6. Open a Pull Request.
7. Put Closes #ISSUE_NUMBER in the PR description.

## Local contribution

### Fork

Use the Fork button on GitHub.

### Clone

    git clone https://github.com/YOUR_USERNAME/moodle-ai-skill-navigator.git
    cd moodle-ai-skill-navigator

### Create a branch

    git checkout -b fix/short-description

### Make the change

Keep the change focused on the selected issue.

### Validate PHP

    php -l path/to/changed-file.php

### Validate JavaScript

    node --check path/to/changed-file.js

See CONTRIBUTING.md for the full repository validation commands.

### Commit

    git add .
    git commit -m "fix: short description"

### Push

    git push -u origin HEAD

### Pull Request

Open a Pull Request and include:

    Closes #ISSUE_NUMBER

## Rules

- Prefer one issue per Pull Request.
- Do not add unrelated refactors.
- Never commit API keys or credentials.
- Never commit real student data.
- Never upload screenshots, fixtures, logs, or decks containing private course material.
- Ask questions in the issue if anything is unclear.

## Maintainer goal

Small beginner Pull Requests should be reviewed quickly when possible.
