+++
title = "Heidrun Spirit 1.0.0"
date = 2026-06-03T12:00:00Z
category = "Bots"
author = "VivaHX"
software = "heidrun-spirit"
release = "1.0.0"
trusted = false
+++
Initial public release.

A standalone Hotline chatterbot built on the 1998 [MegaHAL](https://megahal.alioth.debian.org/) Markov engine (© Jason Hutchens, GPL-2.0). Swift revival of the 2002 *Heidrun's Spirit* plug-in — connects to any Hotline server, replies to chat, learns as it reads, persists its brain across restarts.

Cross-platform via [heidrun-protocol](https://github.com/franckjej/heidrun-protocol)'s `HeidrunNIOClient`: runs on **macOS** (launchd) and **Linux** (Docker).

See [`README.md`](https://github.com/franckjej/heidrun-spirit#readme) for configuration. `deploy/` ships a LaunchDaemon plist; `Dockerfile` + `docker-compose.yml` cover the Linux path.

{{ downloads }}
