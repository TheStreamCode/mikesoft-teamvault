# Mikesoft TeamVault

[![CI](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml/badge.svg)](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml)
[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault?label=WordPress.org)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![WordPress Tested](https://img.shields.io/wordpress/plugin/tested/mikesoft-teamvault?label=Tested%20up%20to)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-db61a2?logo=githubsponsors&logoColor=white)](https://github.com/sponsors/TheStreamCode)

[English](README.md) · **Italiano** · [Français](README.fr.md) · [Español](README.es.md) · [Deutsch](README.de.md)

Spazio di lavoro documentale privato per team, agenzie e reparti operativi WordPress che hanno bisogno di condividere file in modo controllato al di fuori della Libreria Media.

Versione attuale del plugin: `3.2.0`.

**Oltre 2.000 download totali** su WordPress.org, con decine di nuovi download ogni giorno.

Se TeamVault ti è utile, valuta di [sostenere il progetto su GitHub](https://github.com/sponsors/TheStreamCode) — è sviluppato e mantenuto gratuitamente, e le sponsorizzazioni aiutano a portarlo avanti.

## Panoramica

Mikesoft TeamVault aggiunge uno spazio di lavoro documentale privato all'interno della bacheca di WordPress.
È pensato per i team che hanno bisogno di organizzare, visualizzare in anteprima, esportare e condividere file sensibili senza esporli tramite i normali URL della Libreria Media.

I file sono archiviati in uno storage protetto e distribuiti tramite gestori WordPress autenticati anziché tramite URL pubblici dei media.

![TeamVault file manager interface](.wordpress-org/assets/screenshot-1.jpg)

Tra i casi d'uso tipici rientrano:

- documenti aziendali interni
- consegna di documenti dall'agenzia al cliente dalla bacheca WordPress
- scambi di file con partner o fornitori
- archivi di back-office che devono restare fuori dalla Libreria Media pubblica

Tra le funzionalità principali rientrano:

- storage privato al di fuori del normale flusso della Libreria Media
- accesso condiviso per gli utenti interni autorizzati
- operazioni di creazione, rinomina, spostamento ed eliminazione delle cartelle
- caricamenti drag-and-drop con validazione dei file
- anteprima inline per i tipi di file supportati, inclusi i PDF
- esportazione ZIP per le cartelle o per l'intera libreria documentale
- registro attività per la tracciabilità operativa
- strumenti di manutenzione per la pulizia degli orfani e la reindicizzazione dello storage

Funzionalità di governance (tutte gratuite, dalla versione 2.6):

- gruppi TeamVault per organizzare gli utenti in reparti o team, indipendentemente dai ruoli di WordPress
- permessi per cartella con azioni granulari (visualizzazione, caricamento, download, eliminazione, gestione) per utenti e gruppi, con ereditarietà e override espliciti sui figli
- accesso in sola anteprima che consente la visualizzazione senza download o esportazione ZIP
- quote di storage per utente e per gruppo applicate prima del caricamento
- report di accesso (chi ha visualizzato o scaricato cosa) con filtri ed esportazione CSV del registro attività
- notifiche email per gli eventi di caricamento, download, eliminazione e accesso negato

## Ultima versione

La versione `3.0.0` è una tappa importante per la sicurezza e l'affidabilità. I risultati di ricerca ora vengono filtrati attraverso il motore dei permessi per cartella, così gli utenti con restrizioni non possono più scoprire nomi di file o metadati provenienti da cartelle che non possono visualizzare. Il file `.htaccess` di storage generato nega l'accesso diretto su Apache 2.4 oltre che su Apache 2.2 e IIS, e le quote di storage vengono applicate con un blocco a livello di database in modo che i caricamenti concorrenti non possano superare congiuntamente un limite. I download e le anteprime inline acquisiscono il supporto HTTP Range (`Accept-Ranges` / `206 Partial Content`) per trasferimenti riprendibili e visualizzatori PDF con ricerca per intervallo sui file di grandi dimensioni. La finestra di dialogo dei permessi delle cartelle ora avvisa quando esistono regole ma la radice non ne ha, l'icona del menu di amministrazione è coerente con lo stile nativo di WordPress e il JavaScript di amministrazione è stato suddiviso in moduli mirati senza alcuna variazione di comportamento.

La versione `2.6` ha introdotto la **suite di governance** documentale gratuita: gruppi TeamVault, permessi per cartella con ereditarietà e azioni granulari (visualizzazione, caricamento, download, eliminazione, gestione), accesso in sola anteprima, quote di storage per utente e per gruppo, report di accesso con esportazione CSV, notifiche email. Le installazioni esistenti non subiscono variazioni perché le cartelle senza regole mantengono il comportamento precedente.

Perché i team adottano TeamVault:

- crea un'area documentale privata dedicata invece di sovraccaricare la Libreria Media
- aggiunge un controllo degli accessi basato sulle capability con un livello di whitelist opzionale, oltre a permessi per cartella e gruppi per una governance più fine
- mantiene i flussi di esportazione, manutenzione e recupero focalizzati sui file operativi

## Requisiti

- WordPress 6.0 o successivo
- PHP 8.0 o successivo
- Percorso di storage scrivibile per i documenti privati
- `ZipArchive` disponibile sul server per le funzionalità di esportazione

## Installazione

### Consigliata

Installa il plugin dalla [Directory dei plugin di WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/) in modo che il sito riceva le normali notifiche di aggiornamento.

1. Nella bacheca di WordPress, vai su `Plugins > Add New`.
2. Cerca `Mikesoft TeamVault`.
3. Fai clic su `Install Now` e attiva il plugin.
4. Apri `TeamVault > Settings` per rivedere le regole di accesso, storage e file.

### Manuale

1. Scarica il pacchetto della versione da WordPress.org.
2. Caricalo in `wp-content/plugins/mikesoft-teamvault/`.
3. Attiva il plugin dalla schermata Plugin.

## Modello di accesso

- L'accesso allo spazio di lavoro dei file utilizza la capability `manage_private_documents`.
- Le nuove attivazioni assegnano tale capability ai soli Amministratori.
- La capability `manage_private_documents` concede l'accesso completo allo spazio di lavoro TeamVault, incluse le azioni di caricamento, rinomina, spostamento, download, esportazione ed eliminazione.
- La modalità whitelist opzionale aggiunge un secondo livello di autorizzazione per gli utenti selezionati.
- I permessi per cartella (dalla versione 2.6) aggiungono un controllo granulare sopra la capability: quando una cartella ha regole esplicite, l'accesso è limitato agli utenti/gruppi e alle azioni concessi, con ereditarietà dalle cartelle padre; le cartelle senza regole mantengono il comportamento basato sulla capability. Gli Amministratori conservano sempre l'accesso completo.
- Impostazioni, gruppi, quote, notifiche, report, registri attività, gestione della whitelist, strumenti di manutenzione e controlli sui dati alla disinstallazione richiedono `manage_options`.

Quando la modalità whitelist è abilitata, mantieni l'account amministratore corrente nell'elenco degli utenti consentiti prima di salvare le impostazioni.
Sui siti aggiornati da versioni precedenti, rivedi le capability dei ruoli esistenti e le impostazioni della whitelist se in passato gli Editor avevano accesso a TeamVault.

## Storage

- Percorso di storage predefinito: `wp-content/uploads/private-documents/`
- Il plugin può utilizzare un percorso scrivibile personalizzato configurato nelle impostazioni.
- Lo storage è protetto con file di deny a livello di server dove supportati.
- Apache/LiteSpeed possono applicare il file `.htaccess` generato; IIS può applicare `web.config`; Nginx richiede una regola server equivalente che neghi le richieste dirette a `/wp-content/uploads/private-documents/`.
- Per i deployment ad alta sensibilità, preferisci un percorso di storage personalizzato all'esterno della webroot pubblica.
- Il widget di storage nella barra laterale mostra solo lo spazio utilizzato dai file di TeamVault, per evitare di esporre valori di quota dell'hosting fuorvianti negli ambienti condivisi.

Se un sito viene migrato senza copiare la cartella di storage privato, i record di TeamVault possono rimanere nel database mentre i binari originali risultano mancanti. La schermata delle impostazioni include strumenti di pulizia e reindicizzazione per questi scenari.

## Supporto

- Supporto per gli utenti finali: [Forum di supporto di WordPress.org](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- Email: [teamvault@mikesoft.it](mailto:teamvault@mikesoft.it)
- Sito web: [mikesoft.it](https://mikesoft.it)
- Segnalazioni di sicurezza: vedi [SECURITY.md](SECURITY.md)
- Sostieni la manutenzione open-source continua: [GitHub Sponsors](https://github.com/sponsors/TheStreamCode)

## Verifica rapida per lo sviluppo

Installa le dipendenze di sviluppo con Composer, poi esegui i comandi di validazione standard:

```bash
composer install
composer lint
composer test
composer ci
```

`composer lint` controlla tutti i file PHP del repository al di fuori delle dipendenze generate. `composer test` esegue la suite PHPUnit leggera con il bootstrap del repository. GitHub Actions esegue inoltre il WordPress Plugin Check su una build runtime pulita del plugin.

## Guida al repository

Questo repository è il mirror pubblico del codice sorgente del plugin.

- Le informazioni sul prodotto e sull'installazione per gli utenti di WordPress.org si trovano in [`readme.txt`](readme.txt).
- La cronologia completa delle versioni si trova in [`changelog.txt`](changelog.txt).
- Le policy del repository si trovano in [`CONTRIBUTING.md`](CONTRIBUTING.md), [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) e [`SECURITY.md`](SECURITY.md).
- Le note per manutentori e sviluppatori si trovano in [`docs/`](docs/).

## Risorse di branding

- `.wordpress-org/assets/icon-256x256.png` è l'icona principale a colori pieni per la scheda su WordPress.org.
- `.wordpress-org/assets/icon.svg` è la risorsa scalabile complementare per la scheda su WordPress.org.
- `.wordpress-org/assets/screenshot-1.jpg` è lo screenshot principale del file manager utilizzato dalla scheda su WordPress.org e da questo README.
- `assets/logo-teamvault.svg` è il logo di amministrazione interno al plugin, utilizzato all'interno dell'interfaccia di TeamVault.

Queste risorse servono superfici diverse e dovrebbero restare allineate allo stesso brand senza obbligare l'interfaccia runtime del plugin ad adeguarsi ai vincoli di packaging di WordPress.org.

## Mappa della documentazione

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - hook e filtri per sviluppatori
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - flusso di sviluppo locale
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - processo di rilascio su WordPress.org

## Licenza

GPL v2 o successiva. Vedi [LICENSE](LICENSE).
