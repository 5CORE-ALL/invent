#!/usr/bin/env python3
"""Align Dil% row colors and Dil filters to Red <25, Green 25-50, Pink 50+."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path("/Users/shobhanishad/Desktop/5core/invent")
SKIP_DIR_NAMES = {
    "node_modules",
    "vendor",
    ".git",
    "storage",
    "bootstrap",
}
SKIP_FILE_PARTS = {
    "a-plus-images-master-old-backup.blade.php",
    "inv_by_sales.blade.php",
}

NEW_CHAIN = (
    "if (percent < 25) return 'red';\n"
    "{indent}if (percent >= 25 && percent < 50) return 'green';\n"
    "{indent}return 'pink';"
)

GET_DIL_OPEN = re.compile(
    r"(?:const getDilColor\s*=\s*\([^)]*\)\s*=>|function getDilColor\s*\([^)]*\))\s*\{"
)

COLOR_RULE_BLOCK = re.compile(
    r"(['\"][^'\"]*['\"]:\s*\{\s*ranges:\s*)(\[[^\]]+\])(\s*,\s*colors:\s*)(\[[^\]]+\])",
    re.MULTILINE,
)

LI_BLOCK = re.compile(r"<li\b[^>]*>.*?</li>", re.DOTALL | re.IGNORECASE)
OPTION_YELLOW = re.compile(
    r"\n?[ \t]*<option\b[^>]*value=[\"']yellow[\"'][^>]*>.*?</option>",
    re.IGNORECASE | re.DOTALL,
)
SELECT_BLOCK = re.compile(r"<select\b[^>]*>.*?</select>", re.DOTALL | re.IGNORECASE)


def should_skip(path: Path) -> bool:
    if path.suffix not in {".php", ".js", ".blade.php"} and ".blade.php" not in path.name:
        return True
    if any(part in SKIP_DIR_NAMES for part in path.parts):
        return True
    if path.name in SKIP_FILE_PARTS:
        return True
    return False


def replace_get_dil_color_chains(text: str) -> str:
    out = []
    pos = 0
    for m in GET_DIL_OPEN.finditer(text):
        # Skip dynamic campaign-rule helpers.
        lookahead = text[m.end() : m.end() + 400]
        if "currentDilRule" in lookahead or "bands[i].dil_max" in lookahead:
            continue
        brace_start = text.find("{", m.start())
        if brace_start < 0:
            continue
        i = brace_start
        depth = 0
        end = None
        while i < len(text):
            ch = text[i]
            if ch == "{":
                depth += 1
            elif ch == "}":
                depth -= 1
                if depth == 0:
                    end = i
                    break
            i += 1
        if end is None:
            continue
        body = text[brace_start : end + 1]
        new_body, changed = rewrite_dil_body(body)
        if not changed:
            continue
        out.append(text[pos:brace_start])
        out.append(new_body)
        pos = end + 1
    if not out:
        return text
    out.append(text[pos:])
    return "".join(out)


def rewrite_dil_body(body: str) -> tuple[str, bool]:
    # Standard 4-band and mistaken PFT-style Dil helpers.
    patterns = [
        re.compile(
            r"if \(percent < 16\.66\) return 'red';\s*"
            r"if \(percent >= 16\.66 && percent < 25\) return 'yellow';\s*"
            r"if \(percent >= 25 && percent < 50\) return 'green';\s*"
            r"return 'pink';(?: // 50 and above)?"
        ),
        re.compile(
            r"if \(percent < 12\.5\) return 'red';\s*"
            r"if \(percent >= 12\.5 && percent < 16\.66\) return 'yellow';\s*"
            r"if \(percent >= 16\.66 && percent < 25\) return 'blue';\s*"
            r"if \(percent >= 25 && percent < 50\) return 'green';\s*"
            r"return 'pink';(?: // 50 and above)?"
        ),
        re.compile(
            r"if \(percent < 10\) return 'red';\s*"
            r"if \(percent >= 10 && percent < 13\.33\) return 'yellow';\s*"
            r"if \(percent >= 13\.33 && percent < 20\) return 'blue';\s*"
            r"if \(percent >= 20 && percent < 40\) return 'green';\s*"
            r"return 'pink';(?: // 50 and above)?"
        ),
        re.compile(
            r"if \(percent < 16\.66\) \{\s*return 'red';\s*\}"
            r"(?:\s*else)?\s*if \(percent >= 16\.66 && percent < 25\) \{\s*return 'yellow';\s*\}"
            r"(?:\s*else)?\s*if \(percent >= 25 && percent < 50\) \{\s*return 'green';\s*\}"
            r"\s*(?:else\s*)?\{\s*return 'pink';\s*\}"
        ),
    ]
    indent_m = re.search(r"\n([ \t]+)if \(percent", body)
    indent = indent_m.group(1) if indent_m else "            "
    replacement = NEW_CHAIN.format(indent=indent)
    new_body = body
    changed = False
    for pat in patterns:
        if pat.search(new_body):
            new_body = pat.sub(replacement, new_body)
            changed = True
    return new_body, changed


def is_dil_key(key: str) -> bool:
    k = key.strip("'\" ").lower()
    return "dil" in k


def replace_dil_color_rules(text: str) -> str:
    def repl(m: re.Match) -> str:
        key = m.group(0).split(":")[0]
        if not is_dil_key(key):
            return m.group(0)
        ranges = m.group(2)
        colors = m.group(4)
        if "16.66" not in ranges and "12.5" not in ranges:
            # Already 3-band or custom; still force Dil to the shared slabs
            # only when the old 4/5-band color list is present.
            if "'yellow'" not in colors and '"yellow"' not in colors:
                return m.group(0)
        return (
            f"{m.group(1)}[25, 50]{m.group(3)}['red', 'green', 'pink']"
        )

    return COLOR_RULE_BLOCK.sub(repl, text)


def remove_dil_yellow_lis(text: str) -> str:
    def keep(m: re.Match) -> str:
        block = m.group(0)
        if 'data-color="yellow"' not in block and "data-color='yellow'" not in block:
            return block
        dilish = (
            re.search(r'data-column="[^"]*dil[^"]*"', block, re.I)
            or re.search(r"data-column='[^']*dil[^']*'", block, re.I)
            or re.search(r'class="[^"]*dil-item[^"]*"', block, re.I)
            or re.search(r"class='[^']*dil-item[^']*'", block, re.I)
        )
        if not dilish:
            return block
        return ""

    return LI_BLOCK.sub(keep, text)


def dil_select(select_html: str) -> bool:
    head = select_html[:400].lower()
    return any(
        token in head
        for token in (
            'id="dil-filter"',
            "id='dil-filter'",
            'id="dilfilter"',
            'id="dil-color-filter"',
            'id="dws-dil',
            "dil-filter",
            "dilfilter",
            "dil-color",
        )
    )


def update_dil_selects(text: str) -> str:
    def repl(m: re.Match) -> str:
        block = m.group(0)
        if not dil_select(block):
            return block
        # Update red labels and drop yellow band.
        block = re.sub(
            r"(Red(?:</span>)?\s*)(?:\(&lt;16\.(?:7|66)%\)|&lt;16\.(?:7|66)%|\(<16\.(?:7|66)%\))",
            r"\1(&lt;25%)",
            block,
            flags=re.I,
        )
        block = re.sub(
            r">Red &lt;16\.(?:7|66)%<",
            ">Red &lt;25%<",
            block,
        )
        block = OPTION_YELLOW.sub("", block)
        return block

    return SELECT_BLOCK.sub(repl, text)


def update_dil_filter_labels(text: str) -> str:
    text = text.replace("Red (&lt;16.7%)", "Red (&lt;25%)")
    text = text.replace("Red (&lt;16.66%)", "Red (&lt;25%)")
    text = text.replace("Red &lt;16.7%", "Red &lt;25%")
    text = text.replace("Red &lt;16.66%", "Red &lt;25%")
    return text


def update_inline_dil_hex(text: str) -> str:
    patterns = [
        (
            re.compile(
                r"if \(dil(?:Num)? < 16\.66\) color = '#a00211';(?: // red)?\s*"
                r"else if \(dil(?:Num)? >= 16\.66 && dil(?:Num)? < 25\) color = '#ffc107';(?: // yellow)?\s*"
                r"else if \(dil(?:Num)? >= 25 && dil(?:Num)? < 50\) color = '#28a745';(?: // green)?\s*"
                r"else (?:color = '#e83e8c';(?: // pink(?: - bold)?)?)"
            ),
            "if (dil < 25) color = '#dc3545';\n"
            "                            else if (dil >= 25 && dil < 50) color = '#28a745';\n"
            "                            else color = '#e83e8c';",
        ),
        (
            re.compile(
                r"if \(dil(?:Num)? < 16\.66\) color = '#a00211';\s*"
                r"else if \(dil(?:Num)? >= 16\.66 && dil(?:Num)? < 25\) color = '#ffc107';\s*"
                r"else if \(dil(?:Num)? >= 25 && dil(?:Num)? < 50\) color = '#28a745';\s*"
                r"else color = '#e83e8c';"
            ),
            "if (dil < 25) color = '#dc3545';\n"
            "                        else if (dil >= 25 && dil < 50) color = '#28a745';\n"
            "                        else color = '#e83e8c';",
        ),
        (
            re.compile(
                r"dil(?:Num)? < 16\.66 \? '#a00211' : dil(?:Num)? < 25 \? '#ffc107' : dil(?:Num)? < 50 \? '#28a745' : '#e83e8c'"
            ),
            "dil < 25 ? '#dc3545' : dil < 50 ? '#28a745' : '#e83e8c'",
        ),
        (
            re.compile(
                r"if \(dil < 16\.66\) \{\s*color = '#a00211';\s*\} "
                r"else if \(dil >= 16\.66 && dil < 25\) \{\s*color = '#ffc107';\s*\}"
            ),
            "if (dil < 25) { color = '#dc3545'; }",
        ),
    ]
    for pat, repl in patterns:
        text = pat.sub(repl, text)
    # Compact one-line ifs seen in some blades.
    text = re.sub(
        r"if \(dil < 16\.66\) color = '#a00211';\s*else if \(dil >= 16\.66 && dil < 25\) color = '#ffc107';",
        "if (dil < 25) color = '#dc3545';",
        text,
    )
    return text


def update_dil_filter_js(text: str) -> str:
    replacements = [
        (
            r"if \(dilFilter === 'red'\) return dil < 16\.66;\s*"
            r"if \(dilFilter === 'yellow'\) return dil >= 16\.66 && dil < 25;",
            "if (dilFilter === 'red') return dil < 25;",
        ),
        (
            r"if \(dilColor === 'red'\s*&& !\(dil < 16\.66\)\) return false;\s*"
            r"if \(dilColor === 'yellow'\s*&& !\(dil >= 16\.66 && dil < 25\)\) return false;",
            "if (dilColor === 'red'    && !(dil < 25)) return false;",
        ),
        (
            r"if \(dil === 'red'\) return d2 < 16\.66;\s*"
            r"if \(dil === 'yellow'\) return d2 >= 16\.66 && d2 < 25;",
            "if (dil === 'red') return d2 < 25;",
        ),
        (
            r"if \(dilVal === 'red'\) return dil < 16\.66;\s*"
            r"if \(dilVal === 'yellow'\) return dil >= 16\.66 && dil < 25;",
            "if (dilVal === 'red') return dil < 25;",
        ),
        (
            r"if \(colorFilter === 'red'\) return value < 16\.66;\s*"
            r"if \(colorFilter === 'yellow'\) return value >= 16\.66 && value < 25;",
            "if (colorFilter === 'red') return value < 25;",
        ),
    ]
    for pat, repl in replacements:
        text = re.sub(pat, repl, text)
    return text


def update_misc(text: str) -> str:
    text = text.replace(
        "Color-coded DIL% (Red < 16.7%, Yellow 16.7-25%, Green 25-50%, Pink 50%+)",
        "Color-coded DIL% (Red <25%, Green 25–50%, Pink 50%+)",
    )
    text = text.replace(
        "Color-coded DIL% (Red < 16.7%, Yellow 16.7-25%, Green 25-50%, Pink 50%+)",
        "Color-coded DIL% (Red <25%, Green 25–50%, Pink 50%+)",
    )
    text = re.sub(
        r"if \(dil < 16\.66\) cls = 'slo-dil-red';\s*else if \(dil < 25\) cls = 'slo-dil-yellow';",
        "if (dil < 25) cls = 'slo-dil-red';",
        text,
    )
    # PHP helper used for Dil categories
    text = text.replace(
        """        if ($dilPercent < 16.66) {
            return 'Red';
        } elseif ($dilPercent < 25) {
            return 'Yellow';
        } elseif ($dilPercent < 50) {
            return 'Green';
        } else {
            return 'Pink';
        }""",
        """        if ($dilPercent < 25) {
            return 'Red';
        } elseif ($dilPercent < 50) {
            return 'Green';
        } else {
            return 'Pink';
        }""",
    )
    return text


def process(text: str) -> str:
    text = replace_get_dil_color_chains(text)
    text = replace_dil_color_rules(text)
    text = remove_dil_yellow_lis(text)
    text = update_dil_selects(text)
    text = update_dil_filter_labels(text)
    text = update_inline_dil_hex(text)
    text = update_dil_filter_js(text)
    text = update_misc(text)
    return text


def main() -> None:
    changed = []
    for path in ROOT.rglob("*"):
        if not path.is_file() or should_skip(path):
            continue
        try:
            raw = path.read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue
        new = process(raw)
        if new != raw:
            path.write_text(new, encoding="utf-8")
            changed.append(str(path.relative_to(ROOT)))
    print(f"updated {len(changed)} files")
    for p in changed:
        print(p)


if __name__ == "__main__":
    main()
