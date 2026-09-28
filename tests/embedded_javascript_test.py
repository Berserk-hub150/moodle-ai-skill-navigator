#!/usr/bin/env python3
"""Check JavaScript after PHP decodes embedded heredoc and nowdoc scripts."""

import argparse
import re
import subprocess
from pathlib import Path


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", default="php")
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    docs = re.compile(r"<<<(['\"]?)(\w+)\1\n(.*?)\n\2\b", re.S)
    scripts = re.compile(r"<script\b[^>]*>(.*?)</script>", re.S | re.I)
    checked = 0
    for path in sorted((root / "plugins/aiskillnavigator").rglob("*.php")):
        for doc in docs.finditer(path.read_text(encoding="utf-8")):
            for script in scripts.findall(doc[3]):
                # Runtime values are irrelevant to syntax; keep a valid JS literal.
                script = re.sub(r"\{\$\w+\}", "null", script)
                if doc[1] != "'":
                    # Compile only the string expression, without running the page.
                    code = "<?php\nerror_reporting(E_ALL);\necho <<<AISNTESTSCRIPT\n"
                    code += script + "\nAISNTESTSCRIPT;\n"
                    result = subprocess.run(
                        [args.php], input=code, text=True, capture_output=True, check=True
                    )
                    if result.stderr:
                        raise RuntimeError(f"{path}: {result.stderr}")
                    script = result.stdout
                result = subprocess.run(
                    ["node", "--check", "-"], input=script, text=True, capture_output=True
                )
                if result.returncode:
                    raise RuntimeError(f"{path}: {result.stderr}")
                checked += 1
    if not checked:
        raise RuntimeError("No embedded scripts found; check the source extraction")
    print(f"embedded_javascript_test: {checked} rendered scripts passed")


if __name__ == "__main__":
    main()
