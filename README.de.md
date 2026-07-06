# Mikesoft TeamVault

[![CI](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml/badge.svg)](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml)
[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault?label=WordPress.org)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![WordPress Tested](https://img.shields.io/wordpress/plugin/tested/mikesoft-teamvault?label=Tested%20up%20to)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-db61a2?logo=githubsponsors&logoColor=white)](https://github.com/sponsors/TheStreamCode)

[English](README.md) · [Italiano](README.it.md) · [Français](README.fr.md) · [Español](README.es.md) · **Deutsch**

Privater Dokumenten-Arbeitsbereich für WordPress-Teams, Agenturen und Betriebsabläufe, die eine kontrollierte Dateifreigabe außerhalb der Mediathek benötigen.

Aktuelle Plugin-Version: `3.2.1`.

**Über 2.000 Downloads insgesamt** auf WordPress.org, mit täglich Dutzenden neuer Downloads.

Wenn TeamVault für Sie nützlich ist, ziehen Sie in Betracht, [das Projekt auf GitHub zu unterstützen](https://github.com/sponsors/TheStreamCode) — es wird kostenlos entwickelt und gepflegt, und Förderungen helfen, es am Leben zu erhalten.

## Überblick

Mikesoft TeamVault fügt einen privaten Dokumenten-Arbeitsbereich innerhalb des WordPress-Adminbereichs hinzu.
Es wurde für Teams konzipiert, die sensible Dateien organisieren, in der Vorschau anzeigen, exportieren und teilen müssen, ohne sie über die üblichen Mediathek-URLs preiszugeben.

Dateien werden in geschütztem Speicher abgelegt und über authentifizierte WordPress-Handler ausgeliefert, anstatt über öffentliche Medien-URLs.

![TeamVault Dateimanager-Oberfläche](.wordpress-org/assets/screenshot-1.jpg)

Typische Anwendungsfälle sind:

- interne Unternehmensdokumente
- Dokumentenübergabe von Agentur an Kunde aus dem WordPress-Adminbereich
- Dateiaustausch mit Partnern oder Lieferanten
- Back-Office-Archive, die aus der öffentlichen Mediathek herausgehalten werden sollen

Zu den Kernfunktionen gehören:

- privater Speicher außerhalb des normalen Mediathek-Workflows
- geteilter Zugriff für autorisierte interne Benutzer
- Erstellen, Umbenennen, Verschieben und Löschen von Ordnern
- Drag-and-Drop-Uploads mit Dateivalidierung
- Inline-Vorschau für unterstützte Dateitypen, einschließlich PDFs
- ZIP-Export für Ordner oder die gesamte Dokumentenbibliothek
- Aktivitätsprotokollierung zur betrieblichen Nachvollziehbarkeit
- Wartungswerkzeuge zur Bereinigung verwaister Dateien und zur Neuindexierung des Speichers

Governance-Funktionen (alle kostenlos, seit 2.6):

- TeamVault-Gruppen zur Organisation von Benutzern in Abteilungen oder Teams, unabhängig von WordPress-Rollen
- ordnerbezogene Berechtigungen mit granularen Aktionen (Anzeigen, Hochladen, Herunterladen, Löschen, Verwalten) für Benutzer und Gruppen, mit Vererbung und expliziten Überschreibungen für untergeordnete Ordner
- reiner Vorschauzugriff, der das Anzeigen ohne Herunterladen oder ZIP-Export erlaubt
- Speicherkontingente pro Benutzer und pro Gruppe, die vor dem Hochladen durchgesetzt werden
- Zugriffsberichte (wer was angesehen oder heruntergeladen hat) mit Filtern und einem CSV-Export des Aktivitätsprotokolls
- E-Mail-Benachrichtigungen für Ereignisse beim Hochladen, Herunterladen, Löschen und bei verweigertem Zugriff

## Neueste Version

Version `3.0.0` ist ein Meilenstein für Sicherheit und Zuverlässigkeit. Suchergebnisse werden nun durch die ordnerbezogene Berechtigungs-Engine gefiltert, sodass eingeschränkte Benutzer keine Dateinamen oder Metadaten mehr aus Ordnern entdecken können, die sie nicht anzeigen dürfen. Die generierte `.htaccess` für den Speicher verweigert den direkten Zugriff auf Apache 2.4 zusätzlich zu Apache 2.2 und IIS, und Speicherkontingente werden mit einer Datenbanksperre durchgesetzt, damit gleichzeitige Uploads ein Limit nicht gemeinsam überschreiten können. Downloads und Inline-Vorschauen erhalten HTTP-Range-Unterstützung (`Accept-Ranges` / `206 Partial Content`) für fortsetzbare Übertragungen und PDF-Viewer mit Bereichssuche bei großen Dateien. Der Dialog für Ordnerberechtigungen warnt nun, wenn Regeln existieren, das Stammverzeichnis jedoch keine hat, das Symbol im Admin-Menü entspricht dem nativen WordPress-Design, und das Admin-JavaScript wurde ohne Verhaltensänderung in fokussierte Module aufgeteilt.

Version `2.6` führte die kostenlose **Governance-Suite** für Dokumente ein: TeamVault-Gruppen, ordnerbezogene Berechtigungen mit Vererbung und granularen Aktionen (Anzeigen, Hochladen, Herunterladen, Löschen, Verwalten), reinen Vorschauzugriff, Speicherkontingente pro Benutzer und pro Gruppe, Zugriffsberichte mit CSV-Export, E-Mail-Benachrichtigungen. Bestehende Installationen sind nicht betroffen, da Ordner ohne Regeln das bisherige Verhalten beibehalten.

Warum Teams TeamVault einsetzen:

- es schafft einen dedizierten privaten Dokumentenbereich, anstatt die Mediathek zu überladen
- es ergänzt eine fähigkeitsbasierte Zugriffskontrolle mit einer optionalen Whitelist-Ebene sowie ordnerbezogenen Berechtigungen und Gruppen für eine feinere Governance
- es hält Export-, Wartungs- und Wiederherstellungsabläufe auf betriebliche Dateien fokussiert

## Anforderungen

- WordPress 6.0 oder neuer
- PHP 8.0 oder neuer
- Beschreibbarer Speicherpfad für private Dokumente
- `ZipArchive` auf dem Server verfügbar für Exportfunktionen

## Installation

### Empfohlen

Installieren Sie das Plugin aus dem [WordPress.org Plugin-Verzeichnis](https://wordpress.org/plugins/mikesoft-teamvault/), damit die Website standardmäßige Update-Benachrichtigungen erhält.

1. Gehen Sie im WordPress-Adminbereich zu `Plugins > Installieren`.
2. Suchen Sie nach `Mikesoft TeamVault`.
3. Klicken Sie auf `Jetzt installieren` und aktivieren Sie das Plugin.
4. Öffnen Sie `TeamVault > Einstellungen`, um Zugriffs-, Speicher- und Dateiregeln zu überprüfen.

### Manuell

1. Laden Sie das Release-Paket von WordPress.org herunter.
2. Laden Sie es nach `wp-content/plugins/mikesoft-teamvault/` hoch.
3. Aktivieren Sie das Plugin über den Plugins-Bildschirm.

## Zugriffsmodell

- Der Zugriff auf den Datei-Arbeitsbereich nutzt die Fähigkeit `manage_private_documents`.
- Neue Aktivierungen gewähren diese Fähigkeit nur Administratoren.
- Die Fähigkeit `manage_private_documents` gewährt vollen Zugriff auf den TeamVault-Arbeitsbereich, einschließlich der Aktionen Hochladen, Umbenennen, Verschieben, Herunterladen, Exportieren und Löschen.
- Der optionale Whitelist-Modus fügt eine zweite Autorisierungsebene für ausgewählte Benutzer hinzu.
- Ordnerbezogene Berechtigungen (seit 2.6) ergänzen die Fähigkeit um eine feingranulare Steuerung: Wenn ein Ordner explizite Regeln hat, ist der Zugriff auf die berechtigten Benutzer/Gruppen und Aktionen beschränkt, mit Vererbung von übergeordneten Ordnern; Ordner ohne Regeln behalten das fähigkeitsbasierte Verhalten. Administratoren behalten stets vollen Zugriff.
- Einstellungen, Gruppen, Speicherkontingente, Benachrichtigungen, Berichte, Aktivitätsprotokolle, Whitelist-Verwaltung, Wartungswerkzeuge und Kontrollen für Daten bei der Deinstallation erfordern `manage_options`.

Wenn der Whitelist-Modus aktiviert ist, behalten Sie das aktuelle Administrator-Konto in der Liste der erlaubten Benutzer, bevor Sie die Einstellungen speichern.
Überprüfen Sie auf Websites, die von älteren Versionen aktualisiert wurden, die bestehenden Rollenfähigkeiten und Whitelist-Einstellungen, falls Redakteure zuvor Zugriff auf TeamVault hatten.

## Speicher

- Standard-Speicherpfad: `wp-content/uploads/private-documents/`
- Das Plugin kann einen benutzerdefinierten, beschreibbaren Pfad verwenden, der in den Einstellungen konfiguriert wird.
- Der Speicher wird dort, wo es unterstützt wird, mit Sperrdateien auf Serverebene geschützt.
- Apache/LiteSpeed können die generierte `.htaccess` durchsetzen; IIS kann `web.config` durchsetzen; Nginx erfordert eine gleichwertige Serverregel, die direkte Anfragen an `/wp-content/uploads/private-documents/` verweigert.
- Bevorzugen Sie für hochsensible Bereitstellungen einen benutzerdefinierten Speicherpfad außerhalb des öffentlichen Webroots.
- Das Speicher-Widget in der Seitenleiste zeigt nur den von TeamVault-Dateien belegten Speicherplatz an, um zu vermeiden, dass irreführende Hosting-Kontingentwerte in geteilten Umgebungen preisgegeben werden.

Wenn eine Website migriert wird, ohne den privaten Speicherordner zu kopieren, können TeamVault-Datensätze in der Datenbank verbleiben, während die ursprünglichen Binärdateien fehlen. Der Einstellungsbildschirm enthält Bereinigungs- und Neuindexierungswerkzeuge für solche Szenarien.

## Support

- Endbenutzer-Support: [WordPress.org Support-Forum](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- E-Mail: [teamvault@mikesoft.it](mailto:teamvault@mikesoft.it)
- Website: [mikesoft.it](https://mikesoft.it)
- Sicherheitsmeldungen: siehe [SECURITY.md](SECURITY.md)
- Unterstützung der kontinuierlichen Open-Source-Pflege: [GitHub Sponsors](https://github.com/sponsors/TheStreamCode)

## Schnelle Entwicklungsprüfung

Installieren Sie die Entwicklungsabhängigkeiten mit Composer und führen Sie dann die Standard-Validierungsbefehle aus:

```bash
composer install
composer lint
composer test
composer ci
```

`composer lint` prüft alle PHP-Dateien des Repositorys außerhalb der generierten Abhängigkeiten. `composer test` führt die schlanke PHPUnit-Suite mit dem Repository-Bootstrap aus. GitHub Actions führt außerdem WordPress Plugin Check gegen einen sauberen Laufzeit-Build des Plugins aus.

## Repository-Leitfaden

Dieses Repository ist der öffentliche Quellcode-Spiegel für das Plugin.

- Produkt- und Installationsinformationen für WordPress.org-Benutzer befinden sich in [`readme.txt`](readme.txt).
- Die vollständige Versionshistorie befindet sich in [`changelog.txt`](changelog.txt).
- Repository-Richtlinien befinden sich in [`CONTRIBUTING.md`](CONTRIBUTING.md), [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) und [`SECURITY.md`](SECURITY.md).
- Betreuer- und Entwicklernotizen befinden sich in [`docs/`](docs/).

## Branding-Ressourcen

- `.wordpress-org/assets/icon-256x256.png` ist das primäre Vollfarb-Symbol für den WordPress.org-Eintrag.
- `.wordpress-org/assets/icon.svg` ist die skalierbare Begleitressource für den WordPress.org-Eintrag.
- `.wordpress-org/assets/screenshot-1.jpg` ist der primäre Screenshot des Dateimanagers, der für den WordPress.org-Eintrag und diese README verwendet wird.
- `assets/logo-teamvault.svg` ist das Plugin-interne Admin-Logo, das innerhalb der TeamVault-Oberfläche verwendet wird.

Diese Ressourcen bedienen unterschiedliche Oberflächen und sollten auf dieselbe Marke abgestimmt bleiben, ohne die Laufzeit-Plugin-Oberfläche zu zwingen, sich an die Verpackungsvorgaben von WordPress.org anzupassen.

## Dokumentationsübersicht

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - Entwickler-Hooks und -Filter
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - lokaler Entwicklungs-Workflow
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - WordPress.org-Release-Prozess

## Lizenz

GPL v2 oder neuer. Siehe [LICENSE](LICENSE).
