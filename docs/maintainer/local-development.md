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

### PHPUnit

The repository includes lightweight PHPUnit tests with a custom bootstrap.

From `mikesoft-teamvault-src/`:

```powershell
.\vendor\bin\phpunit.bat --bootstrap tests/bootstrap.php tests
```

### Packaging Filter Tests

In the full maintainer workspace, the deployment tooling includes PowerShell tests for release filtering from the sibling `deployment/` directory.

From the workspace root:

```powershell
Invoke-Pester .\deployment\DeployWordPressOrg.Tests.ps1
```

If `Invoke-Pester` is not available, install or enable Pester in the local PowerShell environment before running the test.

If you are working from a standalone clone of `mikesoft-teamvault-src`, these workspace-level deployment scripts are not included.

## Manual QA Focus

Before release, validate at least:

- access control and whitelist mode
- upload, rename, move, delete, preview, and download flows
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
