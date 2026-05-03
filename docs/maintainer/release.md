# WordPress.org Release Process

## Source of Truth

- Source plugin directory: `mikesoft-teamvault-src/`
- WordPress.org assets source: `mikesoft-teamvault-src/.wordpress-org/assets/`
- Local SVN working copy: `mikesoft-teamvault-svn/`

This guide assumes the full maintainer workspace, where release automation lives in sibling directories outside the public plugin repository.

## Version Alignment

Before releasing, confirm these values match:

- plugin header version in `mikesoft-teamvault.php`
- `MSTV_VERSION` constant in `mikesoft-teamvault.php`
- `Stable tag` in `readme.txt`
- current release entry in `changelog.txt`

The deploy script already validates plugin version and stable tag alignment.

## Release Checklist

1. Update the version in `mikesoft-teamvault.php`.
2. Update `Stable tag` and the current release entry in `readme.txt`.
3. Add the full release entry to `changelog.txt`.
4. Run `composer ci` from `mikesoft-teamvault-src/`.
5. Confirm `.wordpress-org/assets/` contains the expected public assets.
6. Run the deployment script from the workspace root.
7. Publish the matching GitHub release with the release ZIP attached.

## Deployment Command

From the workspace root:

```powershell
.\deployment\deploy-to-wordpress.ps1 -Version 2.0.0 -Username thestreamcode -SvnPassword "YOUR_SVN_PASSWORD"
```

Useful switches:

- `-SkipCommit` stages the SVN working copy without committing.
- `-FreshCheckout` recreates the local SVN checkout before staging.

## What the Script Does

The deployment script:

- builds a clean release payload from `mikesoft-teamvault-src/`
- excludes development and repository-only files
- stages `trunk/` and `tags/<version>` in the SVN checkout
- syncs WordPress.org assets separately
- refuses to overwrite an already published tag

## Packaging Rules

Repository-only material must stay out of the WordPress.org package, including:

- `README.md`
- `AGENTS.md`
- `CONTRIBUTING.md`
- `SECURITY.md`
- `docs/`
- tests, vendor source control artifacts, and other development-only files

## Branding Asset Rules

- Keep `.wordpress-org/assets/icon-256x256.png` as the primary full-color icon for the WordPress.org listing.
- Keep `.wordpress-org/assets/icon.svg` aligned with the public listing brand.
- Keep `.wordpress-org/assets/screenshot-1.jpg` aligned with the current TeamVault file manager interface.
- Keep `assets/logo-teamvault.svg` reserved for the plugin admin experience.

The deploy script copies runtime plugin files and WordPress.org listing assets from separate source locations.

In the full maintainer workspace, the filter logic lives in the sibling `deployment/DeployWordPressOrg.psm1` module and is covered by `deployment/DeployWordPressOrg.Tests.ps1`.
