+++
title = "Hotline Navigator v0.3.0"
date = 2026-09-05T12:00:00Z
category = "Clients"
author = "VivaHX"
software = "hotline-navigator"
release = "v0.3.0"
trusted = false
+++
# v0.3.0

This release fills in a substantial part of Navigator’s Hotline protocol support: interrupted transfers can resume, entire folders can be transferred, modern trackers are supported, and compatible servers can display custom GIF avatars.

Text handling also gets a deeper cleanup. Navigator now negotiates UTF-8 consistently across live chat, history, news, and file names, while keeping MacRoman compatibility with classic servers. Private conversations gain media attachments, file sizes and dates are handled more accurately, and saved passwords move into encrypted storage.

## New Features

- **Resumable file transfers** — Interrupted downloads retain partial data and can resume when the remote file still matches. Uploads support classic resume offsets and modern partial-file verification, restarting when a safe resume is unavailable.
- **Folder transfers** — Download folders from the file browser or use **Upload Folder** on desktop. Nested and empty folders are supported, with checks that keep received paths inside the destination folder.
- **Modern trackers and server discovery** — Trackers support v1, v2, and v3, including authenticated listings and optional certificate-validated TLS. Server bookmark information can display advertised ports, transports, and features.
- **Custom GIF avatars** — Set or clear an avatar on compatible servers. Avatars appear in user lists, user details, and grouped chat, with classic icons as a fallback.

...

{{ downloads }}
