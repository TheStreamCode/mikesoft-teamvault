# Mikesoft TeamVault

Gestione documentale privata per i team WordPress, con archiviazione protetta e controllo degli accessi granulare

[![CI](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml/badge.svg)](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml)
[![WordPress.org](https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault?label=WordPress.org)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)

![Banner di TeamVault per la scheda del plugin su WordPress.org](.wordpress-org/assets/banner-772x250.png)

[English](README.md) · **Italiano** · [Français](README.fr.md) · [Español](README.es.md) · [Deutsch](README.de.md)

Mikesoft TeamVault aggiunge uno spazio di lavoro documentale privato all'interno della bacheca di WordPress per team, agenzie, partner e reparti di back-office che hanno bisogno di un accesso controllato a file condivisi. I documenti sono archiviati fuori dalla Libreria media e distribuiti tramite gestori WordPress autenticati anziché tramite URL pubblici dei media. Questo repository è il mirror pubblico del codice sorgente: installazioni e aggiornamenti arrivano da [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/), mentre il supporto agli utenti passa dal [forum di supporto ufficiale](https://wordpress.org/support/plugin/mikesoft-teamvault/).

Mikesoft TeamVault è un plugin indipendente; non è affiliato, sponsorizzato o approvato da WordPress, Automattic o dalla WordPress Foundation.

![Gestione file di TeamVault, con albero delle cartelle, schede file con icone per tipo e miniature delle immagini, e pannello dei dettagli con anteprima inline](.wordpress-org/assets/screenshot-1.jpg)

## Installazione

1. Nella bacheca di WordPress, vai su `Plugins > Add New`.
2. Cerca `Mikesoft TeamVault`.
3. Fai clic su `Install Now` e attiva il plugin.
4. Apri `TeamVault > Settings` per rivedere regole di accesso, archiviazione e file.

Alternativa manuale: scarica il pacchetto di rilascio da [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/), caricalo in `wp-content/plugins/mikesoft-teamvault/` e attivalo dalla schermata Plugin.

## Avvio rapido

1. Apri `TeamVault > Settings` e rivedi il percorso di archiviazione, le estensioni consentite e il limite di caricamento.
2. Assegna la capability `manage_private_documents` solo ai ruoli o agli utenti che hanno bisogno di accesso al vault.
3. Crea i gruppi quando i permessi devono seguire reparti o team di progetto, poi crea le cartelle dei documenti.
4. Carica un file dalla vista griglia o elenco e verifica il comportamento di anteprima e download con un account non amministratore.

## Screenshot

| Permessi per cartella | Ricerca nel vault | Quote di archiviazione |
| :---: | :---: | :---: |
| [![Controllo accessi per cartella](.wordpress-org/assets/screenshot-2.jpg)](.wordpress-org/assets/screenshot-2.jpg) | [![Ricerca con badge per tipo di file](.wordpress-org/assets/screenshot-3.jpg)](.wordpress-org/assets/screenshot-3.jpg) | [![Quote per utente e per gruppo](.wordpress-org/assets/screenshot-4.jpg)](.wordpress-org/assets/screenshot-4.jpg) |
| **Gruppi** | **Registro attività** | **Impostazioni** |
| [![Gruppi utenti](.wordpress-org/assets/screenshot-5.jpg)](.wordpress-org/assets/screenshot-5.jpg) | [![Registro di audit](.wordpress-org/assets/screenshot-6.jpg)](.wordpress-org/assets/screenshot-6.jpg) | [![Impostazioni del plugin](.wordpress-org/assets/screenshot-7.jpg)](.wordpress-org/assets/screenshot-7.jpg) |

## Funzionalità

- Archiviazione privata al di fuori del normale flusso della Libreria media.
- Controlli di accesso basati su capability, whitelist opzionale, utenti, gruppi e cartelle, con ereditarietà e override espliciti sulle sottocartelle.
- Operazioni sulle cartelle (creazione, rinomina, spostamento, eliminazione) e caricamenti drag-and-drop validati con anteprime inline, inclusi i PDF.
- Accesso in sola anteprima che blocca download ed esportazioni ZIP.
- Quote di archiviazione per utente e per gruppo, applicate prima del caricamento.
- Report di accesso, registro attività, esportazione CSV e notifiche email consapevoli della cartella per gli eventi di caricamento, download, eliminazione e accesso negato.
- Esportazione ZIP, pulizia degli orfani e strumenti di manutenzione per la reindicizzazione dell'archivio.
- Interfacce di amministrazione in inglese, italiano, francese, spagnolo e tedesco.

Tutte le funzionalità di governance sono incluse nel plugin gratuito.

## Requisiti

- WordPress 6.0 o successivo.
- PHP 8.0 o successivo.
- Un percorso di archiviazione scrivibile per i documenti privati.
- `ZipArchive` sul server per le funzionalità di esportazione.

## Configurazione

Apri `TeamVault > Settings` dopo l'attivazione. Le impostazioni principali sono:

| Impostazione | Scopo | Predefinito |
| --- | --- | --- |
| Lingua dell'interfaccia | Segue la lingua di WordPress o forza una lingua supportata | Automatica |
| Whitelist utenti | Aggiunge un elenco esplicito di utenti consentiti oltre alla capability richiesta | Disattivata |
| Estensioni consentite | Limita i caricamenti ai tipi documento, immagine, archivio, audio e video configurati | Elenco sicuro predefinito |
| Dimensione massima file | Applica un limite di caricamento del plugin in aggiunta ai limiti di PHP e del server web | 50 MB |
| Anteprima PDF | Consente anteprime PDF inline autenticate | Attiva |
| Registro attività | Registra le operazioni sui documenti per audit e report | Attivo |
| Elimina dati alla disinstallazione | Rimuove tabelle, opzioni, capability e archivio contrassegnato del plugin alla disinstallazione | Disattivato |

Gruppi, permessi per cartella, quote, report e notifiche hanno pagine dedicate nell'amministrazione. Configura l'accesso prima di invitare utenti non amministratori.

La configurazione di runtime è memorizzata nelle opzioni di WordPress; TeamVault non legge impostazioni da variabili d'ambiente o da un file `.env` del repository. Mantieni le credenziali del database e i salt nel `wp-config.php` del sito.

## Privacy e sicurezza

I file privati non vengono mai serviti tramite URL pubblici dei media: download e anteprime passano attraverso gestori WordPress autenticati con risposte private e no-cache.

- L'accesso al vault usa la capability `manage_private_documents`; le nuove attivazioni la assegnano solo agli Amministratori.
- La modalità whitelist opzionale aggiunge un secondo livello di autorizzazione per gli utenti selezionati.
- I permessi per cartella limitano l'accesso agli utenti, ai gruppi e alle azioni concessi, con ereditarietà dalle cartelle padre. Le cartelle senza regole mantengono il comportamento basato sulla capability e gli Amministratori conservano sempre l'accesso completo.
- Impostazioni, gruppi, quote, notifiche, report, registri, gestione della whitelist, strumenti di manutenzione e controlli sui dati alla disinstallazione richiedono `manage_options`.
- L'archiviazione predefinita è `wp-content/uploads/private-documents/` ed è protetta con file di deny a livello di server: il file `.htaccess` generato su Apache e LiteSpeed, il file `web.config` su IIS e una regola Nginx equivalente che nega le richieste dirette. I caricamenti vengono rifiutati quando questi file non possono essere creati.
- Consigliato: un percorso di archiviazione personalizzato all'esterno della webroot pubblica, soprattutto su Nginx, che ignora `.htaccess`. Il widget di archiviazione mostra solo l'utilizzo di TeamVault.

Consulta [`SECURITY.md`](SECURITY.md) per le segnalazioni di vulnerabilità.

## Risoluzione dei problemi

### L'esportazione ZIP non funziona

L'esportazione richiede l'estensione PHP `ZipArchive` e un percorso di archiviazione scrivibile. Attiva l'estensione o chiedi al provider di hosting di attivarla, poi riprova.

### Un amministratore resta bloccato dopo aver attivato la whitelist

La modalità whitelist è un elenco di utenti consentiti. Mantieni l'account amministratore corrente nell'elenco degli utenti consentiti prima di salvare le impostazioni.

### Dopo una migrazione del sito i file risultano mancanti

Un sito migrato senza la cartella di archiviazione privata mantiene i record di TeamVault nel database mentre i file originali risultano mancanti. Copia la directory di archiviazione durante le migrazioni, poi usa gli strumenti di pulizia e reindicizzazione della schermata delle impostazioni dopo aver verificato la copia.

### I caricamenti vengono rifiutati

Controlla le estensioni consentite, la dimensione massima dei file e i limiti di caricamento di PHP. TeamVault valida estensione, tipo MIME rilevato, dimensione e pattern di contenuto pericoloso prima di archiviare un file, e segnala il tipo di contenuto rilevato in caso di mancata corrispondenza.

## Sviluppo

Lo sviluppo richiede PHP 8.2 o successivo per PHPUnit 11, Composer 2, Node.js 24 per il tooling JavaScript e Plugin Check, e Git; il plugin stesso supporta PHP 8.0 o successivo.

Installa le dipendenze di sviluppo bloccate ed esegui i controlli PHP standard:

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer audit --locked
composer ci
```

`composer ci` esegue il lint PHP e la suite PHPUnit. GitHub Actions esegue anche WordPress Plugin Check su un pacchetto runtime pulito.

Controlli aggiuntivi:

```bash
node --check assets/js/admin-app-core.js
node --check assets/js/admin-app-governance.js
node --check assets/js/admin-app.js
node --check assets/js/admin-notice-dismiss.js
node --test tests/plugin-check-output.test.mjs
```

Non esiste una fase di compilazione degli asset: PHP, CSS e JavaScript di runtime sono versionati come sorgenti. `git archive --format=zip --prefix=mikesoft-teamvault/ --output=mikesoft-teamvault.zip HEAD` genera un pacchetto da una revisione committata usando le esclusioni di export di `.gitattributes`.

Note per manutentori e sviluppatori:

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - hook e filtri
- [`docs/developer/i18n.md`](docs/developer/i18n.md) - workflow di traduzione
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - sviluppo locale e QA manuale
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - rilascio e deployment su WordPress.org
- [`docs/maintainer/security-review.md`](docs/maintainer/security-review.md) - ultima revisione di sicurezza

## Supporto

- Supporto per gli utenti finali: [Forum di supporto di WordPress.org](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- Email: [teamvault@mikesoft.it](mailto:teamvault@mikesoft.it)
- Sito web: [mikesoft.it](https://mikesoft.it)
- Segnalazioni di sicurezza: vedi [`SECURITY.md`](SECURITY.md), mai una issue pubblica.

## Contribuire

Questo repository è il mirror pubblico del codice sorgente del plugin. Le pull request esterne non sono accettate; vedi [`CONTRIBUTING.md`](CONTRIBUTING.md) per la policy di segnalazione e il workflow dei manutentori.

## Licenza

GPL-2.0-or-later - vedi [`LICENSE`](LICENSE).

---

Maintained by [Michael Gasperini (Mikesoft)](https://mikesoft.it). Se il progetto ti è utile, valuta di [sostenere la manutenzione](https://github.com/sponsors/TheStreamCode).

