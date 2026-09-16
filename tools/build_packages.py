#!/usr/bin/env python3
"""Build separate, reproducible Moodle component archives from this repository."""
from pathlib import Path
import argparse
import re
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]
COMPONENTS = {
    "local_aiskillnavigator": ROOT / "plugins/aiskillnavigator",
    "block_aiskillnavigator": ROOT / "plugins/block_aiskillnavigator",
}


def build(output: Path) -> None:
    output.mkdir(parents=True, exist_ok=True)
    for component, source in COMPONENTS.items():
        version = (source / "version.php").read_text(encoding="utf-8-sig")
        match = re.search(r"\$plugin->release\s*=\s*'([a-zA-Z0-9._-]+)'", version)
        if not match:
            raise ValueError(f"Missing or invalid release in {source}/version.php")
        target = output / f"{component}-{match[1]}.zip"
        with ZipFile(target, "w", compression=ZIP_DEFLATED) as archive:
            for path in sorted(source.rglob("*")):
                if not path.is_file() or path.is_symlink():
                    continue
                relative = path.relative_to(source)
                if any(part.startswith(".") or part in {"tests", "node_modules", "vendor", "__pycache__"} for part in relative.parts):
                    continue
                if path.suffix in {".zip", ".log", ".pyc"} or ".bak" in path.name:
                    continue
                info = ZipInfo("aiskillnavigator/" + relative.as_posix(), date_time=(1980, 1, 1, 0, 0, 0))
                info.compress_type = ZIP_DEFLATED
                info.external_attr = 0o100644 << 16
                archive.writestr(info, path.read_bytes())
        with ZipFile(target) as archive:
            names = set(archive.namelist())
            required = {"aiskillnavigator/version.php", f"aiskillnavigator/lang/en/{component}.php"}
            required.add("aiskillnavigator/db/install.xml" if component.startswith("local_") else "aiskillnavigator/block_aiskillnavigator.php")
            if not required <= names or any(not n.startswith("aiskillnavigator/") for n in names):
                raise ValueError(f"Invalid Moodle package: {target}")
            if archive.testzip() is not None:
                raise ValueError(f"Corrupt ZIP: {target}")
        print(f"{target.name}: {len(names)} files, verified")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=ROOT / "dist")
    build(parser.parse_args().output)
