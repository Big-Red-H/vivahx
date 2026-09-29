+++
title = "Lemoniscate v0.1.7"
date = 2026-04-17T12:00:00Z
category = "Servers"
author = "VivaHX"
software = "lemoniscate"
release = "v0.1.7"
trusted = false
+++
# Lemoniscate v0.1.7 Release Notes

Persistent chat history. Your server now remembers what was said while clients were offline, and modern clients can page back through it. Storage is JSONL-based with no database dependency -- it works on Tiger PPC the same as modern macOS and Linux.

As writing this the only server that has chat history is Apple Media Archive as it's the only hotline server I run but hopefully you'll be the next!

This pairs well with the HOPE AEAD encryption from 0.1.6: if you have an encryption key configured, message bodies are encrypted at rest using ChaCha20-Poly1305 while metadata stays plaintext so the index and startup scan still work.

---

## Chat History

- **Server-side chat persistence** using append-only JSONL files under `<FileRoot>/ChatHistory/`. One file per channel (`channel-0.jsonl` for public chat, `channel-N.jsonl` for private chat rooms). No database, no external dependencies.
- **Cursor-based pagination** via `TranGetChatHistory` (transaction 700). Clients query with `before`/`after` cursors and a `limit` parameter. The server returns matching entries plus a `has_more` flag for paging. Queries are O(log n) via an in-memory binary-search index built at startup.
- **Capability negotiation** at login. The server advertises `HL_CAPABILITY_CHAT_HISTORY` (bit 4) so clients know history is available. Navigator v0.2.5+ renders the backlog automatically.

...

{{ downloads }}
