# VivaHX

The source for [vivahx.com](http://vivahx.com): Hotline news, and every Hotline client, server,
tracker, bot and tool we know of, with their releases kept on VivaHX for download over plain
HTTP. GitHub does the work; DreamHost serves the pages and DreamObjects holds the files.

The site works in any browser, including classic Mac OS browsers over plain HTTP. It looks the
same as the old PHP site: same tables, colors and images.

## Running the site from GitHub

| To | Do this |
|---|---|
| Post news | Add a file to `news/` (see [news/README.md](news/README.md)), or open a **News post** issue and add the **publish** label |
| Follow a new program | Add it to `config/software.toml`. GitHub releases are picked up by themselves |
| Add a release that isn't on GitHub | Add a file to `releases/custom/` (see [releases/README.md](releases/README.md)) |
| Stop the weekly search suggesting a repo | Add it to `config/ignored-repos.txt` |

Every push to `main` rebuilds and uploads the site.

## What runs when

| When | Workflow | What it does |
|---|---|---|
| Every 6 hours | `releases.yml` | Checks every program for new releases, writes a news post for each, copies the files to DreamObjects, saves to GitHub and uploads the site. |
| Mondays | `discover.yml` | Searches GitHub for Hotline projects that aren't on the list and keeps them in one open issue labeled **discovery**. |
| When an issue gets **publish** | `news-from-issue.yml` | Turns a **News post** issue into a post, uploads the site and closes the issue. |
| Every push to `main` | `build.yml` | Rebuilds and uploads the site. Pull requests are built but not uploaded. |

## The data

- `config/software.toml`: every program we follow.
- `data/releases/<id>.json`: every release of it we've seen, with its files: where each came
  from, its size and SHA-256, and whether it's been copied to the file mirror. Releases are never
  removed from here, even if they disappear from GitHub.
- `news/`: the posts.

The release files themselves aren't in git (they're gigabytes). They're in the DreamObjects
bucket at `<id>/<version>/<file name>`.

## Setting up

The workflows need these on the repo or the Big-Red-H organization
(**Settings > Secrets and variables > Actions**). Until they're set, everything still runs and
saves to GitHub; the upload and the file copying are skipped.

| Name | Kind | What |
|---|---|---|
| `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER` | secrets | SSH access to DreamHost (the same ones the BigRedH repo uses) |
| `DEPLOY_PATH` | variable | the site's folder, like `/home/USER/vivahx.com` |
| `DREAMOBJECTS_ACCESS_KEY`, `DREAMOBJECTS_SECRET_KEY` | secrets | a DreamObjects key |
| `DREAMOBJECTS_BUCKET` | variable | the bucket's name |
| `DREAMOBJECTS_ENDPOINT` | variable | optional; `https://objects-us-east-1.dream.io` if not set |

Then set `files_base_url` in `config/site.toml` to the bucket's plain HTTP address, like
`http://files.vivahx.com`, so download links point at the copies.

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
