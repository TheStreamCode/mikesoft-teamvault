# AGENTS.md

Repository guidance for agentic coding tools working in `mikesoft-teamvault-src`.

## Scope
- Applies to the whole repository rooted at `mikesoft-teamvault-src/`
- Runtime plugin slug: `mikesoft-teamvault`
- Main plugin file: `mikesoft-teamvault.php`
- Local source checkout is intentionally separate from the generated release folder/zip

## Extra Rule Files
- No `.cursor/rules/` directory found
- No `.cursorrules` file found
- No `.github/copilot-instructions.md` file found

## Read First
- `README.md` — product overview and release workflow
- `CONTRIBUTING.md` — development setup and contribution rules
- `readme.txt` — WordPress.org metadata and changelog summary
- `changelog.txt` — full release history

## Important Paths
- `mikesoft-teamvault.php` — plugin header, constants, bootstrap entrypoint
- `includes/` — PHP services, repositories, REST, storage, auth, settings
- `admin/views/` — admin templates
- `assets/js/admin-app.js` — admin UI logic
- `assets/css/admin.css` — admin styles and responsive rules
- `tests/` — PHPUnit tests with a WordPress shim bootstrap

## Setup Commands
Install dev dependencies:

```bash
composer install
```

## Test Commands
Run the full suite:

```bash
./vendor/bin/phpunit --bootstrap tests/bootstrap.php tests
```

Run one test file:

```bash
./vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/PDMRestControllerTest.php
```

Run one test method:

```bash
./vendor/bin/phpunit --bootstrap tests/bootstrap.php --filter test_update_file_accepts_valid_name_even_if_existing_display_name_is_empty tests/PDMRestControllerTest.php
```

## Lint / Verification Commands
Lint one PHP file:

```bash
php -l includes/class-pdm-rest-controller.php
```

Lint several key files:

```bash
php -l mikesoft-teamvault.php && php -l includes/class-pdm-helpers.php && php -l includes/class-pdm-storage.php
```

Check JavaScript syntax:

```bash
node --check assets/js/admin-app.js
```

## Release Packaging
There is no checked-in packaging script. Build the release zip from source into a clean runtime folder.

Preferred Windows packaging command:

```bash
powershell -Command "\
$src='C:\Users\Mike\Desktop\Workspace\wp-private-document-manager\mikesoft-teamvault-src'; \
$dst='C:\Users\Mike\Desktop\Workspace\wp-private-document-manager\mikesoft-teamvault'; \
if (Test-Path $dst) { Remove-Item $dst -Recurse -Force }; \
New-Item -ItemType Directory -Path $dst | Out-Null; \
robocopy $src $dst /MIR /XD .git .worktrees tests vendor /XF composer.json composer.lock CONTRIBUTING.md | Out-Null; \
if ($LASTEXITCODE -gt 3) { exit $LASTEXITCODE }; \
if (Test-Path 'C:\Users\Mike\Desktop\Workspace\wp-private-document-manager\mikesoft-teamvault.zip') { Remove-Item 'C:\Users\Mike\Desktop\Workspace\wp-private-document-manager\mikesoft-teamvault.zip' -Force }; \
Compress-Archive -Path 'C:\Users\Mike\Desktop\Workspace\wp-private-document-manager\mikesoft-teamvault' -DestinationPath 'C:\Users\Mike\Desktop\Workspace\wp-private-document-manager\mikesoft-teamvault.zip' -Force"
```

After packaging, verify the zip root contains `mikesoft-teamvault/` and `mikesoft-teamvault/mikesoft-teamvault.php`.

## Architecture Guidelines
- Keep `mikesoft-teamvault.php` minimal: constants, bootstrap include, init call
- Prefer implementing behavior in `includes/` classes rather than the main plugin file
- Keep admin-only behavior in admin classes, views, or admin JS/CSS
- Preserve the current class-based architecture; do not introduce namespaces casually

## Naming Conventions
- PHP classes: `PDM_Class_Name`
- PHP methods: mostly `snake_case()`
- JavaScript methods/properties: `camelCase`
- CSS classes: `.pdm-*`
- Text domain: `mikesoft-teamvault`
- WP option/action/filter/data prefix: `pdm_`
- Product/UI name: `Mikesoft TeamVault`
- Root navigation label: `Home`

## PHP Style
- Follow WordPress coding standards where practical, while preserving existing repository style
- Use `defined('ABSPATH') || exit;` in plugin PHP files that should block direct access
- Use strict scalar/return types when already present or low-risk to add
- Prefer constructor injection for shared services already passed around the codebase
- Prefer early returns over nested conditionals
- Keep methods focused; extract helpers instead of growing large methods
- Reuse existing helpers for sanitization, formatting, and path handling

## Error Handling
- In REST endpoints, return `WP_Error` with a clear code, translated message, and proper status
- Validate first, sanitize second, and re-check emptiness after sanitization when content can collapse to empty
- Do not silently coerce malformed input into a different valid value
- For filesystem/database multi-step operations, prefer explicit failure handling where integrity matters
- Use the existing logger for meaningful state-changing actions

## Security Rules
- Always enforce capability/authorization checks; admin context alone is not enough
- Use nonces for state-changing browser requests outside REST auth where appropriate
- Sanitize input with the most specific sanitizer available
- Escape output late in templates with the correct WordPress escaping function
- Prefer `$wpdb->prepare()`, `$wpdb->update()`, and `$wpdb->insert()` over interpolated SQL
- Do not reintroduce SVG as a default allowed upload type without real sanitization
- Respect the PDF preview setting both in preview routes and browser payload metadata

## JavaScript Rules
- Keep browser logic inside the existing `PDM` object in `assets/js/admin-app.js`
- Guard initialization by screen/DOM presence; do not assume the file manager exists on every admin page
- Keep API payloads explicit and aligned with registered REST args
- Add frontend fallbacks when legacy server data may be empty

## CSS / UI Rules
- Extend the existing CSS variable system and `.pdm-*` class patterns
- Preserve responsive behavior unless intentionally refining it
- Prefer targeted responsive tweaks over broad visual rewrites
- Avoid mobile toolbar layouts that wrap controls unpredictably when a single-row layout is intended

## Testing Expectations
- Bug fixes should add/update a regression test when practical
- Prefer targeted PHPUnit tests for helpers, REST handlers, and repository logic
- For UI-only changes, at minimum run syntax checks and document manual verification steps
- If upload, rename, preview, move, storage, or path logic changes, run the full PHPUnit suite

## Documentation Expectations
- Update `readme.txt` when WordPress.org-facing behavior or versions change
- Update `README.md` when GitHub-facing setup, workflow, or architecture changes
- Update `changelog.txt` for every released version
- Keep versions aligned across `mikesoft-teamvault.php`, `readme.txt`, `README.md`, and `changelog.txt`

## Git Hygiene
- Keep `mikesoft-teamvault-src/` separate from generated release artifacts
- Do not commit `vendor/` unless policy changes explicitly
- Do not commit generated release folders
- Keep commit messages short, imperative, and behavior-specific

## Agent Reminders
- This plugin must handle legacy data safely; do not assume all old records are clean
- Prefer consistency with existing repository patterns over novelty
- If a bug appears to persist after a source fix, compare the installed WordPress plugin copy against local source before assuming the code is still wrong
