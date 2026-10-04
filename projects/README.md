# Projects

Every Hotline program VivaHX follows is a folder here. The folder's name is the project's id,
used in its address on the site (`/software/<id>.html`): lowercase letters, digits and dashes.

```
projects/<id>/
  project.toml       what it is, where it's released, and who may edit its page   (maintainers)
  page.json          tagline, links, and the screenshots in order with captions   (developers)
  about.md           the About text, in Markdown                                  (developers)
  screenshots/       the screenshot images                                        (developers)
  highlights/<v>.md  a write-up for one release, shown above its notes            (developers)
  releases/<v>.toml  releases that aren't on GitHub                               (maintainers)
```

"Developers" means the project's own developers, through the editor, with every change reviewed;
maintainers can change any of it too. Everything the bots collect (releases, GitHub info) is kept
apart, in `data/`.

## project.toml

```toml
name = "Mobius"
category = "Servers"          # Clients, Servers, Trackers, Bots or Misc
github = "jhalter/mobius"     # follow its GitHub releases (leave out if it isn't on GitHub)
description = "A Hotline server written in Go."
homepage = "http://..."       # optional
platforms = "Mac, Windows, Linux"   # optional
skip_files = ["*_checksums.txt"]    # optional: release files not to list
prereleases = false           # optional: true to include prereleases
no_posts = false              # optional: true for no news post on a new release

discord_editors = ["123456789012345678"]
github_editors = ["someone"]
```

To add a project, make a new folder with a `project.toml` (or recommend it from the editor, which
does exactly that as a pull request). Its releases and GitHub info are picked up at the next
release check, every 6 hours.

## Who can edit a project's page

Developers sign in to the editor at https://vivahx.com/editor/ and can change their project's
tagline, links, About text and screenshots, post news about it, and write up its releases. Every
change arrives as a pull request; nothing is published until a maintainer merges it.

- **GitHub:** anyone who can push to the project's own GitHub repo, and anyone in its
  `github_editors`.
- **Discord:** anyone with the Client Dev, Server Dev or Tracker Dev role on Hotline HQ **and**
  listed in its `discord_editors`. In Discord, turn on Developer Mode (Settings > Advanced), then
  right-click the person and choose **Copy User ID**.

Adding someone takes effect about a minute after the change is merged, when the site is rebuilt.
Someone who isn't linked yet can ask from the editor: it opens an issue labeled
**editor-access** with the line to add.

This is separate from access to this GitHub repo itself. Editors don't need any; giving someone
write access to the repo would let them merge changes without review.

## page.json

Every field is optional.

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

Until a project has an `about.md` or screenshots of its own, the start of its README and up to
three screenshots from it are used. A release write-up's file name is the version in lowercase
with dashes: `v2.3.2` is `highlights/v2-3-2.md`.

## Releases that aren't on GitHub

One file per version in `releases/`:

```toml
version = "1.8.5"
title = "Hotline Server 1.8.5"
date = 2002-01-01
notes = """
What changed. Markdown works.
"""

[[files]]
name = "hlserver_ppc_185.sit"   # optional, taken from the address if left out
url = "http://example.com/hlserver_ppc_185.sit"
```

Downloads link to each `url`, so it has to keep working. Classic Mac software should use a plain
`http://` address so old browsers can download it.

## All projects

<!-- projects:start (written by vivahx/project_index.py; don't edit by hand) -->
60 projects.

| Project | Category | Latest release | Source | Editors |
|---|---|---|---|---|
| [Decline](decline/) | Clients | 1.0-build-11 (2025-09-06) | [dubsdotla/decline](https://github.com/dubsdotla/decline) |  |
| [GtkHx](gtkhx/) | Clients | v1.4.0 (2026-09-26) | [mishan/gtkhx](https://github.com/mishan/gtkhx) | 1 |
| [Heidrun](heidrun/) | Clients | 1.6.0 (2026-10-04) | [franckjej/heidrun](https://github.com/franckjej/heidrun) |  |
| [Hot Lime](hot-lime/) | Clients |  | [hjcoda/hot-lime-client](https://github.com/hjcoda/hot-lime-client) |  |
| [Hotline (pennig)](pennig-hotline/) | Clients |  | [pennig/hotline](https://github.com/pennig/hotline) |  |
| [Hotline (SwiftUI)](hotline-swiftui/) | Clients | 1.0beta28 (2026-03-03) | [mierau/hotline](https://github.com/mierau/hotline) |  |
| [Hotline client (Common Lisp)](hotline-client-lisp/) | Clients |  | [floren/hotline-client](https://github.com/floren/hotline-client) |  |
| [Hotline for the browser](hotline-web/) | Clients |  | [jwheare/hotline](https://github.com/jwheare/hotline) |  |
| [Hotline Navigator](hotline-navigator/) | Clients | v0.3.0 (2026-09-05) | [fuzzywalrus/Hotline-Navigator](https://github.com/fuzzywalrus/Hotline-Navigator) | 1 |
| [HotStuff!](hotstuff/) | Clients |  | [website](http://macintoshgarden.org/apps/hotstuff) | 1 |
| [hx-ng](hx-ng/) | Clients |  | [mishan/hx-ng](https://github.com/mishan/hx-ng) |  |
| [Invigoration](invigoration/) | Clients | v2.3.3 (2026-09-30) | [tagban/invigoration](https://github.com/tagban/invigoration) | 1 |
| [Liteline](liteline/) | Clients |  | [website](https://agora.vespernet.net/) | 1 |
| [Mobius Client](mobius-client/) | Clients | v0.3.1 (2026-03-12) | [jhalter/mobius-hotline-client](https://github.com/jhalter/mobius-hotline-client) | 1 |
| [Obsession](obsession/) | Clients | v109.05-alpha (2024-10-09) | [tjohnman/Obsession](https://github.com/tjohnman/Obsession) |  |
| [rusty-hx](rusty-hx/) | Clients | 1.0.1 (2025-12-06) | [kangsterizer/rusty-hx](https://github.com/kangsterizer/rusty-hx) |  |
| [Senesco](senesco/) | Clients |  | [Rampant-ai/Senesco](https://github.com/Rampant-ai/Senesco) |  |
| [shx (I2P)](shx-i2p/) | Clients |  | [orignal/shx-i2p](https://github.com/orignal/shx-i2p) |  |
| [SilverWing](silverwing/) | Clients |  | [HaikuArchives/SilverWing](https://github.com/HaikuArchives/SilverWing) |  |
| [Zephyr](zephyr/) | Clients |  | [website](https://agora.vespernet.net/) | 1 |
| [Heidrun Server](heidrun-server/) | Servers | 1.5.1 (2026-09-16) | [franckjej/heidrun-server](https://github.com/franckjej/heidrun-server) |  |
| [Hotline Docker images](hotline-docker/) | Servers |  | [mishan/hotline-docker](https://github.com/mishan/hotline-docker) |  |
| [Hotline Server](hotline-server/) | Servers | 1.9.1 (2003-01-01) | [website](http://hlwiki.com/servers/) |  |
| [Hotline Server (NebuHiiEjamu)](nebu-hotline/) | Servers |  | [NebuHiiEjamu/Hotline](https://github.com/NebuHiiEjamu/Hotline) |  |
| [hxd-ng](hxd-ng/) | Servers |  | [mishan/hxd-ng](https://github.com/mishan/hxd-ng) |  |
| [Janus](janus/) | Servers |  | [website](https://agora.vespernet.net/) | 1 |
| [jshxd](jshxd/) | Servers |  | [Schala/jshxd](https://github.com/Schala/jshxd) |  |
| [Lemoniscate](lemoniscate/) | Servers | v0.1.7 (2026-04-17) | [bourbonicfisky/lemoniscate](https://github.com/bourbonicfisky/lemoniscate) | 1 |
| [mhxd](mhxd/) | Servers |  | [website](http://hlwiki.com/servers/) |  |
| [Mobius](mobius/) | Servers | v0.23.1 (2026-08-23) | [jhalter/mobius](https://github.com/jhalter/mobius) | 1 |
| [phxd](phxd/) | Servers |  | [dcwatson/phxd](https://github.com/dcwatson/phxd) |  |
| [phxd (with IRC)](phxd-irc/) | Servers |  | [kangsterizer/phxd](https://github.com/kangsterizer/phxd) |  |
| [Pitbull Pro](pitbull/) | Servers |  | [website](http://ubersoft.org/pitbullserver/) |  |
| [rhxd](rhxd/) | Servers |  | [DaCodeChick/rhxd](https://github.com/DaCodeChick/rhxd) |  |
| [shxd (I2P)](shxd-i2p/) | Servers |  | [orignal/shxd-i2p](https://github.com/orignal/shxd-i2p) |  |
| [zhxd](zhxd/) | Servers |  | [Schala/zhxd](https://github.com/Schala/zhxd) |  |
| [Argus](argus/) | Trackers |  | [website](https://agora.vespernet.net/) |  |
| [hltracker (Visual Basic)](hltracker-vb/) | Trackers |  | [codebox/hltracker](https://github.com/codebox/hltracker) |  |
| [Magnetron](magnetron/) | Trackers | v0.4.0 (2026-01-03) | [benabernathy/magnetron](https://github.com/benabernathy/magnetron) |  |
| [Heidrun Spirit](heidrun-spirit/) | Bots | 1.0.0 (2026-06-03) | [franckjej/heidrun-spirit](https://github.com/franckjej/heidrun-spirit) |  |
| [Hotline Discord Bridge](hotline-discord-bridge/) | Bots | 1.0.0a (2026-04-08) | [tagban/hotline_discord_bridge](https://github.com/tagban/hotline_discord_bridge) |  |
| [Hotline2IRCRouter](hotline2irc/) | Bots |  | [JustinTArthur/Hotline2IRCRouter](https://github.com/JustinTArthur/Hotline2IRCRouter) |  |
| [Caps](caps/) | Misc |  | [hansipie/Caps](https://github.com/hansipie/Caps) |  |
| [CreateList](createlist/) | Misc |  | [fstltna/CreateList](https://github.com/fstltna/CreateList) |  |
| [Heidrun protocol and CLI](heidrun-protocol/) | Misc | 1.0.0 (2026-06-21) | [franckjej/heidrun-protocol](https://github.com/franckjej/heidrun-protocol) |  |
| [hotline (Ruby gem)](hotline-ruby/) | Misc |  | [amoeba/hotline](https://github.com/amoeba/hotline) |  |
| [Hotline 1.9.2 banner patch](hotline-192-banner-patch/) | Misc |  | [jhalter/hotline-client-1.9.2-banner-patch](https://github.com/jhalter/hotline-client-1.9.2-banner-patch) |  |
| [Hotline icons](hotline-icons/) | Misc |  | [tagban/hotline_icons](https://github.com/tagban/hotline_icons) |  |
| [Hotline IM (HIM)](hotline-im-him/) | Misc | v0.2.0 (2026-10-02) | [tagban/him](https://github.com/tagban/him) | 1 |
| [Hotline Modern](hotline-modern/) | Misc | v0.9.1 (2026-06-04) | [Oli97430/hotline-modern](https://github.com/Oli97430/hotline-modern) |  |
| [Hotline protocol notes (fogWraith)](fogwraith-hotline/) | Misc |  | [fogWraith/Hotline](https://github.com/fogWraith/Hotline) | 1 |
| [Hotline WebSocket bridge](hotline-ws-bridge/) | Misc |  | [jhalter/hotline-ws-bridge](https://github.com/jhalter/hotline-ws-bridge) |  |
| [Hotline Wireshark dissector](hotline-wireshark/) | Misc |  | [jhalter/hotline-wireshark-dissector](https://github.com/jhalter/hotline-wireshark-dissector) |  |
| [HotlineManagement](hotline-management/) | Misc |  | [fstltna/HotlineManagement](https://github.com/fstltna/HotlineManagement) |  |
| [hx-libs](hx-libs/) | Misc |  | [mishan/hx-libs](https://github.com/mishan/hx-libs) |  |
| [Neolith](neolith/) | Misc |  | [jyelloz/neolith](https://github.com/jyelloz/neolith) |  |
| [Net::Hotline](net-hotline/) | Misc |  | [pld-linux/perl-Net-Hotline](https://github.com/pld-linux/perl-Net-Hotline) |  |
| [Nyx](nyx/) | Misc |  | [website](https://agora.vespernet.net/) | 1 |
| [PullUpdates](pullupdates/) | Misc |  | [fstltna/PullUpdates](https://github.com/fstltna/PullUpdates) |  |
| [pushguest](pushguest/) | Misc |  | [fstltna/pushguest](https://github.com/fstltna/pushguest) |  |
<!-- projects:end -->
