# FlyGit 2.0

Git-Deployments für WordPress — **automatisch, atomar, flottenweit.**

FlyGit installiert und aktualisiert Plugins & Themes direkt aus GitHub-Repositories. Version 2.0 ist ein kompletter Neubau mit Pull-Architektur: Jede Site prüft selbstständig auf neue Commits und aktualisiert sich — einzeln oder als ganze Flotte über ein zentrales Manifest.

## Warum 2.0?

| | v1 (Push/Webhook) | v2 (Pull + Manifest) |
|---|---|---|
| Update-Auslösung | Webhook pro Site × Plugin | Site pollt selbst (ETag, kostenlos) |
| Verpasster Webhook | Site bleibt veraltet | Nächster Check holt alles nach |
| GitHub-Webhook-Limit (20/Repo) | Blocker bei vielen Shops | Irrelevant |
| Update-Vorgang | Löschen → Kopieren (nicht atomar) | Stage → Verify → atomarer Swap → Rollback |
| Kaputter Download | Kann Live-Site zerstören | Wird vor dem Swap abgelehnt |
| Öffentlicher Endpoint | Pro Installation, z.T. ohne Auth | Einer, deaktiviert by default, immer authentifiziert |
| Tokens | Klartext in DB | AES-256-GCM verschlüsselt |
| Flotten-Rollout | Manuell pro Site | Ein Push ins Manifest-Repo |

## Features

- **Pull-Modell:** WP-Cron prüft GitHub in konfigurierbarem Intervall (15 min – täglich). Dank ETag-Conditional-Requests kosten Checks ohne Änderung **kein Rate-Limit und praktisch keine Serverlast** (304, kein Body).
- **Atomare Updates:** Neue Version wird in einem Staging-Verzeichnis entpackt und verifiziert (Plugin-/Theme-Header). Erst dann: `rename()`-Swap in Millisekunden. Schlägt etwas fehl → automatischer Rollback auf die vorherige Version.
- **Fleet-Manifest:** Ein zentrales JSON-Repo definiert den Soll-Zustand aller Shops. Neue Site aufsetzen = FlyGit installieren + Manifest aktivieren. Neues Plugin überall ausrollen = eine Zeile im Manifest.
- **Native Update-UI:** Anstehende Updates erscheinen zusätzlich unter Dashboard → Aktualisierungen, wie wordpress.org-Updates.
- **Sicherer Webhook (optional):** Ein Site-weiter Endpoint als Beschleuniger. Standardmäßig deaktiviert, HMAC-Pflicht, Payload kann keine Repo-URL injizieren, Deploy läuft asynchron (debounced) statt im Request.
- **Moderne Settings-Page:** Karten-Dashboard mit Update-Badges, Auto-Update-Toggles, Aktivitäts-Log, Copy-Buttons für Webhook-Setup.

## Installation

1. Repo nach `wp-content/plugins/flygit` klonen oder als ZIP installieren.
2. Plugin aktivieren.
3. **FlyGit** im Admin-Menü öffnen.
4. Optional: Globales GitHub-Token hinterlegen (Einstellungen) — nötig für private Repos, erhöht das API-Limit auf 5.000 Anfragen/h.

## Fleet-Manifest

`fleet-manifest.json` in einem eigenen Repo:

```json
{
  "version": 1,
  "plugins": [
    { "repo": "kevinheinrichs/fly-geo", "branch": "main" },
    { "repo": "kevinheinrichs/fly-cache", "branch": "main" }
  ],
  "themes": [
    { "repo": "kevinheinrichs/fly-theme", "branch": "main" }
  ],
  "sites": {
    "beauty-bazaar.de": {
      "exclude": [ "kevinheinrichs/fly-geo" ],
      "plugins": [ { "repo": "kevinheinrichs/bb-extra", "branch": "main" } ]
    }
  }
}
```

- `plugins` / `themes` gelten für **alle** Sites.
- `sites.<host>` ergänzt (`plugins`, `themes`) oder entfernt (`exclude`) Einträge pro Site (Match auf den Hostnamen der Site).
- Verschwindet ein Eintrag aus dem Manifest, wird er auf den Sites deinstalliert (nur manifest-verwaltete Einträge).

## Webhook (optional)

Für Updates in Sekunden statt beim nächsten Check:

1. Einstellungen → Webhook aktivieren, Secret kopieren.
2. GitHub-Repo → Settings → Webhooks → Add:
   - URL: `https://deine-site.de/wp-json/flygit/v1/sync`
   - Content type: `application/json`
   - Secret: das kopierte Secret
3. Fertig. FlyGit prüft die HMAC-Signatur, plant den Check asynchron und antwortet sofort.

Auch ohne Webhook bleibt alles aktuell — der Cron-Check ist die Basis, der Webhook nur Beschleuniger.

## Sicherheit

- Webhook: deaktiviert by default, Secret-Pflicht, HMAC-Verifikation, keine Repo-Injection über Payload, asynchrone Verarbeitung.
- Tokens: AES-256-GCM-verschlüsselt gespeichert (Schlüssel aus WP-Salts — DB-Leak allein reicht nicht).
- Pakete werden vor dem Deploy verifiziert (gültige Plugin-/Theme-Header), sonst abgelehnt.
- Staging-Verzeichnis ist gegen Web-Zugriff gesperrt (.htaccess + index.php).
- Alle Admin-Aktionen: `manage_options` + Nonce.

## Anforderungen

- WordPress 6.0+, PHP 7.4+ (getestet bis 8.3)
- Ausgehende HTTPS-Verbindungen zu api.github.com
- ZipArchive empfohlen (Fallback: WP unzip_file)

## Version History

### 2.0.0
- Kompletter Neubau: Pull-Architektur mit ETag-Conditional-Requests
- Atomare Deployments mit Staging, Verifizierung und Rollback
- Fleet-Manifest für zentralen Flotten-Rollout
- Integration in die native WordPress-Update-UI
- Ein gesicherter Webhook-Endpoint (HMAC, debounced, async) statt einem pro Installation
- Token-Verschlüsselung (AES-256-GCM)
- Neue Settings-Page: Karten-Dashboard, Tabs, Aktivitäts-Log
- Performance: eine autoloaded Option, Assets nur auf der eigenen Seite, Registry/Log ohne Autoload

### 1.1.0 / 1.0.0
- Ursprüngliche Push/Webhook-Version (Formular-Installs, Webhook pro Installation)
