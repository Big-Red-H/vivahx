+++
title = "Lemoniscate v0.1.8"
date = 2026-11-03T12:00:00Z
category = "Servers"
author = "VivaHX"
software = "lemoniscate"
release = "v0.1.8"
trusted = false
+++
### Added

- **Colored nicknames**: Servers can deliver nickname colors to color-aware clients via the new `HL_FIELD_USER_COLOR` (0x0500) field on user-change broadcasts, and optionally honor colors clients set themselves (`TranSetClientUserInfo`, 304). New `ColoredNicknames:` block in `config.yaml` (and `ColoredNicknames*` plist keys): `Delivery` (off / auto / always), `HonorClientColors`, `DefaultAdminColor`, `DefaultGuestColor`. Per-account `Color` overrides the defaults. GUI: new Colored Nicknames settings section and a color row in the Account Editor.
- **Auto-ban for repeat offenders**: An IP that trips the per-IP rate limiter 10 times within 5 minutes is added to the persistent banlist and dropped at accept, before any read or TLS handshake. Controlled by the new `AutoBanEnabled` setting (default on), with a matching GUI checkbox; the Bans tab gains a Refresh button to show bans written by the running server.
- **Chat history settings in the GUI**: All nine `ChatHistory*` keys are now editable and round-trip through GUI saves (previously a GUI save silently dropped them), including a one-click *Generate Key…* for encryption at rest.

### Fixed


...

{{ downloads }}
