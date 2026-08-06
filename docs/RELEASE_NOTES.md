# Release notes

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
