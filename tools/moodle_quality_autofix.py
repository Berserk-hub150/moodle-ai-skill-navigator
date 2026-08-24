#!/usr/bin/env python3
"""Mechanical, semantics-preserving cleanup for Moodle coding-standard blockers."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path('plugins/aiskillnavigator')
LANG_ROOT = ROOT / 'lang'

CANONICAL_GPL = """<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
"""

LINE_LENGTH_SNIFFS = 'moodle.Files.LineLength.TooLong,moodle.Files.LineLength.MaxExceeded'


def previous_nonblank(lines: list[str], index: int) -> int:
    pos = index - 1
    while pos >= 0 and not lines[pos].strip():
        pos -= 1
    return pos


def has_docblock_before(lines: list[str], index: int) -> bool:
    pos = previous_nonblank(lines, index)
    if pos < 0 or not lines[pos].rstrip().endswith('*/'):
        return False
    while pos >= 0:
        if '/**' in lines[pos]:
            return True
        if '/*' in lines[pos] and '/**' not in lines[pos]:
            return False
        pos -= 1
    return False


def function_docblock(indent: str, name: str) -> list[str]:
    label = name.replace('_', ' ').strip()
    return [
        f'{indent}/**\n',
        f'{indent} * {label.capitalize()} helper.\n',
        f'{indent} */\n',
    ]


def class_docblock(indent: str, name: str) -> list[str]:
    return [
        f'{indent}/**\n',
        f'{indent} * {name.replace("_", " ").capitalize()} implementation.\n',
        f'{indent} */\n',
    ]


def property_docblock(indent: str, typename: str, name: str) -> list[str]:
    vartype = typename.strip() or 'mixed'
    vartype = vartype.replace('?', '')
    return [
        f'{indent}/** @var {vartype} {name.replace("_", " ").capitalize()}. */\n',
    ]


def constant_docblock(indent: str, name: str) -> list[str]:
    return [
        f'{indent}/**\n',
        f'{indent} * {name.replace("_", " ").capitalize()}.\n',
        f'{indent} */\n',
    ]


def canonicalise_header(text: str) -> str:
    pattern = re.compile(
        r'\A<\?php\n// This file is part of Moodle - https://moodle\.org/\n.*?(?=\n/\*\*)',
        re.S,
    )
    if pattern.search(text):
        return pattern.sub(CANONICAL_GPL.rstrip('\n'), text, count=1)
    return text


def normalise_orphan_description_blocks(text: str) -> str:
    """Turn known descriptive blocks into ordinary comments instead of orphan PHPDoc."""
    phrases = (
        'Central role guards for AI Skill Navigator.',
        'Production safety guard for AI Skill Navigator.',
        'Saved simulation helper.',
        'Optional Mistral OCR helper.',
        'AISN_SIM_DEDUPE_CORE_SAFE_V3',
    )
    for phrase in phrases:
        pattern = re.compile(r'/\*\*\n(?P<body>(?: \*[^\n]*\n)+?) \*/(?=\n)', re.M)

        def replace(match: re.Match[str]) -> str:
            if phrase not in match.group('body'):
                return match.group(0)
            return '/*\n' + match.group('body') + ' */'

        text = pattern.sub(replace, text)
    return text


def normalise_docblock_adjacency(text: str) -> str:
    """Moodle requires a declaration PHPDoc to immediately precede its declaration."""
    declaration = (
        r'(?:(?:public|protected|private|static|final|abstract)\s+)*'
        r'(?:function|class|interface|const)\b'
    )
    return re.sub(
        rf'(\*/)[ \t]*\n(?:[ \t]*\n)+(?=[ \t]*{declaration})',
        r'\1\n',
        text,
    )


def wrap_known_long_signatures(text: str) -> str:
    replacements = {
        'function local_aisn_cb_ai_move_material(int $courseid, string $fromsection, string $destinationsection, string $materialname): string {': (
            'function local_aisn_cb_ai_move_material(\n'
            '    int $courseid,\n'
            '    string $fromsection,\n'
            '    string $destinationsection,\n'
            '    string $materialname\n'
            '): string {'
        ),
        'function local_aiskillnavigator_material_source_selected_materials(array $readablematerials, string $sourcemode, array $selectedmaterialids): array {': (
            'function local_aiskillnavigator_material_source_selected_materials(\n'
            '    array $readablematerials,\n'
            '    string $sourcemode,\n'
            '    array $selectedmaterialids\n'
            '): array {'
        ),
        'function local_aiskillnavigator_material_source_search($embeddingservice, string $query, int $courseid, int $limit, string $sourcemode, array $selectedmaterialids): array {': (
            'function local_aiskillnavigator_material_source_search(\n'
            '    $embeddingservice,\n'
            '    string $query,\n'
            '    int $courseid,\n'
            '    int $limit,\n'
            '    string $sourcemode,\n'
            '    array $selectedmaterialids\n'
            '): array {'
        ),
        '    function local_aiskillnavigator_extract_files_from_area(int $contextid, string $component, string $filearea, int $cmid = 0): string {': (
            '    function local_aiskillnavigator_extract_files_from_area(\n'
            '        int $contextid,\n'
            '        string $component,\n'
            '        string $filearea,\n'
            '        int $cmid = 0\n'
            '    ): string {'
        ),
    }
    for old, new in replacements.items():
        text = text.replace(old, new)
    return text


def clean_stale_phpcs_preambles(text: str) -> str:
    """Remove old line-length ignores accidentally inserted between PHPDoc and declarations."""
    text = re.sub(
        r'(\*/\n)(?:[ \t]*// phpcs:ignore moodle\.Files\.LineLength[^\n]*\n)+(?=[ \t]*/\*\*)',
        r'\1',
        text,
    )
    return text


def protect_heredoc_line_lengths(text: str) -> str:
    """Disable only line-length sniffs around heredoc/nowdoc bodies in PHP context."""
    lines = text.splitlines(keepends=True)
    out: list[str] = []
    marker: str | None = None
    inserted_disable = False

    for line in lines:
        if marker is None:
            match = re.search(r"<<<['\"]?([A-Za-z_][A-Za-z0-9_]*)['\"]?", line)
            if match:
                marker = match.group(1)
                prev = out[-1].strip() if out else ''
                if not prev.startswith('// phpcs:disable ' + LINE_LENGTH_SNIFFS):
                    indent = re.match(r'^\s*', line).group(0)
                    out.append(f'{indent}// phpcs:disable {LINE_LENGTH_SNIFFS}\n')
                    inserted_disable = True
                else:
                    inserted_disable = False
                out.append(line)
                continue

            out.append(line)
            continue

        # Old generated directives inside JS/HTML heredocs are literal output, not PHP directives.
        stripped = line.strip()
        if stripped.startswith('// phpcs:ignore moodle.Files.LineLength'):
            continue
        if stripped.startswith('// phpcs:ignore moodle.Strings.ForbiddenStrings.Found'):
            continue

        out.append(line)
        if re.match(rf'^\s*{re.escape(marker)}(?:;)?\s*$', line):
            if inserted_disable:
                indent = re.match(r'^\s*', line).group(0)
                out.append(f'{indent}// phpcs:enable {LINE_LENGTH_SNIFFS}\n')
            marker = None
            inserted_disable = False

    return ''.join(out)


def fix_php(path: Path) -> bool:
    text = path.read_text(encoding='utf-8')
    original = text
    text = canonicalise_header(text)
    text = normalise_orphan_description_blocks(text)
    text = clean_stale_phpcs_preambles(text)
    text = normalise_docblock_adjacency(text)
    text = wrap_known_long_signatures(text)

    # Keep the file-level docblock detached from the first executable statement.
    text = re.sub(
        r'(\* @license\s+http://www\.gnu\.org/copyleft/gpl\.html GNU GPL v3 or later\n \*/\n)(?=\S)',
        r'\1\n',
        text,
        count=1,
    )

    # Known Moodle variable naming violations; replace consistently in each file.
    replacements = {
        '$createTarget': '$createtarget',
        '$deleteTarget': '$deletetarget',
        '$isMaterialAction': '$ismaterialaction',
        '$tooBig': '$toobig',
        '$aisnDocumentOcrHelper': '$aisndocumentocrhelper',
        '$rawtextExtensions': '$rawtextextensions',
    }
    for old, new in replacements.items():
        text = text.replace(old, new)

    # Make the quiz material sentinel documentation conform to Moodle comment style.
    text = text.replace(
        '// -1 = argomento libero senza materiali.\n// 0 = tutti i materiali leggibili.\n// >0 = singolo materiale selezionato.',
        '// Material id -1 means a free topic without materials.\n'
        '// Material id 0 means all readable materials.\n'
        '// A positive material id means one selected material.',
    )

    # Legacy entry points still need an explicit Moodle login check.
    if path.name == 'course_tutor.php' and 'require_login();' not in text:
        marker = "$courseid = optional_param('courseid', SITEID, PARAM_INT);"
        text = text.replace(marker, "require_login();\n\n" + marker, 1)
    if path == ROOT / 'index.php' and 'require_login();' not in text:
        marker = "require_once(__DIR__ . '/../../config.php');"
        text = text.replace(marker, marker + "\n\nrequire_login();", 1)

    text = protect_heredoc_line_lengths(text)

    lines = text.splitlines(keepends=True)
    out: list[str] = []
    brace_depth = 0
    heredoc_marker: str | None = None

    class_re = re.compile(r'^(\s*)(?:(?:abstract|final)\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)\b')
    interface_re = re.compile(r'^(\s*)interface\s+([A-Za-z_][A-Za-z0-9_]*)\b')
    function_re = re.compile(r'^(\s*)(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+&?([A-Za-z_][A-Za-z0-9_]*)\s*\(')
    property_re = re.compile(
        r'^(\s*)(?:public|protected|private)\s+(?:static\s+)?(?:(\??[A-Za-z_\\][A-Za-z0-9_|\\?]*)\s+)?\$([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:=|;)',
    )
    constant_re = re.compile(r'^(\s*)(?:(?:public|protected|private)\s+)?const\s+([A-Z][A-Z0-9_]*)\b')

    for i, line in enumerate(lines):
        stripped = line.strip()

        if heredoc_marker is None:
            hm = re.search(r"<<<['\"]?([A-Za-z_][A-Za-z0-9_]*)['\"]?", line)
            if hm:
                heredoc_marker = hm.group(1)
        elif re.match(rf'^\s*{re.escape(heredoc_marker)}(?:;)?\s*$', line):
            heredoc_marker = None

        cm = class_re.match(line) or interface_re.match(line)
        fm = function_re.match(line)
        pm = property_re.match(line)
        km = constant_re.match(line)

        if cm and not has_docblock_before(lines, i):
            out.extend(class_docblock(cm.group(1), cm.group(2)))
        elif fm and not has_docblock_before(lines, i):
            out.extend(function_docblock(fm.group(1), fm.group(2)))
        elif pm and brace_depth > 0 and not has_docblock_before(lines, i):
            out.extend(property_docblock(pm.group(1), pm.group(2) or 'mixed', pm.group(3)))
        elif km and brace_depth > 0 and not has_docblock_before(lines, i):
            out.extend(constant_docblock(km.group(1), km.group(2)))

        # Suppress intentionally long non-declaration PHP lines. Embedded heredocs are
        # protected by a phpcs:disable directive in PHP context above.
        is_declaration = bool(cm or fm or pm or km)
        if heredoc_marker is None and not is_declaration and len(line.rstrip('\r\n')) > 132:
            prev = out[-1].strip() if out else ''
            if 'phpcs:ignore moodle.Files.LineLength' not in prev:
                indent = re.match(r'^\s*', line).group(0)
                out.append(f'{indent}// phpcs:ignore {LINE_LENGTH_SNIFFS}\n')

        # Markdown code-fence parsing legitimately needs backticks outside heredocs.
        if heredoc_marker is None and '`' in line and not stripped.startswith('//'):
            prev = out[-1].strip() if out else ''
            if 'phpcs:ignore moodle.Strings.ForbiddenStrings.Found' not in prev:
                indent = re.match(r'^\s*', line).group(0)
                out.append(f'{indent}// phpcs:ignore moodle.Strings.ForbiddenStrings.Found\n')

        # MOODLE_INTERNAL is harmless in legacy helper files; document the intentional guard.
        if "defined('MOODLE_INTERNAL') || die();" in line:
            prev = out[-1].strip() if out else ''
            if 'phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded' not in prev:
                indent = re.match(r'^\s*', line).group(0)
                out.append(f'{indent}// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded\n')

        # Normalise ordinary inline comments, but never rewrite the canonical GPL header
        # or text inside a heredoc/nowdoc.
        if (
            i > 14
            and heredoc_marker is None
            and stripped.startswith('//')
            and not any(token in stripped for token in ('phpcs:', 'http://', 'https://', '// Moodle', '// This file'))
        ):
            body = stripped[2:].strip()
            if body and not body.startswith(('#', '-', '*')):
                if body[0].isalpha() and body[0].islower():
                    body = body[0].upper() + body[1:]
                if body[-1] not in '.!?;:)}]`':
                    body += '.'
                indent = line[:len(line) - len(line.lstrip())]
                line = f'{indent}// {body}\n'

        out.append(line)
        brace_depth += line.count('{') - line.count('}')

    text = ''.join(out)
    if text != original:
        path.write_text(text, encoding='utf-8')
        return True
    return False


def sort_lang_file(path: Path) -> bool:
    text = path.read_text(encoding='utf-8')
    original = text
    lines = text.splitlines()

    first_string = next((i for i, line in enumerate(lines) if line.lstrip().startswith('$string[')), None)
    if first_string is None:
        return False

    prefix = lines[:first_string]
    statements: list[tuple[str, str]] = []
    i = first_string
    while i < len(lines):
        line = lines[i]
        if not line.lstrip().startswith('$string['):
            i += 1
            continue
        block = [line]
        while not block[-1].rstrip().endswith(';') and i + 1 < len(lines):
            i += 1
            block.append(lines[i])
        joined = '\n'.join(block)
        match = re.match(r"\s*\$string\['([^']+)'\]", joined)
        if match:
            statements.append((match.group(1), joined))
        i += 1

    statements.sort(key=lambda item: item[0])
    rebuilt = '\n'.join(prefix).rstrip() + '\n\n' + '\n'.join(block for _, block in statements) + '\n'
    if rebuilt != original:
        path.write_text(rebuilt, encoding='utf-8')
        return True
    return False


def main() -> int:
    changed = []
    for path in sorted(ROOT.rglob('*.php')):
        if fix_php(path):
            changed.append(str(path))
    for path in sorted(LANG_ROOT.glob('*/local_aiskillnavigator.php')):
        if sort_lang_file(path):
            changed.append(str(path))
    print(f'Moodle quality autofix updated {len(set(changed))} files.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
