+++
title = "Hotline Navigator v0.3.1"
date = 2026-10-28T12:00:00Z
category = "Clients"
author = "VivaHX"
software = "hotline-navigator"
release = "v0.3.1"
trusted = false
+++
# v0.3.1

This release cleans up saved logins and date formatting: bookmarks keep their password settings when they cross between Rust and the interface, reconnects retrieve saved credentials, and news timestamps follow your system’s locale and time zone.

Builds also move entirely onto GitHub-hosted runners. macOS, Windows, Linux, Android, and iOS / iPadOS packages are built in Actions without depending on a personal Mac mini or remote build machine. Downloads get a smaller async stack footprint, with additional regression coverage on Windows.

## New Features

- **GitHub-hosted builds for every platform** — macOS universal, Windows x64, Linux x64 and ARM64, Android, and unsigned iOS / iPadOS packages are available below.
- **Individual platform rebuilds** — The Apple and Android workflow can build all three platforms together or retry just macOS, iOS, or Android.

## Bugfixes and Improvements

- **Dates that follow your system** — Threaded news and file-info dates use your locale and time zone instead of a fixed US format.
- **Saved credentials survive reconnects** — Bookmark password flags now match the interface’s field names, and manual and automatic reconnects retrieve passwords from encrypted storage. Explicitly empty passwords remain valid.
- **Lower download stack usage** — The streaming copy buffer moves to the heap, reducing the size of the async download future. Windows regression tests cover multi-chunk transfers.

...

{{ downloads }}
