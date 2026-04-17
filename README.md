# Mikesoft TeamVault

Secure shared document management for WordPress, separated from the Media Library and designed for private team, partner, and client files.

## Overview

Mikesoft TeamVault adds a private document workspace inside the WordPress admin.
Files are stored in protected storage and delivered through authenticated WordPress handlers instead of public media URLs.

Core capabilities include:

- private storage outside the normal Media Library workflow
- shared access for authorized internal users
- folder creation, rename, move, and delete operations
- drag-and-drop uploads with file validation
- inline preview for supported file types, including PDFs
- ZIP export for folders or the full document library
- activity logging for operational traceability
- maintenance tools for orphan cleanup and storage reindex

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

## Access Model

- The required capability is `manage_private_documents`.
- Administrators and Editors receive that capability on activation.
- Optional whitelist mode adds a second authorization layer for selected users.

When whitelist mode is enabled, keep the current administrator account in the allowed users list before saving settings.

## Storage

- Default storage path: `wp-content/uploads/private-documents/`
- The plugin can use a custom writable path configured in settings.
- Storage is protected with server-level deny files where supported.

If a site is migrated without copying the private storage folder, TeamVault records may remain in the database while the original binaries are missing. The settings screen includes cleanup and reindex tools for those scenarios.

## Support

- End-user support: [WordPress.org support forum](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- Website: [mikesoft.it](https://mikesoft.it)
- Security reports: see [SECURITY.md](SECURITY.md)

## Repository Guide

This repository is the public source mirror for the plugin.

- Product and installation information for WordPress.org users lives in [`readme.txt`](readme.txt).
- Full release history lives in [`changelog.txt`](changelog.txt).
- Repository policies live in [`CONTRIBUTING.md`](CONTRIBUTING.md) and [`SECURITY.md`](SECURITY.md).
- Maintainer and developer notes live in [`docs/`](docs/).

## Branding Assets

- `.wordpress-org/assets/icon-256x256.png` is the primary full-color icon for the WordPress.org listing.
- `.wordpress-org/assets/icon.svg` is the scalable companion asset for the WordPress.org listing.
- `assets/logo-teamvault.svg` is the in-plugin admin logo used inside the TeamVault interface.

These assets serve different surfaces and should stay aligned to the same brand without forcing the runtime plugin UI to match WordPress.org packaging constraints.

## Documentation Map

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - developer hooks and filters
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - local development workflow
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - WordPress.org release process

## License

GPL v2 or later. See [LICENSE](LICENSE).
