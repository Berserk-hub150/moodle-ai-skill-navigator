#!/usr/bin/env python3
"""Move simple Moodle page titles/headings into the English language pack."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path("plugins/aiskillnavigator")
LANG = ROOT / "lang/en/local_aiskillnavigator.php"
COMPONENT = "local_aiskillnavigator"


def php_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


def main() -> int:
    strings: dict[str, str] = {}
    changed: list[Path] = []

    patterns = [
        ("title", re.compile(r"\$PAGE->set_title\(\s*'([^'\n]+)'\s*\);")),
        ("heading", re.compile(r"\$PAGE->set_heading\(\s*'([^'\n]+)'\s*\);")),
    ]

    for path in sorted((ROOT / "pages").glob("*.php")):
        text = path.read_text(encoding="utf-8")
        original = text
        stem = re.sub(r"[^a-z0-9]+", "_", path.stem.lower()).strip("_")

        for kind, pattern in patterns:
            matches = list(pattern.finditer(text))
            for index, match in enumerate(matches, start=1):
                value = match.group(1)
                suffix = "" if len(matches) == 1 else f"_{index}"
                key = f"page_{stem}_{kind}{suffix}"
                strings[key] = value
                method = "set_title" if kind == "title" else "set_heading"
                old = match.group(0)
                new = f"$PAGE->{method}(get_string('{key}', '{COMPONENT}'));"
                text = text.replace(old, new, 1)

        if path.name == "simulator_finder.php":
            text = text.replace(
                "// Do not rely only on $_POST/$_REQUEST mutation, because $notes may already be read.",
                "// Do not rely on request-superglobal mutation, because $notes may already be read.",
            )

        if path.name == "teacher_assessments.php":
            text = text.replace(
                "// Qui leggiamo $_POST in modo controllato e puliamo solo valori scalari con clean_param().",
                "// Read nested submitted data via data_submitted() and clean scalar values with clean_param().",
            )

        if text != original:
            path.write_text(text, encoding="utf-8")
            changed.append(path)

    lang = LANG.read_text(encoding="utf-8")
    additions: list[str] = []
    for key, value in sorted(strings.items()):
        if f"$string['{key}']" not in lang:
            additions.append(f"$string['{key}'] = '{php_escape(value)}';")

    if additions:
        lang = lang.rstrip() + "\n\n// Moodle page titles and headings.\n" + "\n".join(additions) + "\n"
        LANG.write_text(lang, encoding="utf-8")
        changed.append(LANG)

    print(f"Localised page chrome in {len(set(changed))} files.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
