+++
title = "Hotline (SwiftUI) 1.0beta31"
date = 2026-11-14T12:00:00Z
category = "Clients"
author = "VivaHX"
software = "hotline-swiftui"
release = "1.0beta31"
trusted = false
+++
Happy Friday the 13th!

And thank goodness this release has a privacy focus for your protection. Secure connections and a privacy section in Settings, among other things. Take care of yourself. :)

### New in b31
- Secure connections! Hotline now connects to servers over TLS when they support it (like servers running Mobius), so your password and messages are encrypted. It tries a secure connection first, and uses a regular one if a server doesn't offer it. Bookmarks can require a secure connection, and hotlines:// links connect securely. Once you've connected to a server securely, Hotline won't connect to it any other way without asking, and it asks before trusting a server whose certificate has changed.
- Starting with b31, Hotline can fully update itself! When a new build is out, Hotline shows what's new, and Install Update downloads _and_ installs it, then reopens Hotline. Updates are signed, so Hotline only installs genuine releases. Check for Updates in the Hotline menu checks right away.
- Release Notes browser in the Help menu.
- Unread counts in the sidebar, and on Hotline's icon in the Dock.
- More notifications: public chat, private chats and invitations, broadcasts, new message board posts, and when an administrator disconnects you. Pick the ones you want in Settings ▸ Notifications.

...

{{ downloads }}
