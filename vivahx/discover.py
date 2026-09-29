"""Looks on GitHub for Hotline projects VivaHX doesn't follow yet.

  python3 -m vivahx.discover > candidates.md

Prints a Markdown checklist for the weekly "New Hotline projects to look at" issue. A project is
left out once it's in config/software.toml or config/ignored-repos.txt. "Hotline" is a common
word (phone hotlines, Hotline Miami...), so a repo has to mention the protocol side of it too.
"""

import re
import sys
import time
import urllib.parse

from .common import CONFIG, software_list, toml_string
from .sync_releases import github

QUERIES = [
    "topic:hotline-protocol",
    "topic:hotline-client",
    "topic:hotline-server",
    "hotline protocol in:name,description,topics",
    "hotline client in:name,description,topics",
    "hotline server in:name,description,topics",
    "hotline tracker in:name,description,topics",
    "hxd hotline in:name,description,topics",
]

WANTED = re.compile(r"protocol|client|server|tracker|\bhxd\b|mobius|hotline connect|hotline communications|"
                    r"\bbot\b|classic mac|mac os 9|trtp|\bhtlc\b|\bhtrk\b")
UNWANTED = re.compile(r"miami|suicide|crisis|emergency|helpline|hotlines|twilio|leaflet|call ?cent|conduct|"
                      r"\bsms\b|phone|whistle|maya|webring|\bvim\b|graphics engine|invasive|corona|freshworks|"
                      r"asterisk|voip|ivr|ticket|helpdesk|agent|llm|openai|\bslo\b")


def read_list(name):
    path = CONFIG / name
    if not path.exists():
        return set()
    return {line.split("#", 1)[0].strip().lower() for line in path.read_text().splitlines()} - {""}


def main():
    following = {s["github"].lower() for s in software_list() if s.get("github")}
    ignored = read_list("ignored-repos.txt")
    found = {}
    for q in QUERIES:
        try:
            result = github("/search/repositories?per_page=100&q=" + urllib.parse.quote(q))
        except OSError as e:
            print(f"search {q!r} failed: {e}", file=sys.stderr)
            continue
        for repo in result.get("items", []):
            name = repo["full_name"]
            text = " ".join([name, repo.get("description") or "", " ".join(repo.get("topics") or [])]).lower()
            if name.lower() in following or name.lower() in ignored or repo.get("fork"):
                continue
            if "hotline" not in text or not WANTED.search(text) or UNWANTED.search(text):
                continue
            found[name] = repo
        time.sleep(3)  # the search API allows about 10 searches a minute without a token

    print("Projects on GitHub that look Hotline-related and aren't on VivaHX yet. To follow one, add it "
          "to `config/software.toml` (a starting point is below each). To stop it showing up here, add it "
          "to `config/ignored-repos.txt`.\n")
    if not found:
        print("Nothing new this week.")
        return 0
    for name, repo in sorted(found.items(), key=lambda kv: kv[1].get("pushed_at") or "", reverse=True):
        desc = (repo.get("description") or "").replace("\n", " ").strip()
        print(f"- [ ] **[{name}]({repo['html_url']})** ({repo.get('stargazers_count', 0)} stars, "
              f"updated {(repo.get('pushed_at') or '')[:10]}){': ' + desc if desc else ''}")
        snippet = "\n".join([
            "[[software]]",
            f"id = {toml_string(re.sub(r'[^a-z0-9]+', '-', repo['name'].lower()).strip('-'))}",
            f"name = {toml_string(repo['name'])}",
            'category = "Clients"  # or Servers, Trackers, Bots, Misc',
            f"github = {toml_string(name)}",
            f"description = {toml_string(desc)}",
        ])
        print("  <details><summary>software.toml</summary>\n\n  ```toml\n" +
              "\n".join("  " + line for line in snippet.splitlines()) + "\n  ```\n  </details>")
    return 0


if __name__ == "__main__":
    sys.exit(main())
