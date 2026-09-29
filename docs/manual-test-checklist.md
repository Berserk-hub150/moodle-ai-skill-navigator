# Manual test checklist

Use a disposable Moodle site and test accounts before a release. Record the Moodle/PHP versions and provider used.

## General checks

- Start the disposable Moodle site and its database.
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

## Reliability regressions (1.0.5)

- Ask the tutor in English, Italian and another language in both manual and selected-material modes. Repeat with source materials in a different language; the response should follow the question or explicit language request.
- Disable browser JavaScript, submit a tutor question, and verify one signal appears in Tutor Analytics. A failed provider response must not create a signal.
- Configure a generation timeout of 180 seconds; verify with a deliberately delayed test endpoint and check web-server/proxy limits separately.
- Finish a normal Moodle quiz and confirm it appears alongside plugin attempts. Exclude previews, unscored attempts and attempts in another course. Test a non-editing teacher in separate groups and a user without quiz reporting permission.
- Rename a material with a source module ID, allow external AI, resynchronise, and verify the policy remains. Revoke it and verify that a later sync cannot restore the old approval.
- Configure Ollama with a remote HTTPS endpoint and verify that unapproved materials cannot be sent.
- Type part of a material title in Simulator Finder, press Enter, and verify that the list filters without submitting generation. Clear the filter and verify that all rows return.
- Complete a quiz with an incorrect answer and inspect video suggestions. Confirm that the browser console has no syntax errors and the suggestion links render correctly.
- Install each generated ZIP in its own Moodle plugin directory and run the upgrade from 1.0.4.

## Access and integrity regressions (1.0.6)

- Generate a practice quiz as a student; confirm the submitted form contains an opaque token rather than the answer key. Submit, refresh and verify one attempt is stored. A token from another user or course must fail.
- Restrict a source activity by visibility, future availability and group. Verify that another student's material list, retrieved tutor context and knowledge graph contain no content from that activity.
- In separate groups, compare quiz, assessment, gap and tutor reports for teachers in different groups and a teacher allowed to access all groups.
- Give a teacher report access without write capabilities. Verify they can read permitted reports but cannot create, edit, delete or publish assessments, delete resources, or build course activities. A GET request to a write action must fail.
- Open an assessment as a student, change its questions as a teacher before submission, then submit the stale student form. It must request a reload without saving a grade. After a valid attempt, changing questions must be refused while metadata changes preserve results.
- Export a question containing braces, equals signs, backslashes, newlines and accented characters to GIFT and import it into Moodle's question bank. Check text and answer weights. Open a CSV containing formula-like text and verify it remains text.
- Queue material sync twice, run cron and verify there is no duplicated material. Opening the materials page must not start extraction or AI calls. Revoke the requesting teacher's permission before a queued task runs and verify that it stops.
- Change OCR preference in one course and verify that other courses and site-wide provider settings stay unchanged.
- Delete a test course and use the Privacy API to delete a material owner's data in another course. Verify that related attempts, chunks and graph evidence are removed, while unrelated users' data remains.
