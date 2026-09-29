+++
title = "Heidrun protocol and CLI 1.0.0"
date = 2026-06-21T12:00:00Z
category = "Misc"
author = "VivaHX"
software = "heidrun-protocol"
release = "1.0.0"
trusted = false
+++
First stable release of the Heidrun Hotline protocol package.

Ships `HeidrunCore` (wire types + Network.framework client), `HeidrunNIOClient` (cross-platform SwiftNIO client), and the `heidrun` CLI.

Capabilities negotiated via `DATA_CAPABILITIES` (0x01F0):
- **Large-file transfers (>4 GiB)** — 64-bit sizes, 24-byte HTXF handshake, 64-bit FFO fork headers; single-file and folder.
- **UTF-8 text encoding** — opt-in UTF-8 for all strings (chat, names, news, …); macOS Roman otherwise.

Plus Heidrun extensions (0xE000 band): emoji avatars, resource-fork framed downloads. Self-signed TLS, tracker registration/listing, news, file transfers.

The wire format is additive and backward-compatible: every extension degrades gracefully against peers that don't negotiate it. See docs/PROTOCOL-EXTENSIONS.md.

{{ downloads }}
