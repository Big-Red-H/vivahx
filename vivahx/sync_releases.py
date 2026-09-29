"""Collects releases into data/releases/<id>.json and writes a news post for each new one.

  python3 -m vivahx.sync_releases

GitHub releases come from the API (set GITHUB_TOKEN to avoid its rate limit); custom ones from
releases/custom/<id>/<version>.toml. A release is never removed here, even if it disappears
upstream: this is the backup. Files start out not mirrored; vivahx.mirror copies them.

The first time a piece of software is added, its past releases are recorded but only the latest
gets a news post.
"""

import fnmatch
import json
import os
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone

from .common import (CUSTOM, DATA, NEWS, load_toml, releases_for, slugify, software_list,
                     toml_string, write_json)


def github(path):
    req = urllib.request.Request("https://api.github.com" + path, headers={
        "Accept": "application/vnd.github+json",
        "User-Agent": "vivahx-release-sync",
        **({"Authorization": "Bearer " + os.environ["GITHUB_TOKEN"]} if os.environ.get("GITHUB_TOKEN") else {}),
    })
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.load(r)


def github_releases(software):
    repo = software["github"]
    releases = []
    for page in range(1, 11):
        batch = github(f"/repos/{repo}/releases?per_page=100&page={page}")
        releases += batch
        if len(batch) < 100:
            break
    skip = software.get("skip_files", [])
    out = []
    for r in releases:
        if r.get("draft") or (r.get("prerelease") and not software.get("prereleases", False)):
            continue
        version = r["tag_name"]
        files = [{
            "name": a["name"],
            "size": a["size"],
            "source_url": a["browser_download_url"],
            "path": f"{software['id']}/{version}/{a['name']}",
            "mirrored": False,
        } for a in r.get("assets", []) if not any(fnmatch.fnmatch(a["name"], p) for p in skip)]
        out.append({
            "version": version,
            "title": (r.get("name") or version).strip(),
            "date": (r.get("published_at") or r.get("created_at") or "")[:10],
            "url": r["html_url"],
            "notes": r.get("body") or "",
            "prerelease": bool(r.get("prerelease")),
            "source": "github",
            "files": files,
        })
    return out


def custom_releases(software):
    folder = CUSTOM / software["id"]
    out = []
    for path in sorted(folder.glob("*.toml")) if folder.exists() else []:
        c = load_toml(path)
        version = str(c.get("version") or path.stem)
        files = []
        for f in c.get("files", []):
            name = f.get("name") or f["url"].rsplit("/", 1)[-1]
            files.append({
                "name": name,
                "size": f.get("size", 0),
                "source_url": f["url"],
                "path": f"{software['id']}/{version}/{name}",
                "mirrored": False,
            })
        out.append({
            "version": version,
            "title": c.get("title", f"{software['name']} {version}"),
            "date": str(c.get("date", ""))[:10],
            "url": c.get("url", ""),
            "notes": c.get("notes", ""),
            "prerelease": bool(c.get("prerelease", False)),
            "source": "custom",
            "files": files,
        })
    return out


def merge(old, new):
    """Upstream metadata wins, but mirroring state is kept and nothing is ever dropped."""
    old_by_version = {r["version"]: r for r in old}
    merged = []
    for r in new:
        before = old_by_version.pop(r["version"], None)
        if before:
            done = {f["name"]: f for f in before.get("files", [])}
            for f in r["files"]:
                prev = done.pop(f["name"], None)
                if prev:
                    for key in ("mirrored", "sha256", "mirrored_at"):
                        if key in prev:
                            f[key] = prev[key]
                    if not f.get("size"):
                        f["size"] = prev.get("size", 0)
            # Files upstream took down stay in the backup.
            r["files"] += [f for f in done.values() if f.get("mirrored")]
        merged.append(r)
    for r in old_by_version.values():
        r["removed_upstream"] = True
        merged.append(r)
    merged.sort(key=lambda r: (r.get("date") or "", r["version"]), reverse=True)
    return merged


def write_post(software, release):
    date = release.get("date") or datetime.now(timezone.utc).strftime("%Y-%m-%d")
    slug = f"{date}-{slugify(software['id'] + '-' + release['version'])}"
    path = NEWS / f"{slug}.md"
    if path.exists():
        return None
    notes = release.get("notes", "").strip()
    if len(notes) > 1500:
        notes = notes[:1500].rsplit("\n", 1)[0] + "\n\n..."
    header = "\n".join([
        "+++",
        f"title = {toml_string(software['name'] + ' ' + release['version'])}",
        f"date = {date}T12:00:00Z",
        f"category = {toml_string(software['category'])}",
        'author = "VivaHX"',
        f"software = {toml_string(software['id'])}",
        f"release = {toml_string(release['version'])}",
        # The notes come from the software's own release page, so they're cleaned up when shown.
        "trusted = false",
        "+++",
    ])
    body = (notes + "\n\n" if notes else "") + "{{ downloads }}\n"
    path.write_text(header + "\n" + body, encoding="utf-8")
    return path


def main():
    failures = 0
    for software in software_list():
        existing = releases_for(software["id"])
        first_time = not (DATA / f"{software['id']}.json").exists()
        try:
            found = (github_releases(software) if software.get("github") else []) + custom_releases(software)
        except (urllib.error.URLError, OSError, ValueError) as e:
            print(f"{software['id']}: couldn't check ({e})", file=sys.stderr)
            failures += 1
            continue
        known = {r["version"] for r in existing["releases"]}
        releases = merge(existing["releases"], found)
        new = [r for r in releases if r["version"] not in known and not r.get("removed_upstream")]
        write_json(DATA / f"{software['id']}.json", {"id": software["id"], "releases": releases})
        to_post = new[:1] if first_time else new
        for r in to_post:
            if not software.get("no_posts"):
                post = write_post(software, r)
                if post:
                    print(f"{software['id']}: posted {post.name}")
        print(f"{software['id']}: {len(releases)} releases, {len(new)} new")
    return 1 if failures and failures == len(software_list()) else 0


if __name__ == "__main__":
    sys.exit(main())
