# Manual test checklist

Use this checklist before a thesis demo or a marketplace release test.

## General checks

- Start Docker containers.
- Run Moodle upgrade.
- Run Moodle cache purge.
- Run PHP lint checks.
- Open the plugin dashboard.

## Teacher tools

- Open AI Course Builder.
- Create a section.
- Try a destructive action while destructive mode is disabled and verify it is blocked.
- Open Course Materials / RAG.
- Open Teacher Dashboard.
- Open Tutor Analytics.
- Generate initial/final assessments.
- Export CSV and Google Forms CSV.

## Student tools

- Open AI Tutor.
- Ask a course-aware question.
- Generate and complete an AI quiz.
- Generate a mind map.
- Open adaptive review after quiz/assessment data exists.

## Simulator workflow

- Open AI Simulator Finder.
- Generate a simulator suggestion.
- Save the simulation.
- Open Saved simulations.
- Check that the saved simulation is not duplicated.
- Open the simulation detail page.
- Check that links are clickable.

## Privacy and production checks

- Verify that external AI material usage is disabled by default.
- Verify that each Moodle material has an explicit AI access policy.
- Verify that Privacy API classes are present.
- Verify that no .bak, backup, zip, tar.gz, env, log or development scripts are included in plugin folders.