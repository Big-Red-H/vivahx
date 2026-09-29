"""Paths, config and the small helpers the other scripts share."""

import json
import re
import tomllib
from html import escape
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import quote

ROOT = Path(__file__).resolve().parent.parent
CONFIG = ROOT / "config"
NEWS = ROOT / "news"
CUSTOM = ROOT / "releases" / "custom"
DATA = ROOT / "data" / "releases"
SITE = ROOT / "site"

# The boards the old forum had; news posts and software are filed under them.
CATEGORIES = ["General News", "Clients", "Servers", "Trackers", "Bots", "Misc"]


def load_toml(path):
    with open(path, "rb") as f:
        return tomllib.load(f)


def site_config():
    return load_toml(CONFIG / "site.toml")


def software_list():
    """Every piece of software VivaHX follows, from config/software.toml, in file order."""
    items = load_toml(CONFIG / "software.toml").get("software", [])
    for s in items:
        if not re.fullmatch(r"[a-z0-9][a-z0-9-]*", s.get("id", "")):
            raise SystemExit(f"config/software.toml: bad id {s.get('id')!r} (lowercase letters, digits, dashes)")
        if s.get("category") not in CATEGORIES:
            raise SystemExit(f"config/software.toml: {s['id']} needs a category from {CATEGORIES}")
        for x in s.get("discord_editors", []):
            if not re.fullmatch(r"\d{15,21}", str(x)):
                raise SystemExit(f"config/software.toml: {s['id']}: {x!r} isn't a Discord user ID (a long number)")
        for x in s.get("github_editors", []):
            if not re.fullmatch(r"[A-Za-z0-9-]{1,39}", str(x)):
                raise SystemExit(f"config/software.toml: {s['id']}: {x!r} isn't a GitHub username")
    return items


def load_json(path, default):
    try:
        return json.loads(Path(path).read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return default


def write_json(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=1, ensure_ascii=False) + "\n", encoding="utf-8")


def releases_for(software_id):
    return load_json(DATA / f"{software_id}.json", {"id": software_id, "releases": []})


def slugify(text):
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")[:60] or "post"


def file_url(config, file):
    """Where the file came from (a GitHub release, or a custom release's own address). If a file
    mirror is ever set up (files_base_url), copies there are used instead."""
    base = config.get("files_base_url", "").rstrip("/")
    if file.get("mirrored") and base:
        return base + "/" + quote(file["path"])
    return file["source_url"]


def format_size(n):
    if not n:
        return ""
    for unit in ("bytes", "KB", "MB", "GB"):
        if n < 1024 or unit == "GB":
            return f"{n:,} bytes" if unit == "bytes" else f"{n:.1f} {unit}"
        n /= 1024
    return ""


# --- Text from outside (release notes, posts sent in as issues) -------------------------------

class _Sanitizer(HTMLParser):
    """Keeps plain formatting and http(s) links; drops scripts, styles, images and everything
    else. Images go because the ones in release notes are HTTPS-only and old browsers can't load
    them anyway."""

    KEEP = {"p", "br", "ul", "ol", "li", "b", "strong", "i", "em", "code", "pre", "blockquote", "hr", "a"}
    RENAME = {"h1": "b", "h2": "b", "h3": "b", "h4": "b", "h5": "b", "h6": "b"}
    DROP_CONTENT = {"script", "style", "iframe", "object", "embed", "template"}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.out = []
        self.skip = 0

    def handle_starttag(self, tag, attrs):
        if tag in self.DROP_CONTENT:
            self.skip += 1
            return
        if self.skip:
            return
        tag = self.RENAME.get(tag, tag)
        if tag not in self.KEEP:
            return
        if tag == "a":
            href = dict(attrs).get("href") or ""
            if not re.match(r"https?://", href):
                self.out.append("<a>")
                return
            self.out.append(f'<a href="{escape(href)}">')
        else:
            self.out.append(f"<{tag}>")

    def handle_endtag(self, tag):
        if tag in self.DROP_CONTENT:
            self.skip = max(0, self.skip - 1)
            return
        if self.skip:
            return
        tag = self.RENAME.get(tag, tag)
        if tag in self.KEEP and tag not in ("br", "hr"):
            self.out.append(f"</{tag}>")

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)

    def handle_data(self, data):
        if not self.skip:
            self.out.append(escape(data, quote=False))


def sanitize(html):
    parser = _Sanitizer()
    parser.feed(html)
    parser.close()
    return "".join(parser.out)


def github_style(text):
    """GitHub starts a list right after a line of text; Python-Markdown wants a blank line."""
    lines = text.replace("\r\n", "\n").split("\n")
    out = []
    for line in lines:
        is_item = re.match(r"\s*([*+-]|\d+\.)\s+", line)
        if is_item and out and out[-1].strip() and not re.match(r"\s*([*+-]|\d+\.)\s+", out[-1]):
            out.append("")
        out.append(line)
    return "\n".join(out)


def render_markdown(text, trusted):
    import markdown  # only the site build needs it

    html = markdown.markdown(github_style(text), extensions=["fenced_code", "sane_lists"], output_format="html")
    return html if trusted else sanitize(html)


def read_post(path):
    """A news post: TOML between +++ lines, then Markdown."""
    text = Path(path).read_text(encoding="utf-8")
    m = re.match(r"\A\+\+\+\s*\n(.*?)\n\+\+\+\s*\n?(.*)\Z", text, re.S)
    if not m:
        raise SystemExit(f"{path}: needs a +++ header (see news/README.md)")
    meta = tomllib.loads(m.group(1))
    for key in ("title", "date"):
        if key not in meta:
            raise SystemExit(f"{path}: the header needs a {key}")
    meta.setdefault("category", "General News")
    if meta["category"] not in CATEGORIES:
        raise SystemExit(f"{path}: category must be one of {CATEGORIES}")
    meta.setdefault("author", "VivaHX")
    meta["body"] = m.group(2)
    meta["slug"] = Path(path).stem
    return meta


def toml_string(value):
    """A TOML basic string, for writing post headers."""
    return json.dumps(value, ensure_ascii=False)
