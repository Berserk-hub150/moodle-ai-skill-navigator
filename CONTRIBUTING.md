# Contributing

Thanks for improving AI Skill Navigator.

## Development flow

1. Create a branch from `main`.
2. Keep changes scoped to `local_aiskillnavigator`, `block_aiskillnavigator`, or their documentation.
3. Preserve the production-safe defaults documented in the README.
4. Add or update a regression test when fixing a runtime bug.
5. Open a pull request describing the root cause, user impact, and validation performed.

## Required checks

Run the checks available in your environment before opening a pull request:

```bash
find plugins tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/embedding_config_test.php
php tests/embedding_contract_test.php
php tests/rag_indexing_test.php
php tests/php_bom_test.php
php tests/xmldb_schema_test.php
find plugins -type f -name '*.js' -print0 | xargs -0 -n1 node --check
```

Also run the Moodle upgrade and the scenarios in `docs/manual-test-checklist.md` against a disposable test course.

## Security and privacy

- Never commit API keys, credentials, student data, or exported course materials.
- External AI access must remain opt-in globally and per material.
- Destructive Course Builder actions must remain disabled by default.
- Use Moodle capabilities, `require_login()`, and sesskey validation for every protected action.
