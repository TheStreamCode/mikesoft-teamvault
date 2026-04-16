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

The deploy script already validates plugin version and stable tag alignment.

## Release Checklist

1. Update the version in `mikesoft-teamvault.php`.
2. Update `Stable tag` and the current release entry in `readme.txt`.
3. Add the full release entry to `changelog.txt`.
4. Run the relevant validation steps.
5. Confirm `.wordpress-org/assets/` contains the expected public assets.
6. Run the deployment script from the workspace root.

## Deployment Command

From the workspace root:

```powershell
.\deployment\deploy-to-wordpress.ps1 -Version 1.1.31 -Username thestreamcode -SvnPassword "YOUR_SVN_PASSWORD"
```

Useful switches:

- `-SkipCommit` stages the SVN working copy without committing.
- `-FreshCheckout` recreates the local SVN checkout before staging.

## What the Script Does

The deployment script:

- builds a clean release payload from `mikesoft-teamvault-src/`
- excludes development and repository-only files
- stages `trunk/` and `tags/<version>/` in the SVN checkout
- syncs WordPress.org assets separately
- refuses to overwrite an already published tag

## Packaging Rules

Repository-only material must stay out of the WordPress.org package, including:

- `README.md`
- `CONTRIBUTING.md`
- `SECURITY.md`
- `docs/`
- tests, vendor source control artifacts, and other development-only files

In the full maintainer workspace, the filter logic lives in the sibling `deployment/DeployWordPressOrg.psm1` module and is covered by `deployment/DeployWordPressOrg.Tests.ps1`.
