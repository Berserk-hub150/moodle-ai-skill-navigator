# Contributing

Contributions should improve a working Moodle feature, resolve a reproducible defect, or clarify installation and use.

## Development workflow

1. Fork the repository and create a branch from `main`.
2. Read the relevant issue and reproduce the behaviour in a disposable Moodle course.
3. Keep the change focused. Preserve capability checks, material permissions and conservative defaults.
4. Add a regression test for a runtime defect and describe what fails before the fix.
5. Open a pull request explaining the user impact, changes and validation results.

No Moodle installation is needed for documentation changes or the standalone regression suite. Runtime changes also need a Moodle installation; copy the components to the paths in the [README](README.md).

## Local validation

Use PHP 8.1 or later with cURL, DOM, mbstring, PDO SQLite, SimpleXML and ZIP, plus Node.js and Python 3.

```bash
find plugins tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
for test in tests/*_test.php; do php "$test" || exit 1; done
find plugins -type f -name '*.js' -print0 | xargs -0 -n1 node --check
node tests/simulator_material_filter_test.js
python3 tests/embedded_javascript_test.py
python3 tools/build_packages.py
```

On PowerShell, run each test with `Get-ChildItem tests/*_test.php | ForEach-Object { php $_.FullName; if ($LASTEXITCODE) { throw 'Test failed' } }`.

The standalone tests use controlled doubles for Moodle services and a SQLite fixture for dashboard queries. The embedded-script check renders PHP string literals before checking JavaScript syntax. Moodle Marketplace CI also runs the plugin's PHPUnit tests on Moodle 4.5 with PostgreSQL, including native quiz reports, capabilities and separate groups. Run the Moodle upgrade and the [manual checklist](docs/manual-test-checklist.md) for provider and browser behaviour.

## Issue triage

Keep an issue open when it describes a reproducible bug, a useful improvement with acceptance criteria, or a question that needs an answer. Before working, check for an existing pull request.

Close empty reports, duplicates and abandoned promotional tasks with an accurate reason. Do not mark a real defect fixed merely because it cannot be reproduced. Consolidate dependent feature subtasks into a complete, testable feature proposal; do not add navigation to a page that does not exist.

Use the bug report template for defects and the improvement template for scoped changes. Contributions are reviewed for correctness, usefulness and maintainability.

## Security and data

Never include credentials, API keys, private course materials or real student records in changes, tests, screenshots or logs. Follow [SECURITY.md](SECURITY.md) for private vulnerability reports. AI material use must remain opt-in globally and per material, and protected actions must require Moodle capabilities and sesskey validation.
