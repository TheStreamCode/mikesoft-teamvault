# Repository Guidelines

## Project Structure & Module Organization

This repository contains the public source for the Mikesoft TeamVault WordPress plugin. The plugin entry point is `mikesoft-teamvault.php`. Runtime PHP classes live in `includes/`, admin views live in `admin/views/`, and browser assets live in `assets/css/` and `assets/js/`. WordPress.org listing assets are kept in `.wordpress-org/assets/` and must not be shipped inside the runtime plugin package. Tests live in `tests/`. Maintainer and developer notes live in `docs/`.

## Build, Test, and Development Commands

Run commands from the repository root:

```bash
composer install
composer lint
composer test
composer ci
```

`composer lint` checks PHP syntax across repository PHP files outside generated dependencies. `composer test` runs the PHPUnit suite with `tests/bootstrap.php`. `composer ci` runs lint and tests together, matching the local verification flow used before releases.

GitHub Actions also runs WordPress Plugin Check against a clean runtime build. Keep repository-only files out of that build.

Release packaging and WordPress.org deployment tooling lives in the sibling workspace `deployment/`, not inside this public plugin package.

## Coding Style & Naming Conventions

Use PHP 8-compatible syntax and keep WordPress APIs behind explicit capability, nonce, sanitization, and escaping checks. Plugin PHP classes use the `MSTV_` prefix, plugin options and hooks use the `mstv_` prefix, and CSS/JS UI selectors generally use the existing `pdm-` interface prefix. Prefer small focused classes and follow the existing procedural bootstrap pattern.

## Testing Guidelines

Tests use PHPUnit 11 with lightweight WordPress stubs in `tests/bootstrap.php`. Name new tests after the behavior or class under test, following the existing `PDM...Test.php` pattern. Add regression tests for access control, validation, storage behavior, REST responses, translation coverage, and release-sensitive packaging behavior when relevant.

## Commit & Pull Request Guidelines

Keep commit messages short and imperative, for example `Release version 2.0.2` or `Improve GitHub project setup`. Pull requests should include a concise summary, verification commands, screenshots for UI changes, and notes about version/readme alignment when release metadata changes. `main` is protected; changes should pass all required CI checks before merge.

## Security & Release Notes

Do not expose private document paths or user data unnecessarily. Keep `mikesoft-teamvault.php`, `readme.txt`, and `changelog.txt` aligned for every release. Repository-only files such as `README.md`, `docs/`, `tests/`, `.github/`, and Composer tooling must stay out of the WordPress.org package.
