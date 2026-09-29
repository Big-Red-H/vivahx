"""Turns a "News post" issue into a file in news/. Run by the workflow once a maintainer adds the
"publish" label; reads the issue from ISSUE_TITLE, ISSUE_BODY, ISSUE_AUTHOR and ISSUE_NUMBER.

Prints the new file's path.
"""

import os
import re
import sys
from datetime import datetime, timezone

from .common import CATEGORIES, NEWS, slugify, toml_string


def form_fields(body):
    """GitHub issue forms arrive as "### Label" headings, each followed by the answer."""
    fields = {}
    for m in re.finditer(r"^###\s+(.+?)\s*\n(.*?)(?=^###\s|\Z)", body.replace("\r\n", "\n"), re.S | re.M):
        value = m.group(2).strip()
        fields[m.group(1).strip().lower()] = "" if value == "_No response_" else value
    return fields


def main():
    fields = form_fields(os.environ.get("ISSUE_BODY", ""))
    title = re.sub(r"^\s*news\s*:\s*", "", os.environ.get("ISSUE_TITLE", ""), flags=re.I).strip()
    post = fields.get("post", "").strip()
    if not title or not post:
        print("The issue needs a title and a post.", file=sys.stderr)
        return 1
    category = fields.get("category", "General News")
    if category not in CATEGORIES:
        category = "General News"
    now = datetime.now(timezone.utc)
    path = NEWS / f"{now:%Y-%m-%d}-{slugify(title)}.md"
    n = 2
    while path.exists():
        path = NEWS / f"{now:%Y-%m-%d}-{slugify(title)}-{n}.md"
        n += 1
    header = "\n".join([
        "+++",
        f"title = {toml_string(title)}",
        f"date = {now:%Y-%m-%dT%H:%M:%SZ}",
        f"category = {toml_string(category)}",
        f"author = {toml_string(os.environ.get('ISSUE_AUTHOR') or 'VivaHX')}",
        f"issue = {int(os.environ.get('ISSUE_NUMBER') or 0)}",
        # Written in an issue, so only plain formatting is kept when it's shown.
        "trusted = false",
        "+++",
    ])
    path.write_text(header + "\n" + post + "\n", encoding="utf-8")
    print(path.relative_to(NEWS.parent))
    return 0


if __name__ == "__main__":
    sys.exit(main())
