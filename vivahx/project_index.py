"""Writes the table of every project at the end of projects/README.md, so the list can be read on
GitHub at a glance.

  python3 -m vivahx.project_index
"""

import re
import sys

from .common import CATEGORIES, PROJECTS, releases_for, software_list

START = "<!-- projects:start (written by vivahx/project_index.py; don't edit by hand) -->"
END = "<!-- projects:end -->"


def cell(text):
    return str(text).replace("|", "\\|").replace("\n", " ")


def main():
    rows = ["| Project | Category | Latest release | Source | Editors |", "|---|---|---|---|---|"]
    software = software_list()
    for category in CATEGORIES:
        for s in (s for s in software if s["category"] == category):
            latest = next(iter(releases_for(s["id"])["releases"]), None)
            source = f"[{s['github']}](https://github.com/{s['github']})" if s.get("github") else (
                f"[website]({s['homepage']})" if s.get("homepage") else "")
            editors = len(s.get("discord_editors", [])) + len(s.get("github_editors", []))
            rows.append(f"| [{cell(s['name'])}]({s['id']}/) | {category} | "
                        f"{cell(latest['version']) + ' (' + latest.get('date', '') + ')' if latest else ''} | "
                        f"{source} | {editors or ''} |")
    table = f"{len(software)} projects.\n\n" + "\n".join(rows)
    readme = PROJECTS / "README.md"
    text = readme.read_text(encoding="utf-8")
    new = re.sub(re.escape(START) + r".*?" + re.escape(END), lambda m: f"{START}\n{table}\n{END}", text, flags=re.S)
    if new != text:
        readme.write_text(new, encoding="utf-8")
    print(f"projects/README.md: {len(software)} projects")
    return 0


if __name__ == "__main__":
    sys.exit(main())
