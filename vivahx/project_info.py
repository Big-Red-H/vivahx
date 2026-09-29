"""Collects what each project says about itself on GitHub, for its page on VivaHX.

  python3 -m vivahx.project_info

Writes data/projects/<id>.json (description, license, language, stars, and the opening of its
README) and up to three screenshots from the README as data/projects/<id>/readme-N.jpg, shrunk
so old browsers can load them. They're only used until the project's own page has an About text
or screenshots of its own. The README is only read again when it changes.
"""

import base64
import io
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

from .common import ROOT, load_json, software_list, write_json
from .sync_releases import github

PROJECTS = ROOT / "data" / "projects"
MAX_IMAGES = 3
MAX_WIDTH = 800
# Status badges and the like, which aren't screenshots.
NOT_SCREENSHOTS = re.compile(r"shields\.io|badge|travis-ci|codecov|circleci|appveyor|/workflows/|"
                             r"img\.youtube|\.svg(\?|$)|icon|logo|avatar|sponsor|ko-fi|buymeacoffee|donate", re.I)


def readme_intro(markdown_text):
    """The first couple of real paragraphs: no headings, badges, HTML or tables."""
    text = re.sub(r"<!--.*?-->", "", markdown_text, flags=re.S)
    paragraphs = re.split(r"\n\s*\n", text)
    keep = []
    for para in paragraphs:
        p = para.strip()
        if not p or p.startswith(("#", "<", "|", "```", "[![", "![", ">", "---", "===")):
            continue
        if re.fullmatch(r"(\[!\[.*?\]\(.*?\)\]\(.*?\)\s*)+", p):
            continue
        if len(re.sub(r"\[([^\]]*)\]\([^)]*\)", r"\1", p)) < 40:
            continue
        keep.append(p)
        if len(keep) == 2:
            break
    return "\n\n".join(keep)


def image_urls(markdown_text, raw_base, html_base):
    found = re.findall(r"!\[[^\]]*\]\(\s*<?([^)\s>]+)", markdown_text)
    found += re.findall(r"<img[^>]+src=[\"']([^\"']+)", markdown_text, re.I)
    urls = []
    for url in found:
        if url.startswith("//"):
            url = "https:" + url
        elif not re.match(r"https?://", url):
            url = urllib.parse.urljoin(raw_base, url.lstrip("./") if not url.startswith("/") else url[1:])
        # A link to an image on github.com's web page, not the image itself.
        url = re.sub(r"^https://github\.com/([^/]+/[^/]+)/blob/", r"https://raw.githubusercontent.com/\1/", url)
        if url.startswith(html_base + "/raw/"):
            url = url.replace(html_base + "/raw/", raw_base.rsplit("/", 2)[0] + "/", 1)
        if not NOT_SCREENSHOTS.search(url) and url not in urls:
            urls.append(url)
    return urls


def fetch_image(url):
    from PIL import Image  # only needed here and in the site build

    req = urllib.request.Request(url, headers={"User-Agent": "vivahx-project-info"})
    with urllib.request.urlopen(req, timeout=30) as r:
        data = r.read(15 * 1024 * 1024 + 1)
    if len(data) > 15 * 1024 * 1024:
        return None
    img = Image.open(io.BytesIO(data))
    img.seek(0)
    if img.width < 240 or img.height < 120:
        return None  # too small to be a screenshot
    if img.mode in ("P", "LA", "RGBA"):
        # Put transparent images on white, since JPEG has no transparency.
        rgba = img.convert("RGBA")
        img = Image.new("RGB", rgba.size, "white")
        img.paste(rgba, mask=rgba.getchannel("A"))
    else:
        img = img.convert("RGB")
    if img.width > MAX_WIDTH:
        img = img.resize((MAX_WIDTH, round(img.height * MAX_WIDTH / img.width)), Image.LANCZOS)
    out = io.BytesIO()
    img.save(out, "JPEG", quality=85, optimize=True)
    return out.getvalue()


def update(software):
    repo = software["github"]
    target = PROJECTS / f"{software['id']}.json"
    info = load_json(target, {})
    meta = github(f"/repos/{repo}")
    info.update({
        "repo": meta["full_name"],
        "url": meta["html_url"],
        "description": meta.get("description") or "",
        "homepage": meta.get("homepage") or "",
        "license": (meta.get("license") or {}).get("spdx_id") or "",
        "language": meta.get("language") or "",
        "stars": meta.get("stargazers_count", 0),
        "topics": meta.get("topics") or [],
        "archived": bool(meta.get("archived")),
        "updated": (meta.get("pushed_at") or "")[:10],
    })
    try:
        readme = github(f"/repos/{repo}/readme")
    except urllib.error.HTTPError as e:
        if e.code != 404:
            raise
        readme = None
    if readme and readme["sha"] != info.get("readme_sha"):
        text = base64.b64decode(readme["content"]).decode("utf-8", "replace")
        branch = meta.get("default_branch") or "main"
        folder = readme["path"].rsplit("/", 1)[0] + "/" if "/" in readme["path"] else ""
        raw_base = f"https://raw.githubusercontent.com/{meta['full_name']}/{branch}/{folder}"
        info["intro"] = readme_intro(text)
        images_dir = PROJECTS / software["id"]
        for old in images_dir.glob("readme-*.jpg") if images_dir.exists() else []:
            old.unlink()
        saved = []
        for url in image_urls(text, raw_base, meta["html_url"]):
            if len(saved) == MAX_IMAGES:
                break
            try:
                data = fetch_image(url)
            except Exception as e:  # noqa: BLE001 - any image that can't be read is just skipped
                print(f"  {software['id']}: skipped {url} ({e})", file=sys.stderr)
                continue
            if data:
                images_dir.mkdir(parents=True, exist_ok=True)
                name = f"readme-{len(saved) + 1}.jpg"
                (images_dir / name).write_bytes(data)
                saved.append({"file": name, "source": url})
        info["readme_images"] = saved
        info["readme_sha"] = readme["sha"]
    write_json(target, info)
    return info


def main():
    failures = 0
    for software in software_list():
        if not software.get("github"):
            continue
        try:
            info = update(software)
            print(f"{software['id']}: {len(info.get('readme_images', []))} screenshots, "
                  f"{'an' if info.get('intro') else 'no'} intro")
        except (urllib.error.URLError, OSError, ValueError, KeyError) as e:
            print(f"{software['id']}: couldn't check ({e})", file=sys.stderr)
            failures += 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
