# Local Development

## Purpose

This guide describes the local repository workflow for maintaining Mikesoft TeamVault outside a production WordPress site.

## Prerequisites

- WordPress local environment
- PHP 8.0 or later
- Composer dependencies installed from the tracked lockfile
- Git
- PowerShell on Windows for the release tooling in this repository

## Recommended Workspace Layout

- source checkout: `mikesoft-teamvault-src`
- runtime plugin slug inside WordPress: `mikesoft-teamvault`
- local SVN checkout: `mikesoft-teamvault-svn`

Keep the source checkout separate from the installed runtime folder used by WordPress.

Before testing, confirm that the runtime plugin directory is either a junction to the source checkout or a freshly synchronized release payload. Do not test against historical `build/` or `.deploy/github-release/` directories.

## Linking the Plugin into WordPress

### Windows PowerShell

Run PowerShell as Administrator if needed for junction creation.

```powershell
New-Item -ItemType Junction -Path "C:\path\to\wordpress\wp-content\plugins\mikesoft-teamvault" -Target "C:\path\to\mikesoft-teamvault-src"
```

### Linux or macOS

```bash
ln -s /path/to/mikesoft-teamvault-src /path/to/wordpress/wp-content/plugins/mikesoft-teamvault
```

## Local Validation

### Standard Commands

From `mikesoft-teamvault-src/`:

```powershell
composer install
composer lint
composer test
```

Use `composer ci` to run lint and PHPUnit together.

### PHPUnit

The repository includes lightweight PHPUnit tests with a custom bootstrap.

From `mikesoft-teamvault-src/`:

```powershell
composer test
```

### Packaging Filter Tests

In the full maintainer workspace, the deployment tooling includes PowerShell tests for release filtering from the sibling `deployment/` directory.

From the workspace root:

```powershell
Invoke-Pester .\deployment\DeployWordPressOrg.Tests.ps1
```

If `Invoke-Pester` is not available, install or enable Pester in the local PowerShell environment before running the test.

If you are working from a standalone clone of `mikesoft-teamvault-src`, these workspace-level deployment scripts are not included.

## Continuous Integration

`.github/workflows/ci.yml` runs on pushes and pull requests to `main`:

- **PHP lint** on PHP 8.0–8.4 (`tools/lint-php.php`).
- **PHPUnit** on PHP 8.2–8.4 (`composer test`).
- **WordPress Plugin Check** against a clean runtime build (development-only files excluded).

### Why Plugin Check runs inline

The Plugin Check job intentionally does **not** use `wordpress/plugin-check-action`. That action
injects plugin-check as a URL plugin in `.wp-env.json`, which triggers an upstream `@wordpress/env`
bug on the `ubuntu-24.04` runner image (Node 24.16 / libuv 1.52.1): `wp-env start` exits `0`
without starting Docker, so the check reports `Environment not initialized`
(see [plugin-check-action#579](https://github.com/WordPress/plugin-check-action/issues/579)).

The job instead starts `wp-env` with no URL plugins and installs plugin-check via WP-CLI
(`wp plugin install plugin-check --activate`) after the environment is up. Keep this inline form
until the upstream bug is resolved; reverting to the action reintroduces the failure.

### Running Plugin Check locally

In any local WordPress install with the plugin active:

```bash
wp plugin install plugin-check --activate
wp plugin check mikesoft-teamvault
```

On Windows with Local, run these commands from the site's **Open Site Shell**. Local adds the
bundled ImageMagick runtime to `PATH`; invoking its `php.exe` directly without that shell can
produce a misleading `php_imagick.dll` startup warning even though the extension is installed.

A clean result prints `Success: Checks complete. No errors found.`

## Manual QA Focus

Before release, validate at least:

- access control and whitelist mode
- storage widget value for TeamVault used space only
- upload, rename, move, delete, preview, and download flows
- folder create, rename, move, and delete operations
- ZIP export
- cleanup and reindex maintenance tools
- uninstall setting behavior

## Documentation Boundaries

- `README.md` is repository-facing.
- `readme.txt` is WordPress.org-facing.
- `changelog.txt` contains the full release history.
- `docs/` contains maintainer and developer notes and must stay out of the plugin package.

## Branding Asset Roles

- `assets/logo-teamvault.svg` is the runtime logo shown inside the plugin admin UI.
- `.wordpress-org/assets/icon-256x256.png` is the full-color directory icon for WordPress.org.
- `.wordpress-org/assets/icon.svg` is the scalable WordPress.org companion asset.
