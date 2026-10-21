# Mikesoft TeamVault

Private Dokumentenverwaltung für WordPress-Teams, mit geschütztem Speicher und granularer Zugriffskontrolle

[![CI](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml/badge.svg)](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml)
[![WordPress.org](https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault?label=WordPress.org)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)

![TeamVault-Banner für den Plugin-Eintrag auf WordPress.org](.wordpress-org/assets/banner-772x250.png)

[English](README.md) · [Italiano](README.it.md) · [Français](README.fr.md) · [Español](README.es.md) · **Deutsch**

Mikesoft TeamVault fügt einen privaten Dokumenten-Arbeitsbereich im WordPress-Adminbereich hinzu, für Teams, Agenturen, Partner und Back-Office-Bereiche, die kontrollierten Zugriff auf gemeinsam genutzte Dateien benötigen. Dokumente werden außerhalb der Mediathek gespeichert und über authentifizierte WordPress-Handler statt über öffentliche Medien-URLs ausgeliefert. Dieses Repository ist der öffentliche Quellcode-Spiegel: Installationen und Updates kommen von [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/), und der Benutzersupport läuft über das [offizielle Support-Forum](https://wordpress.org/support/plugin/mikesoft-teamvault/).

Mikesoft TeamVault ist ein unabhängiges Plugin; es ist weder mit WordPress, Automattic oder der WordPress Foundation verbunden noch von ihnen gesponsert oder unterstützt.

![TeamVault-Dateimanager mit Ordnerbaum, Dateikarten mit typbezogenen Symbolen und Bildvorschauen sowie dem Detailbereich mit Inline-Vorschau](.wordpress-org/assets/screenshot-1.jpg)

## Installation

1. Öffne im WordPress-Adminbereich `Plugins > Add New`.
2. Suche nach `Mikesoft TeamVault`.
3. Klicke auf `Install Now` und aktiviere das Plugin.
4. Öffne `TeamVault > Settings`, um Zugriffs-, Speicher- und Dateiregeln zu prüfen.

Manuelle Alternative: Lade das Versionspaket von [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/) herunter, lade es nach `wp-content/plugins/mikesoft-teamvault/` hoch und aktiviere das Plugin über den Plugin-Bildschirm.

## Schnellstart

1. Öffne `TeamVault > Settings` und prüfe den Speicherpfad, die erlaubten Dateitypen und das Upload-Limit.
2. Vergib die Capability `manage_private_documents` nur an Rollen oder Benutzer, die Zugriff auf den Vault benötigen.
3. Lege Gruppen an, wenn Berechtigungen Abteilungen oder Projektteams folgen sollen, und erstelle danach die Dokumentenordner.
4. Lade eine Datei aus der Raster- oder Listenansicht hoch und prüfe Vorschau- und Download-Verhalten mit einem Nicht-Administrator-Konto.

## Screenshots

| Ordnerberechtigungen | Suche im Vault | Speicherkontingente |
| :---: | :---: | :---: |
| [![Ordnerbezogene Zugriffskontrolle](.wordpress-org/assets/screenshot-2.jpg)](.wordpress-org/assets/screenshot-2.jpg) | [![Suche mit Dateityp-Badges](.wordpress-org/assets/screenshot-3.jpg)](.wordpress-org/assets/screenshot-3.jpg) | [![Kontingente pro Benutzer und Gruppe](.wordpress-org/assets/screenshot-4.jpg)](.wordpress-org/assets/screenshot-4.jpg) |
| **Gruppen** | **Aktivitätsprotokoll** | **Einstellungen** |
| [![Benutzergruppen](.wordpress-org/assets/screenshot-5.jpg)](.wordpress-org/assets/screenshot-5.jpg) | [![Audit-Protokoll](.wordpress-org/assets/screenshot-6.jpg)](.wordpress-org/assets/screenshot-6.jpg) | [![Plugin-Einstellungen](.wordpress-org/assets/screenshot-7.jpg)](.wordpress-org/assets/screenshot-7.jpg) |

## Funktionen

- Privater Speicher außerhalb des normalen Mediathek-Workflows.
- Zugriffskontrollen über Capability, optionale Whitelist, Benutzer, Gruppen und Ordner, mit Vererbung und expliziten Überschreibungen in Unterordnern.
- Ordneroperationen (Erstellen, Umbenennen, Verschieben, Löschen) und validierte Drag-and-Drop-Uploads mit Inline-Vorschauen, einschließlich PDFs.
- Reiner Vorschauzugriff, der Downloads und ZIP-Exporte blockiert.
- Speicherkontingente pro Benutzer und pro Gruppe, die vor dem Upload durchgesetzt werden.
- Zugriffsberichte, Aktivitätsprotokollierung, CSV-Export und ordnerbewusste E-Mail-Benachrichtigungen für Uploads, Downloads, Löschungen und verweigerten Zugriff.
- ZIP-Export, Bereinigung verwaister Dateien und Wartungswerkzeuge zur Neuindexierung des Speichers.
- Admin-Oberflächen in Englisch, Italienisch, Französisch, Spanisch und Deutsch.

Alle Governance-Funktionen sind im kostenlosen Plugin enthalten.

## Voraussetzungen

- WordPress 6.0 oder neuer.
- PHP 8.0 oder neuer.
- Ein beschreibbarer Speicherpfad für private Dokumente.
- `ZipArchive` auf dem Server für die Exportfunktionen.

## Konfiguration

Öffne `TeamVault > Settings` nach der Aktivierung. Die wichtigsten Einstellungen sind:

| Einstellung | Zweck | Standard |
| --- | --- | --- |
| Oberflächensprache | Folgt der WordPress-Sprache oder erzwingt eine unterstützte Sprache | Automatisch |
| Benutzer-Whitelist | Fügt eine ausdrückliche Benutzerliste zusätzlich zur erforderlichen Capability hinzu | Deaktiviert |
| Erlaubte Dateitypen | Beschränkt Uploads auf die konfigurierten Dokument-, Bild-, Archiv-, Audio- und Videotypen | Sichere integrierte Liste |
| Maximale Dateigröße | Wendet ein Upload-Limit des Plugins zusätzlich zu PHP- und Webserver-Limits an | 50 MB |
| PDF-Vorschau | Erlaubt authentifizierte Inline-PDF-Vorschauen | Aktiviert |
| Aktivitätsprotokoll | Zeichnet Dokumentoperationen für Audit und Berichte auf | Aktiviert |
| Daten bei Deinstallation löschen | Entfernt beim Deinstallieren Plugin-Tabellen, Optionen, Capabilities und markierten Speicher | Deaktiviert |

Gruppen, Ordnerberechtigungen, Kontingente, Berichte und Benachrichtigungen haben eigene Seiten im Adminbereich. Konfiguriere den Zugriff, bevor du Nicht-Administratoren einlädst.

Die Laufzeitkonfiguration liegt in WordPress-Optionen; TeamVault liest keine Einstellungen aus Umgebungsvariablen oder einer `.env`-Datei des Repositorys. Datenbank-Zugangsdaten und Salts gehören in die `wp-config.php` der Website.

## Datenschutz und Sicherheit

Private Dateien werden nie über öffentliche Medien-URLs ausgeliefert: Downloads und Vorschauen laufen über authentifizierte WordPress-Handler mit privaten No-Cache-Antworten.

- Der Vault-Zugriff nutzt die Capability `manage_private_documents`; neue Aktivierungen vergeben sie nur an Administratoren.
- Der optionale Whitelist-Modus fügt eine zweite Autorisierungsebene für ausgewählte Benutzer hinzu.
- Ordnerberechtigungen beschränken den Zugriff auf die gewährten Benutzer, Gruppen und Aktionen, mit Vererbung von übergeordneten Ordnern. Ordner ohne Regeln behalten das capability-basierte Verhalten, und Administratoren behalten immer vollen Zugriff.
- Einstellungen, Gruppen, Kontingente, Benachrichtigungen, Berichte, Protokolle, Whitelist-Verwaltung, Wartungswerkzeuge und Datenkontrollen bei der Deinstallation erfordern `manage_options`.
- Der Standardspeicher ist `wp-content/uploads/private-documents/` und wird durch serverseitige Deny-Dateien geschützt: die generierte `.htaccess` unter Apache und LiteSpeed, die `web.config` unter IIS und eine äquivalente Nginx-Regel, die direkte Anfragen an diesen Pfad verweigert.
- Für besonders sensible Deployments ist ein eigener Speicherpfad außerhalb des öffentlichen Webroots zu bevorzugen. Das Speicher-Widget zeigt nur die Nutzung durch TeamVault.

Siehe [`SECURITY.md`](SECURITY.md) für die Meldung von Schwachstellen.

## Fehlerbehebung

### ZIP-Export schlägt fehl

Der Export benötigt die PHP-Erweiterung `ZipArchive` und einen beschreibbaren Speicherpfad. Aktiviere die Erweiterung oder bitte den Hosting-Anbieter darum, und versuche es erneut.

### Ein Administrator ist nach Aktivierung der Whitelist ausgesperrt

Der Whitelist-Modus ist eine Positivliste. Halte das aktuelle Administrator-Konto in der Liste der erlaubten Benutzer, bevor du die Einstellungen speicherst.

### Nach einer Website-Migration fehlen Dateien

Eine ohne den privaten Speicherordner migrierte Website behält die TeamVault-Datensätze in der Datenbank, während die Originaldateien fehlen. Kopiere das Speicherverzeichnis bei Migrationen und nutze danach die Bereinigungs- und Neuindexierungswerkzeuge im Einstellungsbildschirm, nachdem du die Kopie geprüft hast.

### Uploads werden abgelehnt

Prüfe die erlaubten Dateitypen, die maximale Dateigröße und die PHP-Upload-Limits. TeamVault validiert Dateiendung, erkannten MIME-Typ, Größe und gefährliche Inhaltsmuster, bevor eine Datei gespeichert wird, und meldet den erkannten Inhaltstyp bei einer Abweichung.

## Entwicklung

Die Entwicklung erfordert PHP 8.2 oder neuer für PHPUnit 11, Composer 2, Node.js 24 für das JavaScript- und Plugin-Check-Tooling sowie Git; das Plugin selbst unterstützt PHP 8.0 oder neuer.

Installiere die gesperrten Entwicklungsabhängigkeiten und führe die standardmäßigen PHP-Prüfungen aus:

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer audit --locked
composer ci
```

`composer ci` führt PHP-Lint und die PHPUnit-Suite aus. GitHub Actions führt zusätzlich WordPress Plugin Check gegen ein sauberes Laufzeitpaket aus.

Zusätzliche Prüfungen:

```bash
node --check assets/js/admin-app-core.js
node --check assets/js/admin-app-governance.js
node --check assets/js/admin-app.js
node --check assets/js/admin-notice-dismiss.js
node --test tests/plugin-check-output.test.mjs
```

Es gibt keinen Asset-Kompilierungsschritt: Laufzeit-PHP, CSS und JavaScript werden als Quellcode versioniert. `git archive --format=zip --prefix=mikesoft-teamvault/ --output=mikesoft-teamvault.zip HEAD` erzeugt ein Paket aus einer committeten Revision unter Verwendung der Export-Ausschlüsse aus `.gitattributes`.

Hinweise für Maintainer und Entwickler:

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - Hooks und Filter
- [`docs/developer/i18n.md`](docs/developer/i18n.md) - Übersetzungs-Workflow
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - lokale Entwicklung und manuelle QA
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - Release und WordPress.org-Deployment
- [`docs/maintainer/security-review.md`](docs/maintainer/security-review.md) - letzte Sicherheitsüberprüfung

## Support

- Endbenutzer-Support: [WordPress.org-Support-Forum](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- E-Mail: [teamvault@mikesoft.it](mailto:teamvault@mikesoft.it)
- Website: [mikesoft.it](https://mikesoft.it)
- Sicherheitsmeldungen: siehe [`SECURITY.md`](SECURITY.md), niemals ein öffentliches Issue.

## Mitwirken

Dieses Repository ist der öffentliche Quellcode-Spiegel des Plugins. Externe Pull Requests werden nicht angenommen; siehe [`CONTRIBUTING.md`](CONTRIBUTING.md) für die Melde-Policy und den Maintainer-Workflow.

## Lizenz

GPL-2.0-or-later - siehe [`LICENSE`](LICENSE).

---

Maintained by [Michael Gasperini (Mikesoft)](https://mikesoft.it). Wenn dieses Projekt für dich nützlich ist, erwäge, [seine Wartung zu unterstützen](https://github.com/sponsors/TheStreamCode).

