# Release notes

## 1.0.6

- Companion block 1.0.5 now shows student, reporting, material-management and Course Builder links according to their actual capabilities. It requires local plugin 1.0.6.

- Keep practice-quiz answer keys on the server, bind submissions to their user/course/session and prevent duplicate results on refresh. Reject invalid AI answer keys instead of silently choosing the first option.
- Apply live Moodle activity visibility and availability restrictions to material selection, retrieval and knowledge-graph evidence. Filter inaccessible chunks before selecting search results.
- Apply separate-group restrictions consistently to plugin attempts, assessment reports, learning gaps and Tutor Analytics.
- Require write capabilities, POST and session keys for state changes. Keep read-only teacher access separate from assessment, material and activity administration.
- Preserve assessment attempts when editing metadata; lock questions after an attempt. Serialise editing and grading, and reject submissions from an outdated question revision.
- Escape GIFT question syntax and neutralise formula-like cells in CSV exports.
- Queue course-resource extraction/indexing through Moodle cron, deduplicate queued work and prevent concurrent synchronisation of the same course. Replace each search index atomically so a failed insert retains its previous contents.
- Keep OCR preferences scoped to the course; remove course data and derived graph evidence through Moodle lifecycle/privacy operations.
- Correct capability language keys and full-name queries, and remove an empty deprecated footer callback that triggered Moodle debugging warnings.
- Add database regression tests, upgrade tests and browser workflows; expand CI to Moodle 4.4/4.5 with PostgreSQL and MySQL.

## 1.0.5

- Rename legacy database tables to the `local_aiskillnavigator_` prefix during Moodle upgrade. The migration preserves IDs, records and relationships, resumes after interruption and refuses conflicting destination tables. Update any custom SQL reports using the old prefixes.
- Make generation timeouts configurable for every HTTP AI provider and distinguish provider failures from malformed assessment JSON.
- Respect the learner's response language across tutor modes; remove conflicting Italian-only instructions.
- Record successful tutor interactions on the server and explain how Tutor Analytics is populated.
- Include scored, finished native Moodle quiz attempts in the Teacher dashboard while respecting report permissions and separate groups.
- Preserve material approval through sync, use source module IDs for renamed materials, and treat remote Ollama endpoints as external.
- Fix legacy source metadata backfill and prevent Enter in the Simulator Finder material filter from submitting the generation form.
- Label fallback mind maps as generic templates and show actionable guidance when the AI returns unusable output.
- Fix apostrophe escaping in generated video suggestion scripts; check embedded JavaScript after PHP rendering.
- Remove invalid empty CHAR defaults from the install schema so Moodle test installation runs without XMLDB warnings.
- Add Moodle database integration tests for native quiz reporting, capabilities and separate groups; resolve Moodle coding-standard violations.
- Replace promotional ranking/contribution content with installation, configuration and troubleshooting guidance; retire automatic micro-contribution merging and generated source-rewrite workflows.
- Build and verify separate local/block Moodle ZIPs with reproducible contents.

## 1.0.4 - Final runtime hardening

Main improvements:

- Fixed automatic RAG indexing after course-resource synchronisation.
- Added keyword-only embedding fallback for prototype and unsupported chat providers.
- Prevented external embedding calls unless site and material policies allow them.
- Added graceful handling when PHP cURL is unavailable.
- Fixed CLI duplicate-cleanup counters and stale resource cleanup after module deletion.
- Reconciled legacy database fields and indexes with the current XMLDB schema.
- Removed UTF-8 BOM from PHP entry points and corrected visible mojibake strings.
- Declared the block plugin dependency on the local plugin.
- Added automated PHP, XMLDB, JavaScript, BOM, and RAG contract checks.

## 1.0.3 - Marketplace hardening version

Main improvements:

- Stable Moodle local plugin and block plugin metadata.
- Course-aware AI Tutor, Quiz Generator, Mind Map Generator and Simulator Finder.
- Teacher dashboards, assessments, learning-gap analysis and adaptive review.
- RAG/material management with per-material external AI approval.
- Privacy API implementation for stored plugin data.
- Production-safe defaults: external AI material use disabled, destructive Course Builder actions disabled, automatic block insertion disabled, automatic course resource sync disabled, external MathJax CDN disabled.
- Database schema managed through install.xml and upgrade.php, including knowledge graph tables.
- Development scripts and temporary cleanup files removed from plugin package.
