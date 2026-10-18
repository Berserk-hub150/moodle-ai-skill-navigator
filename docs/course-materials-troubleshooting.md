# Troubleshooting missing course materials

Use this checklist when course materials do not appear in AI Skill Navigator or are unavailable to RAG features. Start with the non-destructive checks below before forcing a re-sync or changing provider settings.

## 1. Non-destructive checks first

1. Confirm that the Moodle course contains a visible file, page, URL, or other supported course resource.
2. Confirm that the affected user can access the course and the resource in Moodle itself.
3. Open the AI Skill Navigator course-materials view again and refresh the page. The plugin performs course-resource synchronization from its teacher and material flows.
4. Check whether the material is visible in the plugin but unavailable only to a specific AI workflow. If so, continue with the configuration checks before changing or re-uploading anything.
5. Do not copy private course content, student data, credentials, API keys, or full document contents into issues, logs, screenshots, or external troubleshooting tools.

## 2. Configuration checks

Open:

```text
Site administration > Plugins > Local plugins > AI Skill Navigator
```

Check the following settings:

- `Provider`: the default `prototype` provider is valid for first-run checks and performs no external AI calls.
- `Automatically sync course resources on Moodle events`: disabled by default. When it is disabled, do not assume that every Moodle resource change will be synchronized immediately by an event observer.
- `Approve external AI for teacher materials`: disabled by default. External providers must not receive teacher materials unless this site-level approval is enabled.
- Per-material approval is also required before a teacher material can be sent to an external provider.
- If an external embedding provider is configured, verify its endpoint/model/API-key settings in Moodle without exposing those values in screenshots or bug reports.

If the material is present in AI Skill Navigator but excluded from an external-provider workflow, review the site-level and per-material approval state instead of changing the source course resource.

## 3. Synchronization and indexing checks

If the configuration is correct but a Moodle course resource still does not appear, an administrator can run the repository's course-resource synchronization command from the Moodle root:

```text
php local/aiskillnavigator/cli/sync_course_resources.php --courseid=2 --userid=2
```

Replace the example IDs with the target course and a valid user. The command reports how many records were created, updated, or skipped.

Use `--force` only after the ordinary synchronization path has been checked:

```text
php local/aiskillnavigator/cli/sync_course_resources.php --courseid=2 --userid=2 --force
```

After synchronization, reload the course-materials view and retry the RAG workflow. If the material is listed but retrieval still fails, treat that as an indexing/embedding problem rather than a course-resource discovery problem. Review the embedding-provider configuration and application logs for errors, but redact secrets and private material before sharing diagnostics.

## 4. Information safe to include in a bug report

Useful diagnostic information includes:

- Moodle version and PHP version;
- AI Skill Navigator version/commit;
- affected course ID if it is safe to disclose internally;
- resource type (for example file, page, or URL);
- whether the resource is visible in Moodle;
- whether it appears in the AI Skill Navigator materials view;
- synchronization counts (`Created`, `Updated`, `Skipped`);
- sanitized error messages with credentials, tokens, student data, and course content removed.

Do not attach exported course materials, API keys, access tokens, student submissions, or screenshots containing private course content.
