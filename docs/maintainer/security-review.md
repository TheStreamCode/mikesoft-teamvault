# Security Review — 2026-08-08

## Scope and Method

This review covers the PHP plugin bootstrap and classes, REST and admin actions, browser JavaScript, storage and filesystem boundaries, database access, dependency metadata, tests, CI, and release filtering in the full maintainer workspace. It combines manual source review with PHP lint, PHPUnit, JavaScript syntax tests, Composer validation/audit, packaging tests, and inspection of the clean release payload.

No critical issue was identified. The findings below were fixed in the reviewed working tree and have regression coverage where practical. This document is a point-in-time engineering review, not a guarantee against every vulnerability or a substitute for production configuration testing.

## Resolved Findings

### SEC-001 — Missing output encoding for the avatar initial

- Severity: Low
- Status: Fixed in 3.2.4
- Location: `assets/js/admin-app.js` (user-search result rendering)
- Rule: `web-frontend-xss-dom-001`
- Evidence: the first character of the WordPress display name was interpolated into HTML without contextual escaping.
- Impact: the current one-character truncation does not provide enough content for a demonstrated executable payload, but leaving a stored user value unencoded in an HTML sink is fragile and could become exploitable if the rendering changes.
- Resolution: the initial is now passed through the existing HTML escaper before interpolation. A regression test verifies escaping.
- False-positive notes: this is defense in depth rather than a confirmed stored-XSS exploit. WordPress sanitizes profile fields and the current sink uses one character, but output encoding is still the correct boundary.

### SEC-002 — Unvalidated REST-provided browser destinations

- Severity: Low
- Status: Fixed in 3.2.4
- Location: `assets/js/admin-app.js`, `assets/js/admin-app-core.js`, and `assets/js/admin-app-governance.js`
- Rule: `web-frontend-untrusted-url-001`
- Evidence: preview, download, and audit-export URLs from localized or REST data were assigned to `href`, `src`, `window.location`, or `window.open` without an explicit protocol and origin allowlist. Some preview URLs were also inserted into HTML attributes without escaping.
- Impact: an unexpected server or extension-provided value could redirect an administrator or create an unsafe URL sink.
- Resolution: a shared normalizer now accepts only same-origin HTTP(S) URLs; HTML attributes are escaped and new tabs use `noopener,noreferrer`. Regression tests cover rejection of cross-origin and dangerous schemes.
- False-positive notes: no user-controlled URL source was demonstrated and current server-generated URLs are normally same-origin. The fix adds defense in depth at each browser sink.

### SEC-003 — Cache semantics for private file responses

- Severity: Medium
- Status: Fixed in 3.2.4
- Location: `includes/class-mstv-download.php`, `includes/class-mstv-preview.php`, and `includes/class-mstv-export.php`
- Evidence: the handlers called WordPress `nocache_headers()` and then replaced part of that policy with `must-revalidate` and `Pragma: public` headers.
- Impact: browsers or intermediaries could retain authenticated private content longer than intended, depending on their interpretation of the conflicting headers.
- Resolution: the conflicting overrides were removed. WordPress no-cache response headers remain authoritative for previews, downloads, and exports. Static regression tests prevent the public cache directives from returning.
- False-positive notes: the prior `must-revalidate` directive was not equivalent to a strict no-store policy and `Pragma: public` was actively misleading for private documents.

### SEC-004 — Absolute private paths in diagnostic logs

- Severity: Low
- Status: Fixed in 3.2.4
- Location: `includes/class-mstv-download.php` and `includes/class-mstv-preview.php`
- Evidence: read and stream failures included the absolute storage path in PHP error logs.
- Impact: anyone with log access could learn host directory layout and private storage locations, increasing the value of another local or configuration weakness.
- Resolution: diagnostics now include only the internal numeric file identifier. Tests verify that path variables are not passed to `error_log` in these handlers.
- False-positive notes: logs are server-side, but least-disclosure still applies because hosting logs are commonly aggregated or shared with support tooling.

### SEC-005 — Settings language accepted a broader value set than the UI

- Severity: Low
- Status: Fixed in 3.2.4
- Location: `includes/class-mstv-admin.php` (`handle_save_settings`)
- Evidence: the direct admin-post handler used generic text sanitization for the interface language instead of the existing supported-language allowlist.
- Impact: crafted requests could persist unsupported configuration and create inconsistent fallback behavior.
- Resolution: the handler now uses `MSTV_I18n::sanitize_language()`. A regression test asserts that the allowlist path is used.
- False-positive notes: nonce and capability checks already protected the action; this finding concerns validation and configuration integrity.

### SEC-006 — Stored file extension reached an HTML sink unescaped

- Severity: Low
- Status: Fixed in 3.2.5
- Location: `assets/js/admin-app-core.js` (file details panel)
- Rule: `web-frontend-xss-dom-001`
- Evidence: the details panel interpolated `files.extension` into an `innerHTML` template literal without contextual escaping, while every neighbouring value in the same block used the shared HTML escaper.
- Impact: no exploit path was demonstrated. `MSTV_Validator::validate_extension()` restricts the stored value to `^[a-z0-9]+$` plus the configured allowlist on both the upload and the storage-reindex paths, so the field cannot currently carry markup. The gap was an inconsistent output boundary, not a confirmed stored XSS.
- Resolution: the value is coerced to a string and passed through the existing escaper before interpolation. A regression test asserts the escaped form is present and the raw form is absent.
- False-positive notes: this is the same class as SEC-001 and completes the 3.2.4 output-encoding pass; after this change no server-derived value in the admin JavaScript reaches an HTML sink unescaped.

## Release Review — 3.2.6

The full repository was reviewed again on 2026-08-08, including lifecycle and multisite behavior, REST authorization and validation, governance settings, upload/storage boundaries, ZIP export, browser rendering and URL sinks, SQL construction, uninstall cleanup, CI, packaging, and deployment tooling. The existing public API namespace, response shapes, database schema, option names, and runtime visual assets remain unchanged. The resolved findings below ship in version 3.2.6.

### SEC-007 — Upload writes did not use the complete symlink-aware path verifier

- Severity: Medium
- Status: Fixed in 3.2.6
- Location: `includes/class-mstv-storage.php` (`store_uploaded_file`)
- Rule: `filesystem-path-boundary-001`
- Evidence: the upload destination used a lexical storage-root check before opening the destination, while other filesystem writes used `MSTV_Filesystem::get_verified_path()`, which also rejects symlinks in intermediate path components.
- Impact: if a local process or storage operator had already placed an intermediate symlink inside the TeamVault storage tree, an authorized upload could follow it and write the generated file outside the configured storage root.
- Resolution: upload destinations now pass through the shared boundary and symlink verifier before any destination stream is opened. A regression test verifies that a rejected destination is never resolved or written through the weaker path.
- False-positive notes: no remote method for creating such a symlink was found. Exploitation requires an already modified storage filesystem, but the prior behavior weakened an explicit containment boundary and could amplify a local or shared-storage compromise.

### SEC-008 — REST boolean strings could invert governance settings

- Severity: Low
- Status: Fixed in 3.2.6
- Location: `includes/class-mstv-rest-governance.php` (`update_notifications`, `update_quotas`)
- Evidence: direct PHP boolean casts treat the string `"false"` as true, including the administrator-recipient flag.
- Impact: a nonstandard but valid REST client could unintentionally enable notifications, administrator recipients, or quota enforcement while sending a false boolean string.
- Resolution: the affected fields now use WordPress boolean normalization. Regression tests cover string-form false values without changing the JSON response contract.

### SEC-009 — Group updates accepted a name that became empty after sanitization

- Severity: Low
- Status: Fixed in 3.2.6
- Location: `includes/class-mstv-rest-governance.php` (`update_group`)
- Evidence: the update path checked the raw name for emptiness but did not repeat the check after `sanitize_text_field()`, unlike group creation.
- Impact: a crafted administrator request could persist an empty display name and a fallback slug, degrading governance data integrity and usability.
- Resolution: the sanitized name is now validated before the transaction starts, with the existing validation error shape and regression coverage.

### SEC-010 — Site-local activation could initialize unrelated new multisite sites

- Severity: Low
- Status: Fixed in 3.2.6
- Location: `includes/class-mstv-bootstrap.php` (`initialize_site`)
- Evidence: the `wp_initialize_site` hook initialized TeamVault tables and defaults whenever the plugin happened to be loaded, even when it was active only for one existing site rather than network-wide.
- Impact: creating another site in the network could receive TeamVault schema and options outside the plugin's intended activation scope.
- Resolution: new-site initialization now runs only when TeamVault is network-active. A regression test covers both inactive and network-active states.

### SEC-011 — ZIP creation could continue after archive write failures or cyclic metadata

- Severity: Low
- Status: Fixed in 3.2.6
- Location: `includes/class-mstv-export.php`
- Evidence: return values from ZIP entry writes and finalization were not checked, and recursive traversal relied entirely on the database parent invariant.
- Impact: storage or ZIP errors could produce an incomplete download reported as successful; manually corrupted cyclic folder metadata could cause unbounded recursive traversal during export.
- Resolution: entry writes and finalization now fail closed, `Throwable` is handled by the existing cleanup path, and visited folder identifiers bound recursive traversal. The cycle guard has regression coverage where ZipArchive is available.

### SEC-012 — Details panel covered toolbar actions in the WordPress desktop layout

- Severity: Low
- Status: Fixed in 3.2.6
- Location: `assets/css/admin.css`, `assets/js/admin-app-core.js`
- Evidence: at a 1280px browser viewport, WordPress's 160px desktop admin menu reduced the plugin canvas below the intended 1200px breakpoint. The inline details panel covered the Upload action and intercepted pointer input.
- Impact: administrators on common laptop-sized desktop viewports could see Export but could not activate Upload with a pointer, despite the control remaining in the DOM.
- Resolution: the details drawer threshold now includes the WordPress desktop admin-menu width, preserving the original 1200px app-content target. Static regression coverage and an isolated browser smoke test verify that the action remains visible and clickable.

## Residual Risks and Recommendations

### SR-001 — Audit-log retention is not automated

- Priority: Medium
- Location: `includes/class-mstv-repository-logs.php` (`delete_old`)
- Risk: audit records contain user, IP-address, and user-agent metadata and can grow without a scheduled retention policy.
- Recommendation: add an administrator-controlled retention period and a WP-Cron cleanup task, with an explicit disabled/forever option. This was not applied because automatic deletion changes data-retention behavior and requires a product policy decision.

### SR-002 — ZIP exports lack a total-byte or execution-budget limit

- Priority: Medium
- Location: `includes/class-mstv-export.php` (`MAX_EXPORT_FILES` and recursive ZIP creation)
- Risk: the 5,000-file ceiling limits item count but not aggregate bytes, compression time, or temporary-disk use. An authorized user could exhaust constrained hosting resources with a very large export.
- Recommendation: introduce configurable aggregate-byte, elapsed-time, and free-space guards with a clear user-facing error. This was not applied because choosing safe defaults can change currently valid export behavior.

### SR-003 — Web-server protection remains deployment-dependent

- Priority: Medium
- Location: default storage under `wp-content/uploads/private-documents/`
- Risk: Nginx does not honor `.htaccess`; a host without an equivalent deny rule could expose files directly if their paths become known.
- Recommendation: prefer storage outside the public webroot for sensitive deployments, enforce a tested Nginx deny rule where applicable, and verify direct HTTP denial after every infrastructure change. The plugin cannot safely rewrite external web-server configuration.

### SR-004 — Large modules increase security-review cost

- Priority: Low
- Location: the REST controller, i18n catalog, and admin JavaScript/CSS modules
- Risk: monolithic files and manual HTML assembly make authorization, escaping, and regression review harder.
- Recommendation: incrementally extract cohesive route handlers and rendering helpers behind compatibility-preserving interfaces. Avoid a broad rewrite; add tests before each extraction.

### SR-005 — Deprecated transitive package in local wp-env tooling

- Priority: Low
- Location: CI-only `@wordpress/env@11.12.0` dependency tree
- Risk: npm emits a deprecation warning for transitive `glob@10.5.0`. This package is used only while creating the disposable Plugin Check environment and is not shipped with TeamVault.
- Recommendation: monitor `@wordpress/env` releases and refresh the pinned version after its upstream dependency is updated. A forced nested override was not added because the repository has no Node runtime dependency graph and an override could destabilize WordPress test tooling.

## Positive Controls Observed

- Privileged admin and REST operations generally pair capability checks with nonces or permission callbacks.
- SQL values are prepared and dynamic identifiers or sort directions are allowlisted.
- Filesystem access uses storage-root containment, verified paths, and symlink defenses.
- Upload and archive names are normalized, sensitive downloads use `nosniff`, and previews restrict supported types.
- Composer's locked dependencies had no known security advisory or abandoned package at review time.
- Release filtering excludes repository-only files, secrets, development dependencies, caches, and generated archives.
- WordPress Plugin Check 2.0.0 completed without errors against the filtered 55-file payload on WordPress 7.0.2.

## Production Verification Still Required

Before publishing a maintenance release, run WordPress Plugin Check against the filtered package and exercise permissions, whitelist mode, upload, preview, download, export, move, rename, delete, maintenance, and uninstall flows in a representative WordPress environment. Verify direct HTTP denial for the active storage root on the actual web server.
