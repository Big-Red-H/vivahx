+++
title = "Heidrun Server 1.5.1"
date = 2026-09-16T12:00:00Z
category = "Servers"
author = "VivaHX"
software = "heidrun-server"
release = "1.5.1"
trusted = false
+++
### Fixed
- **Single-file downloads always ship the flattened file object.** The server
  sent a bare data fork unless the Heidrun-only `resourceForkSupport` flag was
  negotiated, so classic clients (and Heidrun ≤ 1.4 against classic servers)
  misread downloads. Fresh downloads, resumes (data-fork remainder + full
  resource fork) and large files now all frame per the Hotline spec; the flag
  is a no-op.
### Notes
- Pins `heidrun-protocol` **1.1.1** (FILP decode on the client side of the same fix).

{{ downloads }}
