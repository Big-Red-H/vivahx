+++
title = "Hotline (SwiftUI) 1.0beta33"
date = 2026-11-20T12:00:00Z
category = "Clients"
author = "VivaHX"
software = "hotline-swiftui"
release = "1.0beta33"
trusted = false
+++
Hey all,

Some nice changes in this release. Some further work on security, like around image previews where we now only allow image previews from registered domains and over secure connections (even when you have said to allow all sites). Also now support for animated GIFs in chat and a brand new slash commands interface.

### New
- Animated GIFs! GIFs now play in chat, private chat, news and the message board. Links to Giphy, Tenor and Imgur pages show their GIF, and a link to a picture now shows as just the picture. Turn off Play GIFs automatically in Settings ▸ Chat and they'll only play when you hover over them.
- Slash commands in chat and private chat: /me, /msg, /away and /back, /name, /icon, /invite, /topic, /leave, /info, /broadcast, /kick and /ban.
- Ban someone right from the userlist menu.
- Release notes now look as they do on GitHub, with pictures, videos and GIFs, in the update window and the Release Notes browser.
- Copy Image and Save Image… in a picture preview's menu.
- Previews for AVIF and JPEG XL pictures.
- Encrypted Servers, in Settings ▸ Privacy: select servers to forget several at once, and Show Certificate shows the certificate a server gave you, for any you've connected to since updating.

### Changed
- Pictures come in more smoothly: a new picture's box shows straight away with its link in it, takes the picture's shape as soon as it starts loading, and shows a spinner if it's slow, so chat doesn't jump around as pictures load.

...

{{ downloads }}
