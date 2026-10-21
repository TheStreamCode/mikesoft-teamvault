# Contributing to Mikesoft TeamVault

## Code of conduct

All interactions around this project are covered by the [Code of Conduct](CODE_OF_CONDUCT.md).

## Repository policy

This repository is a public source mirror for the Mikesoft TeamVault plugin.

- External pull requests are not accepted.
- GitHub Issues are not the support channel for the plugin.
- End-user support and bug reports go through the [official WordPress.org support forum](https://wordpress.org/support/plugin/mikesoft-teamvault/).
- Maintainer changes use internal pull requests so the required CI and security checks run before protected `main` is updated.

## What this repository is for

The repository is published for transparency, code review, and technical reference.

If you evaluate the plugin or reproduce an issue locally, collect at least:

- WordPress version
- PHP version
- plugin version
- exact reproduction steps
- expected result versus actual result
- relevant screenshots or logs

## Maintainer workflow

- Open internal pull requests from short-lived branches and let the required CI checks pass before merging.
- Use Conventional Commits (`type(scope): summary`, English, imperative mood).
- Run the checks listed in [`AGENTS.md`](AGENTS.md) before merging: `composer validate --strict`, `composer audit --locked`, `composer ci`, and the JavaScript syntax and node tests. GitHub Actions also runs WordPress Plugin Check.
- Keep version fields, `readme.txt`, `changelog.txt`, and `CITATION.cff` aligned when preparing a release, and update the localized READMEs when user-facing behavior changes.

## Maintainer notes

Maintainer-specific development and release notes live in dedicated documentation:

- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md)
- [`docs/maintainer/release.md`](docs/maintainer/release.md)
- [`docs/developer/hooks.md`](docs/developer/hooks.md)

## Naming conventions

The codebase deliberately uses two prefixes, kept apart on purpose:

- PHP classes use `MSTV_`, and plugin options and hooks use `mstv_`.
- CSS classes, the front-end JavaScript namespace, and legacy test file names use the historical `pdm-` and `PDM` prefixes.

When adding code, follow the prefix of the layer you are in: `MSTV_` and `mstv_` for PHP, `pdm-` for CSS and JavaScript selectors. Do not rename across layers in unrelated changes; a full `pdm-` to `mstv-` migration, if ever done, must be its own dedicated change.

## Security reports

Do not post security vulnerabilities publicly.
Follow the instructions in [SECURITY.md](SECURITY.md).
