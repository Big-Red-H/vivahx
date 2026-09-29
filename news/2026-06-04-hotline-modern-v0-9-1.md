+++
title = "Hotline Modern v0.9.1"
date = 2026-06-04T12:00:00Z
category = "Misc"
author = "VivaHX"
software = "hotline-modern"
release = "v0.9.1"
trusted = false
+++
## Hotline Modern v0.9.1 — Hotfix

### Corrections critiques
- **Tracker connectivity** : remplace `localhost` par `127.0.0.1` pour éviter les problèmes de résolution DNS IPv6
- **Bootstrap config** : valide la joignabilité d'un tunnel avant de le mettre en cache (timeout 3s)
- **URLs périmées** : nettoyage automatique des tunnels trycloudflare morts au démarrage
- **Tracker local préservé** : ne supprime jamais `localhost:9998` quand on ajoute un tunnel
- **public-config.json** : vidé pour ne plus pointer vers un tunnel mort

### APK Android
`hotline-modern.apk` — 4.0 MB, debug build

{{ downloads }}
