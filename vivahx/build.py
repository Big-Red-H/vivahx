"""Builds the whole site into public/.

  python3 -m vivahx.build

Same tables, images and colors as the old PHP site, so it looks the same and still works in
classic Mac OS browsers over plain HTTP. No JavaScript.
"""

import json
import re
import shutil
from collections import defaultdict
from datetime import datetime, timezone
from email.utils import format_datetime
from html import escape

from . import projects
from .common import (CATEGORIES, NEWS, ROOT, SITE, file_url, format_size, read_post, releases_for,
                     render_markdown, site_config, software_list)

OUT = ROOT / "public"
# Releases shown in full on a software page; older ones are listed with just their files.
FULL_RELEASES = 10
FONT = 'face="Verdana, Helvetica, Arial"'


def h(text):
    return escape(str(text), quote=True)


def page(config, title, body, sidebar):
    full = config["title"] if not title else f"{title} - {config['title']}"
    invite = config.get("discord_invite", "")
    discord_link = f' | <a href="{h(invite)}"><b>Discord</b></a>' if invite else ""
    return f"""<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="color-scheme" content="only light">
    <title>{h(full)}</title>
    <link rel="alternate" type="application/rss+xml" title="VivaHX news" href="/rss.xml">
    <style type="text/css">
        :root {{ color-scheme: only light; }}
        body {{ background-color: #E8EFCF; color: #222222; margin: 0; padding: 0; font-family: Geneva, Helvetica, Arial, sans-serif; }}
        td {{ font-family: Geneva, Helvetica, Arial, sans-serif; }}
        a {{ color: #00607F; text-decoration: none; }}
        a:visited {{ color: #8A4B00; }}
        a:hover {{ text-decoration: underline; }}
        img {{ display: block; border: 0; }}
        .spacer {{ line-height: 1px; font-size: 1px; }}
        pre {{ white-space: pre-wrap; }}
        /* Old browsers keep the sizes in the HTML; newer ones get small text at a readable size. */
        font[size="1"] {{ font-size: 12px; }}
        font[size="2"] {{ font-size: 13px; }}
    </style>
</head>
<body bgcolor="#E8EFCF" text="#222222" link="#00607F" vlink="#8A4B00" alink="#FF0000" leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">

<table width="100%" border="0" cellspacing="0" cellpadding="0" background="/images/toptile.gif">
    <tr>
        <td width="420"><a href="/"><img src="/images/vivahx.gif" width="420" height="85" alt="VivaHX"></a></td>
        <td align="right" valign="top">
            <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td><img src="/images/shadow.gif" width="132" height="85" alt=""></td>
                    <td><img src="/images/guys.gif" width="105" height="85" alt=""></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div align="center" style="margin-top: 10px;">
    <font size="2">[ <a href="/">General News</a> | <a href="/software/">Software</a> | <a href="/archive.html">Archive</a>{discord_link} ]</font>
</div>

<table width="98%" border="0" cellspacing="0" cellpadding="0" align="center" style="margin-top: 15px;">
    <tr>
        <td valign="top" width="75%">
{body}
</td> <td width="15" class="spacer"><img src="/images/pix.gif" width="15" height="1"></td>

        <td valign="top" width="200" align="left">
{sidebar}
        </td> </tr>
</table>

<p>&nbsp;</p>
<div align="center">
    <font {FONT} size="1" color="#999966">Brought to you by <a href="http://bigredh.com/">BigRedH.com</a> | <a href="http://hlwiki.com/">HLWiki.com</a> | stickytack.com</font>
</div>

</body>
</html>
"""


def box(title, meta, body, footer):
    """One news item, the old site's table exactly."""
    return f"""<table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 25px;">
    <tr bgcolor="#5fba22">
        <td width="7" height="10" valign="top" class="spacer"><img src="/images/cl.gif" width="7" height="10"></td>
        <td width="100%" height="10" valign="middle" style="padding: 0 5px;">
            <font color="#FFFFFF" {FONT} size="2"><b>{title}</b></font>
        </td>
        <td width="7" height="10" align="right" valign="top" class="spacer"><img src="/images/cr.gif" width="7" height="10"></td>
    </tr>
    <tr bgcolor="#e6e6e6">
        <td width="11" background="/images/gl.gif" class="spacer"><img src="/images/pix.gif" width="11" height="11"></td>
        <td width="100%" style="padding: 2px 5px;">
            <font {FONT} size="1">{meta}</font>
        </td>
        <td width="11" background="/images/gr.gif" align="right" class="spacer"><img src="/images/pix.gif" width="11" height="11"></td>
    </tr>
    <tr bgcolor="#006666">
        <td colspan="3" height="2" class="spacer"><img src="/images/pix.gif" width="1" height="2"></td>
    </tr>
    <tr bgcolor="#DDE3C5">
        <td width="11" background="/images/wl.gif" class="spacer"><img src="/images/pix.gif" width="11" height="11"></td>
        <td width="100%" style="padding: 10px;">
            <font {FONT} size="2">
{body}
            </font>
        </td>
        <td width="11" background="/images/wr.gif" align="right" class="spacer"><img src="/images/pix.gif" width="11" height="11"></td>
    </tr>
    <tr bgcolor="#5fba22"><td colspan="3" height="1" class="spacer"><img src="/images/pix.gif" width="1" height="1"></td></tr>
    <tr bgcolor="#cccccc">
        <td width="11" background="/images/wl_cccccc.gif" class="spacer"><img src="/images/pix.gif" width="11" height="11"></td>
        <td width="100%" style="padding: 5px;">
            <font {FONT} size="2">( {footer} )</font>
        </td>
        <td width="11" background="/images/wr_cccccc.gif" align="right" class="spacer"><img src="/images/pix.gif" width="11" height="11"></td>
    </tr>
    <tr bgcolor="#5fba22"><td colspan="3" height="1" class="spacer"><img src="/images/pix.gif" width="1" height="1"></td></tr>
</table>
"""


def sidebar_box(title, lines):
    """One sidebar box, the old site's table exactly."""
    return f"""            <table width="115" border="0" cellpadding="0" cellspacing="0" style="margin-top: 15px;">
                <tr bgcolor="#5fba22">
                    <td width="7" height="10" valign="top" class="spacer"><img src="/images/cl.gif" width="7" height="10"></td>
                    <td width="101" height="10" valign="middle">
                        <font {FONT} size="1" color="#FFFFFF"><b>&nbsp;{h(title)}</b></font>
                    </td>
                    <td width="7" height="10" align="right" valign="top" class="spacer"><img src="/images/cr.gif" width="7" height="10"></td>
                </tr>
            </table>
            <table width="200" border="0" cellpadding="0" cellspacing="0">
                <tr bgcolor="#5fba22"><td colspan="3" height="1" class="spacer"><img src="/images/pix.gif" width="1" height="1"></td></tr>
                <tr bgcolor="#DDE3C5">
                    <td width="11" valign="top" style="background-image: url('/images/sl.gif'); background-repeat: repeat-y; background-position: left;">
                        <img src="/images/pix.gif" width="11" height="1">
                    </td>
                    <td width="178" valign="top" align="left" style="padding: 6px 4px;">
                        <font {FONT} size="1" style="line-height: 14px; text-align: left;">
                            {"<br>".join(lines)}
                        </font>
                    </td>
                    <td width="11" valign="top" align="right" style="background-image: url('/images/sr.gif'); background-repeat: repeat-y; background-position: right;">
                        <img src="/images/pix.gif" width="11" height="1">
                    </td>
                </tr>
                <tr bgcolor="#5fba22"><td colspan="3" height="1" class="spacer"><img src="/images/pix.gif" width="1" height="1"></td></tr>
            </table>
"""


def by_recent_release(software, latest):
    """Newest release first; software with no releases after, in list order."""
    return sorted(software, key=lambda s: (latest.get(s["id"]) or {}).get("date") or "", reverse=True)


def build_sidebar(software, latest, invite=""):
    parts = [sidebar_box("Links", [
        "- <a href='/'>Home</a>",
        *([f"- <a href='{h(invite)}'>Hotline HQ Discord</a>"] if invite else []),
        "- <a href='/software/'>Software</a>",
        "- <a href='/archive.html'>News Archive</a>",
        "- <a href='http://tracker.bigredh.com/'>Server Tracker</a>",
        "- <a href='http://hlwiki.com/'>Hotline Wiki</a>",
    ])]
    by_category = defaultdict(list)
    for s in by_recent_release(software, latest):
        by_category[s["category"]].append(s)
    for category in CATEGORIES:
        items = by_category.get(category)
        if not items:
            continue
        lines = []
        for s in items[:5]:
            version = latest.get(s["id"])
            label = s["name"] + (f" {version['version']}" if version else "")
            lines.append(f"- <a href='/software/{s['id']}.html'>{h(label)}</a>")
        if len(items) > 5:
            lines.append(f"<div align='right'><font size='1'><a href='/software/#{category.lower()}'><i>(more...)</i></a></font></div>")
        parts.append(sidebar_box(category, lines))
    return "\n".join(parts)


def download_list(config, release):
    if not release or not release.get("files"):
        return ""
    rows = []
    for f in release["files"]:
        size = format_size(f.get("size"))
        rows.append(f'<li><a href="{h(file_url(config, f))}">{h(f["name"])}</a>'
                    f'{" &nbsp;(" + size + ")" if size else ""}</li>')
    return "<p><b>Downloads</b></p>\n<ul>\n" + "\n".join(rows) + "\n</ul>"


def post_html(config, post, releases_by_id):
    html = render_markdown(post["body"], trusted=post.get("trusted", True))
    release = None
    if post.get("software"):
        release = next((r for r in releases_by_id.get(post["software"], [])
                        if r["version"] == post.get("release")), None)
    if release:
        # The developer's own write-up about this release, if they've added one.
        extra = projects.highlight(post["software"], release["version"]).strip()
        if extra:
            html = render_markdown(extra, trusted=False) + html
    downloads = download_list(config, release)
    html = re.sub(r"<p>\s*\{\{\s*downloads\s*\}\}\s*</p>", lambda m: downloads, html)
    return html.replace("{{ downloads }}", downloads)


def post_meta(post):
    date = post["date"]
    when = date.strftime("%B %-d, %Y") if hasattr(date, "strftime") else str(date)
    return f"[ {h(post['category'])} ] posted by {h(post['author'])} {when}"


def post_footer(post, software_by_id):
    links = [f'<a href="/news/{post["slug"]}.html">Permalink</a>']
    s = software_by_id.get(post.get("software"))
    if s:
        links.append(f'<a href="/software/{s["id"]}.html">About {h(s["name"])}</a>')
    return " | ".join(links)


def release_box(config, s, r, title_prefix=""):
    extra = projects.highlight(s["id"], r["version"]).strip()
    notes = render_markdown(extra, trusted=False) if extra else ""
    notes += render_markdown(r.get("notes", ""), trusted=False) if r.get("notes") else ""
    flags = " <i>(prerelease)</i>" if r.get("prerelease") else ""
    flags += " <i>(since removed by its author)</i>" if r.get("removed_upstream") else ""
    footer = f'<a href="{h(r["url"])}">Release page</a>' if r.get("url") else f'<a href="/software/{s["id"]}.html">{h(s["name"])}</a>'
    return box(title_prefix + h(r["title"]) + flags, f"Released {h(r.get('date') or 'unknown')}",
               notes + download_list(config, r), footer)


def project_page(config, s, releases, posts, editor_url):
    shots, from_readme = projects.publish_screenshots(s["id"], OUT)
    parts = [f"<p><b>{h(projects.tagline(s))}</b></p>", projects.gallery(shots, from_readme), projects.about_html(s)]
    if not releases:
        where = "its website" if s.get("homepage") else "its source code page" if s.get("github") else "the Hotline Wiki"
        parts.append(f"<p><i>No releases to download here yet. See {where}.</i></p>")
    links = projects.links_html(s)
    if links:
        parts.append(f"<p><b>{links}</b></p>")
    footer = f'<a href="/software/#{s["category"].lower()}">All {h(s["category"].lower())}</a>'
    if editor_url:
        # The editor needs a modern browser (it signs in with Discord or GitHub over HTTPS).
        footer += f' | <a href="{h(editor_url)}/project.php?id={h(s["id"])}">Edit this page</a>'
    body = [box(h(s["name"]), projects.facts(s, releases), "\n".join(p for p in parts if p), footer)]

    if releases:
        body.append(release_box(config, s, releases[0], "What's new: "))
    if posts:
        lines = [f'<li><a href="/news/{p["slug"]}.html">{h(p["title"])}</a> '
                 f'<font size="1">{sort_key(p).strftime("%B %-d, %Y")}</font></li>' for p in posts[:10]]
        body.append(box(f"News about {h(s['name'])}", f"{len(posts)} post{'' if len(posts) == 1 else 's'}",
                        "<ul>\n" + "\n".join(lines) + "\n</ul>", '<a href="/archive.html">All news</a>'))
    for r in releases[1:FULL_RELEASES]:
        body.append(release_box(config, s, r))
    older = releases[FULL_RELEASES:]
    if older:
        lines = []
        for r in older:
            files = ", ".join(f'<a href="{h(file_url(config, f))}">{h(f["name"])}</a>' for f in r.get("files", []))
            lines.append(f"<li><b>{h(r['title'])}</b> <font size=\"1\">({h(r.get('date') or 'unknown')})</font>"
                         + (f"<br><font size=\"1\">{files}</font>" if files else "") + "</li>")
        body.append(box("Older releases", f"{len(older)} more", "<ul>\n" + "\n".join(lines) + "\n</ul>",
                        f'<a href="/software/{s["id"]}.html">Back to the top</a>'))
    return "\n".join(body)


def write(path, text):
    path = OUT / path
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")


def sort_key(post):
    d = post["date"]
    if isinstance(d, datetime):
        return d if d.tzinfo else d.replace(tzinfo=timezone.utc)
    return datetime(d.year, d.month, d.day, tzinfo=timezone.utc)


def main():
    config = site_config()
    software = software_list()
    software_by_id = {s["id"]: s for s in software}
    releases_by_id = {s["id"]: releases_for(s["id"])["releases"] for s in software}
    latest = {sid: next((r for r in rs if not r.get("prerelease")), rs[0] if rs else None)
              for sid, rs in releases_by_id.items()}
    posts = sorted((read_post(p) for p in NEWS.glob("*.md") if p.name != "README.md"),
                   key=sort_key, reverse=True)

    if OUT.exists():
        shutil.rmtree(OUT)
    shutil.copytree(SITE, OUT)
    sidebar = build_sidebar(software, latest, config.get("discord_invite", ""))

    # Front page and one page per post.
    front = [box(h(p["title"]), post_meta(p), post_html(config, p, releases_by_id), post_footer(p, software_by_id))
             for p in posts[:config.get("posts_on_front_page", 10)]]
    if len(posts) > len(front):
        front.append(f'<p><font {FONT} size="2"><a href="/archive.html">Older news...</a></font></p>')
    write("index.html", page(config, "", "\n".join(front) or "<p>No news yet.</p>", sidebar))
    for p in posts:
        write(f"news/{p['slug']}.html", page(config, p["title"], box(
            h(p["title"]), post_meta(p), post_html(config, p, releases_by_id), post_footer(p, software_by_id)), sidebar))

    # Archive.
    by_year = defaultdict(list)
    for p in posts:
        by_year[sort_key(p).year].append(p)
    lines = []
    for year in sorted(by_year, reverse=True):
        lines.append(f"<p><b>{year}</b></p><ul>")
        for p in by_year[year]:
            lines.append(f'<li><a href="/news/{p["slug"]}.html">{h(p["title"])}</a> '
                         f'<font size="1">[ {h(p["category"])} ] {sort_key(p).strftime("%b %-d")}</font></li>')
        lines.append("</ul>")
    write("archive.html", page(config, "News Archive", box("News Archive", f"{len(posts)} posts",
                                                           "\n".join(lines), '<a href="/">Back to the news</a>'), sidebar))

    # Software.
    editor_url = config.get("editor_url", "").rstrip("/")
    sections = []
    for category in CATEGORIES:
        items = [s for s in by_recent_release(software, latest) if s["category"] == category]
        if not items:
            continue
        rows = []
        for s in items:
            r = latest.get(s["id"])
            rows.append(f'<p><b><a href="/software/{s["id"]}.html">{h(s["name"])}</a></b>'
                        + (f' {h(r["version"])} <font size="1">({h(r.get("date", ""))})</font>' if r else "")
                        + (f'<br><font size="1">{h(s.get("platforms", ""))}</font>' if s.get("platforms") else "")
                        + f'<br>{h(projects.tagline(s))}</p>')
        sections.append(f'<a name="{category.lower()}"></a>' + box(
            h(category), f"{len(items)} {'program' if len(items) == 1 else 'programs'}", "\n".join(rows),
            "Downloads come straight from each project's own releases"))
    # How to add a project that's missing: the editor (signed in) or a GitHub issue (anyone).
    suggest = f"https://github.com/{config.get('github_repo', 'Big-Red-H/vivahx')}/issues/new?template=software.yml"
    ways = []
    if editor_url:
        ways.append(f'<li><a href="{h(editor_url)}/recommend.php">Recommend it</a>: sign in with GitHub, or with Discord '
                    f'if you have a Client, Server or Tracker Dev role. <font size="1">(Needs a modern browser.)</font></li>')
    ways.append(f'<li><a href="{h(suggest)}">Suggest it on GitHub</a>: anyone with a GitHub account.</li>')
    sections.append(box("Know a Hotline project we're missing?", "Clients, servers, trackers, bots, tools",
                        "<p>Tell us about it, and once a maintainer has looked it over it gets its own page here, "
                        "with its releases followed automatically.</p><ul>" + "".join(ways) + "</ul>",
                        '<a href="/software/">All software</a>'))
    write("software/index.html", page(config, "Software", "\n".join(sections), sidebar))

    posts_by_software = defaultdict(list)
    for p in posts:
        if p.get("software"):
            posts_by_software[p["software"]].append(p)
    for s in software:
        body = project_page(config, s, releases_by_id[s["id"]], posts_by_software[s["id"]], editor_url)
        write(f"software/{s['id']}.html", page(config, s["name"], body, sidebar))

    # What the editor reads to know who may edit which project. It's kept from the web by
    # editor/.htaccess.
    editor = OUT / "editor"
    shutil.copytree(ROOT / "editor", editor, ignore=shutil.ignore_patterns("config*.php", "*.md"))
    (editor / "projects.json").write_text(json.dumps(
        [projects.editor_entry(s, releases_by_id[s["id"]]) for s in software], indent=1) + "\n", encoding="utf-8")

    # RSS for feed readers, old and new.
    items = []
    for p in posts[:20]:
        link = f"{config['url']}/news/{p['slug']}.html"
        items.append(f"""<item>
<title>{h(p['title'])}</title>
<link>{h(link)}</link>
<guid>{h(link)}</guid>
<category>{h(p['category'])}</category>
<pubDate>{format_datetime(sort_key(p))}</pubDate>
<description>{h(post_html(config, p, releases_by_id))}</description>
</item>""")
    write("rss.xml", f"""<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0"><channel>
<title>VivaHX!</title>
<link>{h(config['url'])}/</link>
<description>Hotline news and software</description>
{chr(10).join(items)}
</channel></rss>
""")

    write("404.html", page(config, "Not found", box("Not found", "", "<p>There's nothing here. Try the <a href=\"/\">front page</a>.</p>", '<a href="/">Home</a>'), sidebar))
    print(f"public/: {len(posts)} posts, {len(software)} programs")


if __name__ == "__main__":
    main()
