# AGENTS.md

## Scope and Source of Truth

These instructions apply to the entire `mikesoft-teamvault-src` repository. This repository is the source of truth for the public Mikesoft TeamVault WordPress plugin; generated packages, `.deploy/` directories, installed WordPress copies, and the WordPress.org SVN checkout are outputs, not editable sources.

Before changing code, inspect `git status`, preserve unrelated work, and read the nearest documentation for the area being modified. Do not commit, push, tag, publish a GitHub release, or deploy to WordPress.org unless the user explicitly requests it.

## Repository Map

- `mikesoft-teamvault.php`: plugin bootstrap, metadata, constants, activation hooks.
- `includes/`: PHP domain, REST, storage, permission, audit, export, and lifecycle classes.
- `admin/views/`: server-rendered admin views. Escape output at the last responsible point.
- `assets/js/` and `assets/css/`: admin application behavior and presentation. Preserve the existing `pdm-` compatibility prefix.
- `tests/`: PHPUnit regression tests and JavaScript parser tests.
- `tools/`: local validation helpers used by Composer and CI.
- `.wordpress-org/assets/`: WordPress.org listing assets; these are not runtime plugin files.
- `docs/`: maintainer and integration documentation; exclude it from release packages.

The full maintainer workspace may also contain sibling `deployment/` and `mikesoft-teamvault-svn/` directories. Their release automation and published history are separate from this Git repository.

## Change Rules

- Preserve existing functionality, public hooks, REST routes, option names, database schema, and response shapes unless a breaking change is explicitly approved.
- Use the `MSTV_` PHP class prefix and `mstv_` hooks/options. Existing `PDM...Test.php` and `pdm-` UI names are compatibility conventions, not rename targets.
- Keep changes focused. Do not reformat unrelated files or overwrite concurrent/untracked work.
- Do not change design, icons, screenshots, logos, or other visual assets unless the task explicitly requires it.
- Treat public methods and hooks as extension points even when repository-local usage is not apparent.
- Keep `mikesoft-teamvault.php`, `readme.txt`, `changelog.txt`, and README release metadata aligned when preparing a release. Do not bump versions for unreleased maintenance work unless requested.

## Security Requirements

- Pair every privileged action with the appropriate WordPress capability check and nonce or REST permission callback.
- Sanitize and validate input at ingress; use strict allowlists for enum-like values. Escape HTML, attributes, URLs, and JavaScript data for the output context.
- Use `$wpdb->prepare()` for values and explicit allowlists for identifiers or sort directions.
- Preserve storage-root containment, `realpath` checks, and symlink defenses for every filesystem operation.
- Serve private documents with no-cache/private semantics. Never log absolute private paths, credentials, tokens, file contents, or personal data.
- Validate browser destinations before assigning them to `href`, `src`, `window.location`, or `window.open`; application-generated file URLs must remain same-origin HTTP(S).
- Add a regression test whenever fixing an authorization, validation, path-handling, output-escaping, or data-integrity issue.

## Environment and Secrets

TeamVault runtime configuration is stored in WordPress options; the plugin does not require a repository `.env` file. Advanced custom storage roots are operator-provisioned and must remain writable by WordPress while being protected from direct web access.

Never commit `.env*`, `auth.json`, Composer credentials, WordPress salts, database dumps, API keys, deployment credentials, generated archives, or production data. Keep real secrets in the host, CI secret store, or operating-system credential manager. The `.gitignore` exception for `.env.example` is reserved for a future sanitized template only.

## Validation Commands

Run commands from the repository root. Install exact locked development dependencies first:

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer audit --locked
composer ci
node --check assets/js/admin-app-core.js
node --check assets/js/admin-app-governance.js
node --check assets/js/admin-app.js
node --check assets/js/admin-notice-dismiss.js
node --check tools/parse-plugin-check-output.mjs
node --test tests/plugin-check-output.test.mjs
```

`composer ci` runs PHP lint and PHPUnit. The plugin supports PHP 8.0+, while PHPUnit 11 requires PHP 8.2+; CI therefore lints PHP 8.0-8.4 and runs unit tests on PHP 8.2-8.4.

When the full maintainer workspace is available, also run:

```powershell
Invoke-Pester .\deployment\DeployWordPressOrg.Tests.ps1
```

For release-sensitive changes, run WordPress Plugin Check against a clean filtered payload and perform manual WordPress QA for permissions, whitelist mode, upload, preview, download, rename, move, delete, ZIP export, maintenance tools, and uninstall behavior.

## Packaging and Pull Requests

Runtime packages must exclude repository metadata, tests, docs, Composer files, CI configuration, `.wordpress-org/`, `.env*`, `auth.json`, caches, coverage, `node_modules`, `vendor`, and prebuilt ZIP files. WordPress.org listing assets are synchronized separately.

Use concise imperative commit messages. Pull requests should state behavior affected, security or data implications, verification commands and results, manual QA performed, and screenshots only when the UI intentionally changed. Keep CI green and call out any check that could not be run locally.
