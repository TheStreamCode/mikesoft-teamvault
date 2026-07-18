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
- current plugin version in `README.md`

The deploy script already validates plugin version and stable tag alignment.

## Release Checklist

1. Update the version in `mikesoft-teamvault.php`.
2. Update `Stable tag` and the current release entry in `readme.txt`.
3. Add the full release entry to `changelog.txt`.
4. Update the current plugin version and latest release summary in `README.md`.
5. Run `composer ci` from `mikesoft-teamvault-src/`.
6. Run WordPress Plugin Check against the clean runtime payload.
7. Smoke test the file browser REST endpoint with plain permalinks when REST URL handling changes.
8. Confirm `.wordpress-org/assets/` contains the expected public assets.
9. Build the release ZIP and verify that repository-only files are absent.
10. Commit the release, push `main`, and publish the matching GitHub tag and release with the ZIP attached.
11. Run the WordPress.org deployment script from the workspace root.
12. Verify the public GitHub release and the WordPress.org `trunk` and version tag.

## Changelog Policy

- Keep `changelog.txt` as the complete historical changelog, ordered newest first.
- Keep the `readme.txt` changelog short and user-facing: current release plus the most important recent releases only.
- Use clear categories in long-form entries, such as Security, Reliability, Uploads, Export, Admin UI, Storage, Documentation, Compliance, and Tests.
- Do not rewrite the technical meaning of past releases when polishing release notes; improve clarity and consistency only.
- Do not edit already published WordPress.org release tags for documentation-only cleanup. Include changelog cleanup in the next real release.

## WordPress.org Listing Translations

Keep the shipped `readme.txt` in English. WordPress.org localizes plugin listing content through translate.wordpress.org, not through locale-specific files such as `readme-it_IT.txt` in the plugin package.

For Italian listing copy, translate the plugin readme strings in the plugin's Development Readme project on translate.wordpress.org and wait for locale validation.

## Deployment Command

From the workspace root:

```powershell
.\deployment\deploy-to-wordpress.ps1 -Version 3.2.3 -Username thestreamcode
```

The script never accepts or forwards an SVN password. Authenticate through SVN's interactive prompt or its operating-system credential store so credentials are not exposed in native process arguments.

Useful switches:

- `-SkipCommit` stages the SVN working copy without committing.
- `-FreshCheckout` recreates the local SVN checkout before staging.

`-FreshCheckout` only removes a dedicated working copy inside the maintainer workspace when an existing `.svn` directory is present. It refuses the workspace root, the source repository, external paths, ordinary directories, symbolic links, and junctions. Release versions are validated as semantic versions before they are used as SVN tag paths.

## What the Script Does

The deployment script:

- builds a clean release payload from `mikesoft-teamvault-src/`
- excludes development and repository-only files
- stages `trunk/` and `tags/<version>` in the SVN checkout
- syncs WordPress.org assets separately
- refuses to overwrite an already published tag

Generated staging directories under `.deploy/` are disposable and are rebuilt from `mikesoft-teamvault-src/`. Historical `build/` or `.deploy/github-release/` directories are not release inputs and must not be used for publication.

## Packaging Rules

Repository-only material must stay out of the WordPress.org package, including:

- `README.md`
- `COPYRIGHT.md`
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
