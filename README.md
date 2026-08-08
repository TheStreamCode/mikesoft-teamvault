<p align="center">
  <img src=".wordpress-org/assets/banner-772x250.png" alt="TeamVault" width="772">
</p>

<h1 align="center">Mikesoft TeamVault</h1>

<p align="center"><strong>Private document management for WordPress teams, with protected storage and granular access control.</strong></p>

<p align="center">
  <a href="https://wordpress.org/plugins/mikesoft-teamvault/"><strong>Install from WordPress.org</strong></a> ·
  <a href="docs/README.md"><strong>Documentation</strong></a> ·
  <a href="SECURITY.md"><strong>Security</strong></a> ·
  <a href="https://wordpress.org/support/plugin/mikesoft-teamvault/"><strong>Support</strong></a>
</p>

<p align="center">
  <a href="https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml"><img src="https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://wordpress.org/plugins/mikesoft-teamvault/"><img src="https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault?label=WordPress.org" alt="WordPress Plugin Version"></a>
  <a href="https://wordpress.org/plugins/mikesoft-teamvault/"><img src="https://img.shields.io/wordpress/plugin/tested/mikesoft-teamvault?label=Tested%20up%20to" alt="WordPress Tested"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&amp;logoColor=white" alt="PHP 8.0+"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg" alt="GPL v2 or later"></a>
</p>

<p align="center"><strong>English</strong> · <a href="README.it.md">Italiano</a> · <a href="README.fr.md">Français</a> · <a href="README.es.md">Español</a> · <a href="README.de.md">Deutsch</a></p>

> [!NOTE]
> This is the public source mirror for TeamVault. Installations and updates are distributed through [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/); user support is handled in the [official support forum](https://wordpress.org/support/plugin/mikesoft-teamvault/).

Current plugin version: `3.2.6`.

![TeamVault file manager — folder tree, file cards with type-aware icons and image thumbnails, and the details panel with inline preview](.wordpress-org/assets/screenshot-1.jpg)

## Why TeamVault

TeamVault gives internal teams, agencies, and operations a dedicated private workspace inside WordPress instead of mixing sensitive documents with public Media Library assets.

- **Protected delivery:** files are served through authenticated WordPress handlers instead of normal public media URLs.
- **Granular governance:** groups and inheritable per-folder rules control view, upload, download, delete, and management actions.
- **Operational visibility:** quotas, access reports, activity logs, CSV export, and email notifications support accountable workflows.
- **Practical file management:** drag-and-drop upload, search, previews, folder operations, and ZIP export live in one focused interface.

Typical uses include internal company documents, agency-to-client delivery, partner exchanges, and back-office archives.

## Product Tour

| Per-folder permissions | Search across the vault | Storage quotas |
| :---: | :---: | :---: |
| [![Per-folder access control](.wordpress-org/assets/screenshot-2.jpg)](.wordpress-org/assets/screenshot-2.jpg) | [![Search with file-type badges](.wordpress-org/assets/screenshot-3.jpg)](.wordpress-org/assets/screenshot-3.jpg) | [![Per-user and per-group quotas](.wordpress-org/assets/screenshot-4.jpg)](.wordpress-org/assets/screenshot-4.jpg) |
| **Groups** | **Activity log** | **Settings** |
| [![User groups](.wordpress-org/assets/screenshot-5.jpg)](.wordpress-org/assets/screenshot-5.jpg) | [![Audit trail](.wordpress-org/assets/screenshot-6.jpg)](.wordpress-org/assets/screenshot-6.jpg) | [![Plugin settings](.wordpress-org/assets/screenshot-7.jpg)](.wordpress-org/assets/screenshot-7.jpg) |

## Capabilities

- private storage outside the normal Media Library workflow
- capability, optional whitelist, user, group, and per-folder access controls
- folder creation, rename, move, delete, inheritance, and explicit child overrides
- validated drag-and-drop uploads and inline previews, including PDFs
- preview-only access that blocks downloads and ZIP exports
- per-user and per-group storage quotas enforced before upload
- access reports, activity logging, CSV export, and event notifications
- ZIP export plus orphan cleanup and storage reindex maintenance tools
- English, Italian, French, Spanish, and German admin interfaces

All governance capabilities are included in the free plugin.

## Latest Release

Version `3.2.6` hardens upload containment and ZIP export reliability, corrects REST governance and multisite activation edge cases, and keeps toolbar actions accessible on common WordPress desktop layouts. It also refreshes repository presentation and release governance. No configuration changes are required.

See the [full changelog](changelog.txt) and [GitHub releases](https://github.com/TheStreamCode/mikesoft-teamvault/releases) for complete history.

## Requirements

- WordPress 6.0 or later
- PHP 8.0 or later
- Writable storage path for private documents
- `ZipArchive` available on the server for export features

## Installation

### Recommended

Install the plugin from the [WordPress.org Plugin Directory](https://wordpress.org/plugins/mikesoft-teamvault/) so the site receives standard update notifications.

1. In WordPress admin, go to `Plugins > Add New`.
2. Search for `Mikesoft TeamVault`.
3. Click `Install Now` and activate the plugin.
4. Open `TeamVault > Settings` to review access, storage, and file rules.

### Manual

1. Download the release package from WordPress.org.
2. Upload it to `wp-content/plugins/mikesoft-teamvault/`.
3. Activate the plugin from the Plugins screen.

## Configuration

Open `TeamVault > Settings` after activation. The main settings are:

| Setting | Purpose | Default |
| --- | --- | --- |
| Interface language | Follows the current WordPress locale or forces English, Italian, French, Spanish, or German | Automatic |
| User whitelist | Adds an explicit user allowlist on top of the required capability | Disabled |
| Allowed extensions | Restricts uploads to the configured document, image, archive, audio, and video types | Safe built-in list |
| Maximum file size | Applies a plugin-level upload limit in addition to PHP and web-server limits | 50 MB |
| PDF preview | Allows authenticated inline PDF previews | Enabled |
| Activity log | Records document operations for audit and reporting | Enabled |
| Delete data on uninstall | Removes plugin tables, options, capabilities, and marked storage when uninstalling | Disabled |

Groups, per-folder permissions, quotas, reports, and notifications have dedicated pages under the TeamVault admin menu. Administrators should configure access before inviting non-administrator users.

TeamVault does not read application settings from environment variables or a repository `.env` file. Runtime configuration is stored through WordPress options. Keep WordPress database credentials and salts in the site-level `wp-config.php`; do not copy them into this repository.

## Usage

1. Grant the `manage_private_documents` capability only to roles or users that need vault access.
2. Create groups when permissions should follow departments or project teams.
3. Create folders and, where needed, add explicit per-folder rules. Child folders inherit the nearest rule set unless they define their own.
4. Upload files from the grid or list view. TeamVault validates the extension, detected MIME type, size, and dangerous content patterns before storing a file.
5. Use previews, downloads, ZIP exports, reports, and the activity log according to the granted action permissions.
6. Use cleanup and reindex only as maintenance tools after confirming that the private storage directory has been copied during migrations.

## Access Model

- File workspace access uses the `manage_private_documents` capability.
- New activations grant that capability to Administrators only.
- The `manage_private_documents` capability grants full TeamVault workspace access, including upload, rename, move, download, export, and delete actions.
- Optional whitelist mode adds a second authorization layer for selected users.
- Per-folder permissions (since 2.6) add fine-grained control on top of the capability: when a folder has explicit rules, access is limited to the granted users/groups and actions, with inheritance from parent folders; folders with no rules keep the capability-based behavior. Administrators always retain full access.
- Settings, groups, quotas, notifications, reports, activity logs, whitelist management, maintenance tools, and uninstall data controls require `manage_options`.

When whitelist mode is enabled, keep the current administrator account in the allowed users list before saving settings.
On sites upgraded from older releases, review existing role capabilities and whitelist settings if Editors previously had TeamVault access.

## Storage

- Default storage path: `wp-content/uploads/private-documents/`
- The active storage directory is shown in `TeamVault > Settings`. An advanced custom path can be provisioned by the site operator and must be writable and marked as TeamVault storage.
- Storage is protected with server-level deny files where supported.
- Apache/LiteSpeed can enforce the generated `.htaccess`; IIS can enforce `web.config`; Nginx requires an equivalent server rule that denies direct requests to `/wp-content/uploads/private-documents/`.
- For high-sensitivity deployments, prefer a custom storage path outside the public webroot.
- The sidebar storage widget shows only the space used by TeamVault files, to avoid exposing misleading hosting quota values on shared environments.

If a site is migrated without copying the private storage folder, TeamVault records may remain in the database while the original binaries are missing. The settings screen includes cleanup and reindex tools for those scenarios.

## Support

- End-user support: [WordPress.org support forum](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- Email: [teamvault@mikesoft.it](mailto:teamvault@mikesoft.it)
- Website: [mikesoft.it](https://mikesoft.it)
- Security reports: see [SECURITY.md](SECURITY.md)
- Support continued open-source maintenance: [GitHub Sponsors](https://github.com/sponsors/TheStreamCode)

## Development

Prerequisites for repository work are PHP 8.2 or later for PHPUnit 11, Composer 2, Node.js 24 for the JavaScript/Plugin Check tooling, and Git. The shipped plugin itself continues to support PHP 8.0 or later.

Install the locked development dependencies and run the standard PHP checks:

```bash
composer install --no-interaction --prefer-dist
composer lint
composer test
composer ci
```

Run the dependency and JavaScript checks separately:

```bash
composer validate --strict
composer audit --locked
node --check assets/js/admin-app-core.js
node --check assets/js/admin-app-governance.js
node --check assets/js/admin-app.js
node --check assets/js/admin-notice-dismiss.js
node --test tests/plugin-check-output.test.mjs
```

`composer lint` checks repository PHP files outside generated dependencies. `composer test` runs the lightweight PHPUnit suite with `tests/bootstrap.php`. GitHub Actions also runs WordPress Plugin Check against a clean runtime package. See [local development](docs/maintainer/local-development.md) for WordPress linking, manual QA, Pester packaging tests, and local Plugin Check instructions.

## Build

There is no asset compilation step: runtime PHP, CSS, and JavaScript are committed as source. For a package from a committed revision, Git attributes exclude repository-only files:

```bash
git archive --format=zip --prefix=mikesoft-teamvault/ --output=mikesoft-teamvault.zip HEAD
tar -tf mikesoft-teamvault.zip
```

Before publishing, verify that the archive contains one `mikesoft-teamvault/` root and excludes `.github/`, `.wordpress-org/`, `docs/`, `tests/`, `tools/`, Composer development files, credentials, and local environment files.

## Deployment

Publishing is a maintainer-only operation. The full maintainer workspace provides the sibling PowerShell deployment tooling and the WordPress.org SVN working copy; they are intentionally not part of a standalone source clone.

The release sequence is:

1. align version metadata and changelogs;
2. run all PHP, JavaScript, packaging, and Plugin Check gates;
3. build and inspect the runtime ZIP;
4. commit and tag the validated revision;
5. publish the GitHub release asset;
6. deploy the same payload to WordPress.org SVN;
7. verify the public plugin API and downloadable ZIP.

Exact commands, safety constraints, and package boundaries are documented in the [release process](docs/maintainer/release.md). SVN credentials must come from the interactive client or operating-system credential store, never command arguments or `.env` files.

## Repository Guide

This repository is the public source mirror for the plugin.

- Product and installation information for WordPress.org users lives in [`readme.txt`](readme.txt).
- Full release history lives in [`changelog.txt`](changelog.txt).
- Repository policies live in [`CONTRIBUTING.md`](CONTRIBUTING.md), [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md), and [`SECURITY.md`](SECURITY.md).
- Maintainer and developer notes live in [`docs/`](docs/).

## Branding Assets

- `.wordpress-org/assets/icon-256x256.png` is the primary full-color icon for the WordPress.org listing.
- `.wordpress-org/assets/icon.svg` is the scalable companion asset for the WordPress.org listing.
- `.wordpress-org/assets/screenshot-1.jpg` … `screenshot-7.jpg` are the WordPress.org listing screenshots, also used in this README.
- `.github/social-preview.png` is the dedicated 1280×640 GitHub social preview; upload this file in the repository's social preview settings after brand changes.
- `assets/logo-teamvault.svg` is the in-plugin admin logo used inside the TeamVault interface.

These assets serve different surfaces and should stay aligned to the same brand without forcing the runtime plugin UI to match WordPress.org packaging constraints.

## Documentation Map

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - developer hooks and filters
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - local development workflow
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - WordPress.org release process
- [`docs/maintainer/security-review.md`](docs/maintainer/security-review.md) - latest repository security review and residual risks

## License

GPL v2 or later. See [LICENSE](LICENSE).
