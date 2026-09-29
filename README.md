# VivaHX

The source for [vivahx.com](http://vivahx.com): Hotline news, and every Hotline client, server,
tracker, bot and tool we know of, with a post for each new release. GitHub does the work and
DreamHost serves the pages. Downloads link to each project's own releases, which stay the
source of truth.

The site works in any browser, including classic Mac OS browsers over plain HTTP. It looks the
same as the old PHP site: same tables, colors and images.

## Running the site from GitHub

| To | Do this |
|---|---|
| Post news | Add a file to `news/` (see [news/README.md](news/README.md)), or open a **News post** issue and add the **publish** label |
| Follow a new program | Make a folder for it in `projects/` with a `project.toml` (see [projects/README.md](projects/README.md)), or recommend it from the editor. GitHub releases are picked up by themselves |
| Change a project's page | Edit `projects/<id>/` (see [projects/README.md](projects/README.md)), or let its developers do it from the editor |
| Let a developer edit their project | Add their Discord user ID to `discord_editors`, or their GitHub username to `github_editors`, in `projects/<id>/project.toml` |
| Add a release that isn't on GitHub | Add a file to `projects/<id>/releases/` (see [projects/README.md](projects/README.md)) |
| Stop the weekly search suggesting a repo | Add it to `config/ignored-repos.txt` |

Every push to `main` rebuilds and uploads the site.

## What runs when

| When | Workflow | What it does |
|---|---|---|
| Every 6 hours | `releases.yml` | Checks every program for new releases, writes a news post for each, reads each project's GitHub page and README, saves to GitHub and uploads the site. |
| Mondays | `discover.yml` | Searches GitHub for Hotline projects that aren't on the list and keeps them in one open issue labeled **discovery**. |
| When an issue gets **publish** | `news-from-issue.yml` | Turns a **News post** issue into a post, uploads the site and closes the issue. |
| Every push to `main` | `build.yml` | Rebuilds and uploads the site. Pull requests are built but not uploaded. |

## The developer editor

`editor/` is a small PHP app at https://vivahx.com/editor/ (it needs HTTPS; the rest of the site
stays plain HTTP). Developers sign in with GitHub or Discord and can change their own project's
tagline, links, About text and screenshots, post news about it, and write up a release. Every
change arrives as a pull request. It's the Hotline Wiki's Discord editor, adapted.

Its settings, with its secrets, live outside the website at `~/editor-config/vivahx.com.php` on
DreamHost; `editor/config.sample.php` says what goes in it. See
[projects/README.md](projects/README.md) for who can edit what.

GitHub's schedules are best effort, so the cron job on DreamHost that starts BigRedH's workflows
also starts `releases.yml` every 6 hours and `discover.yml` on Mondays (see the BigRedH README).

## The data

- `projects/<id>/`: every program we follow, one folder each: what it is and who may edit it
  (`project.toml`), and its page (see [projects/README.md](projects/README.md), which also lists
  them all).
- `data/releases/<id>.json`: every release of it we've seen, with its notes and files (name,
  size, download address). Releases are never removed from here, even if they disappear from
  GitHub, so the list survives even when a download doesn't.
- `news/`: the posts.

The release files themselves aren't copied anywhere. Classic Mac software, which isn't on
GitHub, links to plain `http://` copies (like the Hotline Wiki's) so old browsers can download it.

## Setting up

The workflows need these on the repo or the Big-Red-H organization
(**Settings > Secrets and variables > Actions**). Until they're set, everything still runs and
saves to GitHub; the upload is skipped.

| Name | Kind | What |
|---|---|---|
| `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER` | secrets | SSH access to DreamHost (the same ones the BigRedH repo uses) |
| `DEPLOY_PATH` | variable | the site's folder, like `/home/USER/vivahx.com` |

A file mirror isn't used, but `vivahx/mirror.py` can copy every release file to a DreamObjects
bucket if that's ever wanted: add `DREAMOBJECTS_ACCESS_KEY` and `DREAMOBJECTS_SECRET_KEY`
(secrets), `DREAMOBJECTS_BUCKET` (variable), and set `files_base_url` in `config/site.toml`.

In the DreamHost panel, leave **HTTPS redirect** off for vivahx.com so old browsers can still
connect. A deploy only deletes files in a folder it set up itself (it leaves a `.vivahx-deploy`
file there).

## Running it on your computer

```bash
pip install -r requirements.txt
python3 -m vivahx.sync_releases     # set GITHUB_TOKEN to avoid GitHub's rate limit
python3 -m vivahx.build
python3 -m http.server -d public 8000
```
