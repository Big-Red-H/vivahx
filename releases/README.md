# Custom releases

Software that isn't released on GitHub gets its releases here: one file per version, at
`releases/custom/<software id>/<version>.toml`. The software itself must be in
`config/software.toml` first.

```toml
version = "1.8.5"
title = "Hotline Server 1.8.5"
date = 2002-01-01
notes = """
What changed. Markdown works.
"""

[[files]]
name = "hlserver_ppc_185.sit"   # optional, taken from the address if left out
url = "http://example.com/hlserver_ppc_185.sit"
```

The next release check copies each file to the VivaHX file mirror and writes a news post for the
new version. The `url` only has to work until then.
