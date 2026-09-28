# Deploying to a university Moodle site

Use a staging copy of the target site first. The automated suite exercises the combinations below; it does not establish compatibility with untested Moodle versions, custom role definitions, themes or external providers.

| Moodle | PHP | Database | Automated checks |
| --- | --- | --- | --- |
| 4.4 stable branch | 8.1 | PostgreSQL 17 | Installation, upgrade, database regressions, Moodle standards |
| 4.5 stable branch | 8.3 | PostgreSQL 17 | The same checks plus Chrome student/teacher workflows |
| 4.5 stable branch | 8.3 | MySQL 8.0 | Installation, upgrade, database regressions, Moodle standards |

The independent PHP suite also runs on PHP 8.2. Check the repository's Actions results for the exact Moodle patch release and commit used by each run. A tested plugin combination does not extend the upstream support lifecycle of Moodle, PHP or the database.

## Install and upgrade

1. Record the installed plugin version and target commit. Back up the Moodle database, `moodledata` and installed plugin code together; verify that the backup can be restored to staging.
2. Use the separate ZIP packages described in [MARKETPLACE.md](../MARKETPLACE.md), or replace `local/aiskillnavigator` with `plugins/aiskillnavigator`. Install the optional block separately in `blocks/aiskillnavigator`.
3. Run `php admin/cli/upgrade.php --non-interactive` and `php admin/cli/purge_caches.php` from the Moodle directory, under the site's normal service account.
4. For an upgrade from a version before 1.0.5, verify the migrated materials and attempts. Custom SQL reports need the `local_aiskillnavigator_` table prefix.
5. If rollback is needed, restore the matching database, files and plugin-code backup. Copying older PHP files over an upgraded database is not a database rollback.

## Materials and background work

Configure Moodle cron using the site's normal scheduler. A teacher presses **Synchronise course materials** in **Manage teacher materials** to enqueue extraction and indexing. With automatic event synchronisation enabled, activity changes enqueue the same task. Opening a material page does not reindex the course.

Verify the `local_aiskillnavigator\task\sync_course` ad hoc task completes. Moodle records task failures and retries thrown errors. A course lock prevents overlapping synchronisations, and replacing the chunks for one material uses a database transaction. Revoked teacher permissions stop queued work. Removing or restricting a source activity immediately excludes its stored content from learner retrieval.

For large courses, measure extraction and indexing duration with the actual file types, OCR service and embedding provider. Inspect failed task logs and the resulting material text before making the course available. Embeddings may fall back to keyword retrieval; inspect provider configuration if semantic retrieval is expected.

## Provider and access configuration

Start with `prototype` to check navigation without external requests. It produces fixed demonstration answers and is not an AI service. Configure the actual endpoint, model, credentials and timeout before provider acceptance testing. Keep credentials out of issue reports and logs shared outside the site.

External use of stored materials requires site approval and approval for each material. A remote Ollama endpoint counts as external. Text entered directly into a tool is submitted to the configured provider; material approval does not cover arbitrary pasted content.

Test with a student, an editing teacher and the institution's read-only teacher role. In courses with separate groups, test two teachers and students in different groups. Material visibility must follow Moodle activity availability, including future dates and group restrictions. Administrative write capabilities must not be granted merely to make a report visible.

Generated quizzes and assessments are learning aids. Teachers should review their questions, correct answers and explanations before publication. Practice quizzes use session-bound server-side answer keys; unfinished practice quizzes expire after two hours. Published assessment submissions are checked against the displayed question revision, and questions with existing attempts cannot be changed in place.

## Acceptance and operations

Run [the manual checklist](manual-test-checklist.md) with the target theme, authentication setup and real provider. Include provider errors, timeouts, representative documents, group boundaries, CSV/GIFT import and privacy deletion. For simultaneous classes, measure response latency and error rate at the expected concurrency; AI generation remains a synchronous web request and is also constrained by provider quotas and web-server timeouts.

Record the tested commit, environment, role configuration, provider/model, number of concurrent users and pass/fail evidence. Review failed cron tasks and PHP errors after deployment, and verify the backup/restore procedure before an upgrade. The automated suite uses deterministic prototype responses; it does not test the university's external model, network or production load.
