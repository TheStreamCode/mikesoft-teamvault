# Mikesoft TeamVault

Gestion documentaire privée pour les équipes WordPress, avec stockage protégé et contrôle d'accès granulaire

[![CI](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml/badge.svg)](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml)
[![WordPress.org](https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault?label=WordPress.org)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)

![Bannière de TeamVault pour la fiche de l'extension sur WordPress.org](.wordpress-org/assets/banner-772x250.png)

[English](README.md) · [Italiano](README.it.md) · **Français** · [Español](README.es.md) · [Deutsch](README.de.md)

Mikesoft TeamVault ajoute un espace de travail documentaire privé dans l'administration WordPress pour les équipes, les agences, les partenaires et les services de back-office qui ont besoin d'un accès contrôlé à des fichiers partagés. Les documents sont stockés en dehors de la Médiathèque et distribués par des gestionnaires WordPress authentifiés plutôt que par des URL de médias publiques. Ce dépôt est le miroir public du code source : les installations et les mises à jour proviennent de [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/), et le support utilisateur passe par le [forum de support officiel](https://wordpress.org/support/plugin/mikesoft-teamvault/).

Mikesoft TeamVault est une extension indépendante ; elle n'est ni affiliée, ni sponsorisée, ni approuvée par WordPress, Automattic ou la WordPress Foundation.

![Gestionnaire de fichiers TeamVault, avec arborescence des dossiers, cartes de fichiers avec icônes par type et vignettes d'images, et panneau de détails avec aperçu intégré](.wordpress-org/assets/screenshot-1.jpg)

## Installation

1. Dans l'administration WordPress, ouvrez `Plugins > Add New`.
2. Recherchez `Mikesoft TeamVault`.
3. Cliquez sur `Install Now` et activez l'extension.
4. Ouvrez `TeamVault > Settings` pour vérifier les règles d'accès, de stockage et de fichiers.

Solution manuelle : téléchargez le paquet de version depuis [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/), téléversez-le dans `wp-content/plugins/mikesoft-teamvault/` et activez l'extension depuis l'écran Extensions.

## Démarrage rapide

1. Ouvrez `TeamVault > Settings` et vérifiez le chemin de stockage, les extensions autorisées et la limite de téléversement.
2. Accordez la capacité `manage_private_documents` uniquement aux rôles ou utilisateurs qui ont besoin d'accéder au coffre.
3. Créez des groupes lorsque les autorisations doivent suivre les services ou les équipes projet, puis créez les dossiers de documents.
4. Téléversez un fichier depuis la grille ou la vue liste et vérifiez le comportement de l'aperçu et du téléchargement avec un compte non administrateur.

## Captures d'écran

| Autorisations par dossier | Recherche dans le coffre | Quotas de stockage |
| :---: | :---: | :---: |
| [![Contrôle d'accès par dossier](.wordpress-org/assets/screenshot-2.jpg)](.wordpress-org/assets/screenshot-2.jpg) | [![Recherche avec badges de type de fichier](.wordpress-org/assets/screenshot-3.jpg)](.wordpress-org/assets/screenshot-3.jpg) | [![Quotas par utilisateur et par groupe](.wordpress-org/assets/screenshot-4.jpg)](.wordpress-org/assets/screenshot-4.jpg) |
| **Groupes** | **Journal d'activité** | **Réglages** |
| [![Groupes d'utilisateurs](.wordpress-org/assets/screenshot-5.jpg)](.wordpress-org/assets/screenshot-5.jpg) | [![Piste d'audit](.wordpress-org/assets/screenshot-6.jpg)](.wordpress-org/assets/screenshot-6.jpg) | [![Réglages de l'extension](.wordpress-org/assets/screenshot-7.jpg)](.wordpress-org/assets/screenshot-7.jpg) |

## Fonctionnalités

- Stockage privé en dehors du flux habituel de la Médiathèque.
- Contrôles d'accès par capacité, liste d'autorisation facultative, utilisateurs, groupes et dossiers, avec héritage et substitutions explicites au niveau des sous-dossiers.
- Opérations sur les dossiers (création, renommage, déplacement, suppression) et téléversements par glisser-déposer validés avec aperçus intégrés, y compris les PDF.
- Accès en aperçu seul qui bloque les téléchargements et les exports ZIP.
- Quotas de stockage par utilisateur et par groupe, appliqués avant le téléversement.
- Rapports d'accès, journal d'activité, export CSV et notifications par e-mail tenant compte du dossier pour les téléversements, téléchargements, suppressions et accès refusés.
- Export ZIP, nettoyage des orphelins et outils de maintenance pour la réindexation du stockage.
- Interfaces d'administration en anglais, italien, français, espagnol et allemand.

Toutes les fonctionnalités de gouvernance sont incluses dans l'extension gratuite.

## Prérequis

- WordPress 6.0 ou version ultérieure.
- PHP 8.0 ou version ultérieure.
- Un chemin de stockage accessible en écriture pour les documents privés.
- `ZipArchive` sur le serveur pour les fonctions d'export.

## Configuration

Ouvrez `TeamVault > Settings` après l'activation. Les principaux réglages sont :

| Réglage | Rôle | Valeur par défaut |
| --- | --- | --- |
| Langue de l'interface | Suit la locale WordPress ou force une langue prise en charge | Automatique |
| Liste d'autorisation d'utilisateurs | Ajoute une liste explicite d'utilisateurs autorisés en plus de la capacité requise | Désactivée |
| Extensions autorisées | Restreint les téléversements aux types document, image, archive, audio et vidéo configurés | Liste sûre intégrée |
| Taille maximale de fichier | Applique une limite de téléversement de l'extension en plus des limites PHP et du serveur web | 50 Mo |
| Aperçu PDF | Autorise les aperçus PDF intégrés authentifiés | Activé |
| Journal d'activité | Enregistre les opérations sur les documents pour l'audit et les rapports | Activé |
| Supprimer les données à la désinstallation | Supprime à la désinstallation les tables, options, capacités et stockage marqué de l'extension | Désactivé |

Les groupes, les autorisations par dossier, les quotas, les rapports et les notifications disposent de pages dédiées dans l'administration. Configurez les accès avant d'inviter des utilisateurs non administrateurs.

La configuration d'exécution est stockée dans les options WordPress ; TeamVault ne lit pas de réglages depuis des variables d'environnement ou un fichier `.env` du dépôt. Conservez les identifiants de base de données et les salts dans le `wp-config.php` du site.

## Confidentialité et sécurité

Les fichiers privés ne sont jamais servis par des URL de médias publiques : les téléchargements et les aperçus passent par des gestionnaires WordPress authentifiés avec des réponses privées et no-cache.

- L'accès au coffre utilise la capacité `manage_private_documents` ; les nouvelles activations l'accordent uniquement aux administrateurs.
- La liste d'autorisation facultative ajoute une seconde couche d'autorisation pour les utilisateurs sélectionnés.
- Les autorisations par dossier limitent l'accès aux utilisateurs, groupes et actions accordés, avec héritage depuis les dossiers parents. Les dossiers sans règles conservent le comportement basé sur la capacité, et les administrateurs conservent toujours un accès complet.
- Les réglages, groupes, quotas, notifications, rapports, journaux, gestion de la liste d'autorisation, outils de maintenance et contrôles des données à la désinstallation exigent `manage_options`.
- Le stockage par défaut est `wp-content/uploads/private-documents/` et il est protégé par des fichiers de refus au niveau du serveur : le fichier `.htaccess` généré sur Apache et LiteSpeed, le fichier `web.config` sur IIS et une règle Nginx équivalente qui refuse les requêtes directes vers ce chemin.
- Pour les déploiements à haute sensibilité, préférez un chemin de stockage personnalisé en dehors de la racine web publique. Le widget de stockage affiche uniquement l'utilisation de TeamVault.

Consultez [`SECURITY.md`](SECURITY.md) pour signaler une vulnérabilité.

## Résolution des problèmes

### L'export ZIP échoue

L'export nécessite l'extension PHP `ZipArchive` et un chemin de stockage accessible en écriture. Activez l'extension ou demandez à l'hébergeur de l'activer, puis réessayez.

### Un administrateur est bloqué après l'activation de la liste d'autorisation

La liste d'autorisation est une liste positive. Conservez le compte administrateur actuel dans la liste des utilisateurs autorisés avant d'enregistrer les réglages.

### Des fichiers manquent après une migration du site

Un site migré sans le dossier de stockage privé conserve les enregistrements TeamVault en base de données alors que les fichiers d'origine manquent. Copiez le répertoire de stockage lors des migrations, puis utilisez les outils de nettoyage et de réindexation de l'écran des réglages après avoir vérifié la copie.

### Les téléversements sont refusés

Vérifiez les extensions autorisées, la taille maximale de fichier et les limites de téléversement PHP. TeamVault valide l'extension, le type MIME détecté, la taille et les motifs de contenu dangereux avant de stocker un fichier, et indique le type de contenu détecté en cas d'incohérence.

## Développement

Le développement nécessite PHP 8.2 ou version ultérieure pour PHPUnit 11, Composer 2, Node.js 24 pour l'outillage JavaScript et Plugin Check, et Git ; l'extension elle-même prend en charge PHP 8.0 ou version ultérieure.

Installez les dépendances de développement verrouillées et exécutez les contrôles PHP standard :

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer audit --locked
composer ci
```

`composer ci` exécute le lint PHP et la suite PHPUnit. GitHub Actions exécute également WordPress Plugin Check sur un paquet d'exécution propre.

Contrôles supplémentaires :

```bash
node --check assets/js/admin-app-core.js
node --check assets/js/admin-app-governance.js
node --check assets/js/admin-app.js
node --check assets/js/admin-notice-dismiss.js
node --test tests/plugin-check-output.test.mjs
```

Aucune étape de compilation d'assets n'existe : le PHP, le CSS et le JavaScript d'exécution sont versionnés comme sources. `git archive --format=zip --prefix=mikesoft-teamvault/ --output=mikesoft-teamvault.zip HEAD` produit un paquet à partir d'une révision commitée en utilisant les exclusions d'export de `.gitattributes`.

Notes pour les mainteneurs et les développeurs :

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - hooks et filtres
- [`docs/developer/i18n.md`](docs/developer/i18n.md) - workflow de traduction
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - développement local et QA manuelle
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - publication et déploiement sur WordPress.org
- [`docs/maintainer/security-review.md`](docs/maintainer/security-review.md) - dernière revue de sécurité

## Assistance

- Support utilisateur final : [forum de support WordPress.org](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- E-mail : [teamvault@mikesoft.it](mailto:teamvault@mikesoft.it)
- Site web : [mikesoft.it](https://mikesoft.it)
- Signalements de sécurité : voir [`SECURITY.md`](SECURITY.md), jamais une issue publique.

## Contribution

Ce dépôt est le miroir public du code source de l'extension. Les pull requests externes ne sont pas acceptées ; consultez [`CONTRIBUTING.md`](CONTRIBUTING.md) pour la politique de signalement et le workflow des mainteneurs.

## Licence

GPL-2.0-or-later - voir [`LICENSE`](LICENSE).

---

Maintained by [Michael Gasperini (Mikesoft)](https://mikesoft.it). Si ce projet vous est utile, envisagez de [soutenir sa maintenance](https://github.com/sponsors/TheStreamCode).

