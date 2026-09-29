# News posts

Each post is one Markdown file here, named `YYYY-MM-DD-short-title.md`. It starts with a header
between `+++` lines:

```
+++
title = "Mobius 1.0 is out"
date = 2026-10-01T12:00:00Z
category = "Servers"     # General News, Clients, Servers, Trackers, Bots or Misc
author = "tagban"
+++
The post, in **Markdown**. [Links](http://example.com) and lists work.
```

To post from the browser: on GitHub, open this folder, click **Add file > Create new file**,
and commit to `main`. The site updates in about a minute. Or open a **News post** issue and a
maintainer publishes it by adding the **publish** label.

Posts about new releases are written automatically. Edit or delete them like any other post. A
release post keeps `{{ downloads }}` where its download links go, and `trusted = false`, which
shows only plain formatting (release notes come from other people's pages). Posts you write
yourself can use HTML too.
