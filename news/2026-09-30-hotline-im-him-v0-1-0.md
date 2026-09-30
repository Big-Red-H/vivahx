+++
title = "Hotline IM (HIM) v0.1.0"
date = 2026-09-30T12:00:00Z
category = "Misc"
author = "VivaHX"
software = "hotline-im-him"
release = "v0.1.0"
trusted = false
+++
The first release of HIM, the Hotline Instant Messenger: a late-90s AIM-style buddy list for the Hotline IM network. It starts out on VesperNet; **Get a Screen Name** on the Sign On window makes an account.

- Buddy List, IM windows, away messages and auto-responses, offline messages, delivered/read receipts
- HOPE secure sign-on, with ChaCha20-Poly1305 encryption when the server offers it
- Buddy Icons (the Buddy Icons extension, Janus 2.0.16+), including BadassBuddy zips as a searchable gallery
- AIM-style smileys, door sounds, and Hotline servers as chat rooms

| System | File |
|---|---|
| macOS, Apple silicon | `HIM_0.1.0_aarch64.dmg` (signed and notarized) |
| macOS, Intel | `HIM_0.1.0_x64.dmg` (signed and notarized) |
| Windows | `HIM_0.1.0_x64-setup.exe`, or the `.msi` |
| Linux | `HIM_v0.1.0_x86_64.flatpak` / `_aarch64.flatpak`, or the `.deb` |

The flatpak needs the GNOME 47 runtime: `flatpak install flathub org.gnome.Platform//47`, then `flatpak install --user HIM_v0.1.0_x86_64.flatpak`.

{{ downloads }}
