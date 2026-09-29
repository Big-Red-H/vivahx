# Project pages

Every program in `config/software.toml` has a home page on VivaHX at
`/software/<id>.html`. What's on it comes from:

| Part | From |
|---|---|
| Name, category, platforms | `config/software.toml` |
| Tagline, website, Discord, donate link, screenshots | `projects/<id>/page.json` |
| About text | `projects/<id>/about.md`, or else the start of the project's README |
| Screenshots | `projects/<id>/screenshots/`, or else up to three from the README |
| License, language, stars | GitHub, read every 6 hours (`data/projects/<id>.json`) |
| Release write-ups | `projects/<id>/highlights/<version>.md`, shown above that release's notes |
| Releases and their notes | GitHub, or `releases/custom/` |
| News | posts in `news/` with `software = "<id>"` |

`page.json` looks like this (every field is optional):

```json
{
 "tagline": "One line under the name",
 "website": "http://example.com",
 "discord": "https://discord.gg/...",
 "donate": "https://...",
 "screenshots": [
  {"file": "main-window.png", "caption": "The main window"}
 ]
}
```

A release write-up's file name is the version in lowercase with dashes: `v2.3.2` is
`highlights/v2-3-2.md`.

## Developers edit their own page

Developers change these files from the editor at https://vivahx.com/editor/, signing in with
GitHub or Discord. Each change arrives as a pull request for a maintainer to merge.

Who can edit a project:

- **GitHub:** anyone who can push to the project's GitHub repo, and anyone in its
  `github_editors` in `config/software.toml`.
- **Discord:** anyone with the Client Dev, Server Dev or Tracker Dev role on the Hotline Discord
  **and** listed in the project's `discord_editors`.

Someone who isn't linked yet can ask from the editor. It opens an issue labeled
**editor-access** with the line to add to `config/software.toml`.
