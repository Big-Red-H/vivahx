"""Each project's home page: what its developers (or we) wrote, its screenshots, what GitHub says
about it, and its releases.

A project's own files live in projects/<id>/:
  page.json          tagline, links, and the screenshots in order with captions
  about.md           the About text, in Markdown
  screenshots/       the screenshot images
  highlights/<v>.md  a write-up for one release, shown above the release notes

Developers edit these through the editor (editor/), which sends each change for review as a pull
request. Until a project has an About text or screenshots of its own, the opening of its README
and the screenshots found there (data/projects/) are used.
"""

import shutil
from html import escape

from .common import ROOT, load_json, render_markdown, slugify

PROJECTS = ROOT / "projects"
INFO = ROOT / "data" / "projects"
THUMB_WIDTH = 200
SCREENSHOTS_PER_ROW = 3
IMAGE_TYPES = {".png", ".jpg", ".jpeg", ".gif"}


def h(text):
    return escape(str(text), quote=True)


def page_data(pid):
    data = load_json(PROJECTS / pid / "page.json", {})
    return data if isinstance(data, dict) else {}


def about_text(pid):
    path = PROJECTS / pid / "about.md"
    return path.read_text(encoding="utf-8") if path.exists() else ""


def highlight(pid, version):
    path = PROJECTS / pid / "highlights" / f"{slugify(version)}.md"
    return path.read_text(encoding="utf-8") if path.exists() else ""


def github_info(pid):
    return load_json(INFO / f"{pid}.json", {})


def screenshots(pid):
    """[(source path, caption)]: the project's own, or failing that the README's."""
    own = []
    for shot in page_data(pid).get("screenshots", []):
        path = PROJECTS / pid / "screenshots" / str(shot.get("file", ""))
        if path.suffix.lower() in IMAGE_TYPES and path.is_file() and path.parent.name == "screenshots":
            own.append((path, str(shot.get("caption", ""))))
    if own:
        return own, False
    readme = [(INFO / pid / s["file"], "") for s in github_info(pid).get("readme_images", [])
              if (INFO / pid / s["file"]).is_file()]
    return readme, True


def publish_screenshots(pid, out):
    """Copies the screenshots next to the page, with small versions old browsers load quickly.
    Returns [(full url, small url, width, height, caption)] and whether they came from the README."""
    from PIL import Image

    shots, from_readme = screenshots(pid)
    folder = out / "software" / pid
    folder.mkdir(parents=True, exist_ok=True)
    result = []
    for n, (path, caption) in enumerate(shots, 1):
        full = f"{n}-{slugify(path.stem)}{path.suffix.lower()}"
        shutil.copyfile(path, folder / full)
        small = f"{n}-{slugify(path.stem)}-small.jpg"
        with Image.open(path) as img:
            img.seek(0)
            if img.mode in ("P", "LA", "RGBA"):
                rgba = img.convert("RGBA")
                flat = Image.new("RGB", rgba.size, "white")
                flat.paste(rgba, mask=rgba.getchannel("A"))
            else:
                flat = img.convert("RGB")
            height = max(1, round(flat.height * THUMB_WIDTH / flat.width))
            flat.resize((THUMB_WIDTH, height), Image.LANCZOS).save(folder / small, "JPEG", quality=82, optimize=True)
        result.append((f"/software/{pid}/{full}", f"/software/{pid}/{small}", THUMB_WIDTH, height, caption))
    return result, from_readme


def gallery(shots, from_readme):
    if not shots:
        return ""
    rows = []
    for i in range(0, len(shots), SCREENSHOTS_PER_ROW):
        cells = []
        for full, small, w, ht, caption in shots[i:i + SCREENSHOTS_PER_ROW]:
            cells.append(f'<td valign="top" align="center" width="{w + 10}"><a href="{h(full)}">'
                         f'<img src="{h(small)}" width="{w}" height="{ht}" alt="{h(caption)}" border="1"></a>'
                         + (f'<font size="1">{h(caption)}</font>' if caption else "") + "</td>")
        rows.append("<tr>" + "".join(cells) + "</tr>")
    note = '<p><font size="1"><i>Screenshots from the project\'s README.</i></font></p>' if from_readme else ""
    return '<table border="0" cellspacing="4" cellpadding="0">' + "".join(rows) + "</table>" + note


def about_html(software):
    """The project's own About text, or the opening of its README, or its one-line description."""
    pid = software["id"]
    own = about_text(pid).strip()
    if own:
        return render_markdown(own, trusted=False)
    intro = github_info(pid).get("intro", "").strip()
    if intro:
        return render_markdown(intro, trusted=False) + '<p><font size="1"><i>From the project\'s README.</i></font></p>'
    return f"<p>{h(software.get('description', ''))}</p>"


def links_html(software):
    page = page_data(software["id"])
    info = github_info(software["id"])
    links = []
    website = page.get("website") or software.get("homepage") or info.get("homepage")
    if website:
        links.append(f'<a href="{h(website)}">Website</a>')
    if software.get("github"):
        links.append(f'<a href="https://github.com/{h(software["github"])}">Source code</a>')
    if page.get("discord"):
        links.append(f'<a href="{h(page["discord"])}">Discord</a>')
    if page.get("donate"):
        links.append(f'<a href="{h(page["donate"])}">Donate</a>')
    return " | ".join(links)


def facts(software, releases):
    info = github_info(software["id"])
    parts = [f"[ {h(software['category'])} ]"]
    if software.get("platforms"):
        parts.append(h(software["platforms"]))
    parts.append(f"{len(releases)} release{'' if len(releases) == 1 else 's'}")
    if info.get("language"):
        parts.append(h(info["language"]))
    if info.get("license") and info["license"] != "NOASSERTION":
        parts.append(h(info["license"]))
    if info.get("stars"):
        parts.append(f"{info['stars']} stars on GitHub")
    if info.get("archived"):
        parts.append("<b>archived</b>")
    return " | ".join(parts)


def tagline(software):
    return page_data(software["id"]).get("tagline") or software.get("description", "")


def editor_entry(software, releases):
    """What the editor needs to know about a project: who may edit it and its releases."""
    return {
        "id": software["id"],
        "name": software["name"],
        "category": software["category"],
        "github": software.get("github", ""),
        "discord_editors": [str(x) for x in software.get("discord_editors", [])],
        "github_editors": [str(x) for x in software.get("github_editors", [])],
        "releases": [{"version": r["version"], "slug": slugify(r["version"]), "title": r["title"]}
                     for r in releases[:30]],
    }
