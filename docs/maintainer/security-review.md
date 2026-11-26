# Security Review — 2026-11-26

## Scope and Method

This review covers the 3.5.1 working tree: the maximum-file-size settings fix, the WordPress 7.2 compatibility review and release metadata, and the previously reviewed permission, notification, export, and storage surfaces. It combines manual source review with the full PHPUnit suite (231 tests, 801 assertions) on PHP 8.4 and PHP 8.6, Composer validation and audit, PHP lint, JavaScript syntax checks, behavioral node tests, WordPress Plugin Check on a clean runtime payload under WordPress 7.2 RC1 on PHP 8.4 and PHP 8.5 (no errors or warnings), and scripted browser QA on the same WordPress 7.2 RC1 staging installation. The browser pass covered the admin menu icon in default, hover/current, and Coffee color-scheme states; folder creation and deletion; file upload, rename, move, download, image preview, and delete; a Settings save that persisted and then removed the uninstall data opt-in; the Activity Log and Reports screens; and a debug.log check. Real mail transport, Nginx-specific storage configuration, multisite, whitelist mode, and end-to-end uninstall data removal were not exercised in this cycle; the latter two remain covered by their PHPUnit suites. The Studio staging site uses SQLite, where the MySQL advisory quota lock is unavailable, so uploads were smoke-tested with quotas disabled; quota enforcement remains covered by its unit suite.

No critical issue remains open in this cycle. The findings below were fixed in the reviewed tree and carry regression coverage where practical. This document is a point-in-time engineering review, not a guarantee against every vulnerability or a substitute for production configuration testing.

## Resolved Findings

### SEC-005 — Governance: free-fallback users could manage permission rules

- Severity: Medium
- Status: Fixed in 3.3.0
- Location: `includes/class-mstv-rest-governance.php`, `includes/class-mstv-permissions.php`
- Evidence: the per-folder permission endpoints gated on the effective `manage` action, which the capability-based free fallback grants to every workspace user for folders without explicit rules. Any non-admin holding the plugin capability could therefore read and rewrite the permission rules of unruled folders, including the virtual root.
- Resolution: rule management is now allowed only to administrators or users whose `manage` grant comes from an explicit rule (nearest-ancestor). The browser payload exposes a `can_manage_rules` flag so the interface hides the rule editor for users the server would refuse. Verified live: a capability-holding editor receives 403 on rule endpoints and sees no permissions button.
- Compatibility note: this is the only deliberate behavior change of the release and is called out in the upgrade notice.

### SEC-006 — Export: malformed temporary path broke ZIP exports

- Severity: Medium (availability)
- Status: Fixed in 3.3.0
- Location: `includes/class-mstv-export.php`
- Evidence: `wp_tempnam()` concatenates the provided directory as-is, and the caller passed `sys_get_temp_dir()` without a trailing slash, producing paths such as `.../TempQA-Folder-x.tmp`. On configurations where that path could not be created, folder and library ZIP exports failed. The unit-test shim masked the defect by adding a separator.
- Resolution: both temp-directory branches now pass trailing-slashed paths; the shim mirrors core concatenation; a regression test fails against the previous construction. Verified live against the staging export endpoints.

### SEC-007 — Audit CSV: filter/routing parameter collision

- Severity: Low
- Status: Fixed in 3.3.0
- Location: `assets/js/admin-app-governance.js`, `includes/class-mstv-admin.php`
- Evidence: the reports action filter traveled under the `action` query variable, overwriting the `admin-post.php` routing parameter and silently breaking the audit CSV download whenever a filter was active.
- Resolution: the filter travels as `log_action` end-to-end; regression assertions guard both sides.

### SEC-008 — Hardening pass on the client rendering layer

- Severity: Low
- Status: Fixed in 3.3.0
- Location: `assets/js/admin-app-core.js`, `assets/js/admin-app-governance.js`, `assets/js/admin-app.js`
- Evidence: localized strings interpolated into HTML attributes, governance enum fallbacks, and REST-derived identifiers reached sinks without mechanical escaping (all values server-controlled, so defense in depth rather than an exploitable path).
- Resolution: attribute sinks route through the shared escaper; enum fallbacks are escaped; IDs are normalized with `parseInt`; the preview download binding is null-guarded. Shared pure helpers now carry behavioral node tests, and the packaging excludes repository-only metadata files (the only WordPress Plugin Check warning).

### SEC-009 — Notifications: recipients could receive document names from folders they cannot view

- Severity: Medium (information disclosure)
- Status: Fixed in 3.4.0
- Location: `includes/class-mstv-notifications.php`, `includes/class-mstv-bootstrap.php`, `includes/class-mstv-download.php`
- Evidence: notification recipients were resolved once from the global administrator/user/group selection and reused for every event. A group selected for upload notifications received the document name even when the folder that produced the event was governed by rules that excluded that group. The existing `mstv_folder_notifications` option was not used for routing.
- Resolution: each queued event now carries its `folder_id`; non-administrator recipients are filtered through `MSTV_Permissions::user_can($userId, $folderId, 'view')`, administrators bypass the filter when the administrator recipient option is enabled, and events are grouped by recipient set so one email cannot mix files from folders with different access rules. Download events now include the folder in their hook payload. The behavior is enabled by default and can be disabled with the Notifications setting; the `mstv_notification_recipients` filter is the documented extension point. Regression tests cover group filtering, administrator bypass, direct recipients, downloads, opt-out, and cross-folder batching.
- Compatibility note: existing sites begin filtering after the update; the setting restores the previous global behavior. This behavior change is called out in the upgrade notice.

### SEC-010 — Storage: duplicate rows and unprotected storage acceptance

- Severity: Medium
- Status: Fixed in 3.5.0
- Location: `includes/class-mstv-activator.php`, `includes/class-mstv-storage.php`, `includes/class-mstv-rest-controller.php`, `includes/class-mstv-repository-folders.php`, `includes/class-mstv-repository-files.php`
- Evidence: folder and file uniqueness was enforced only by application checks, so concurrent or legacy writes could create duplicate names or relative paths and leave ambiguous database and storage state. Separately, uploads could proceed when the private storage directory was not writable or when its protection files could not be created, leaving private documents reachable from the web root.
- Resolution: an option-guarded migration adds `UNIQUE (parent_key, name)` on folders and `UNIQUE (relative_path)` on files after a dedupe pass (lowest folder id survives; children, files, and per-folder rules are re-pointed; duplicate subtrees are rewritten). Duplicate inserts return 409 conflicts and the reindex path skips duplicates; the auto-reindex claim is atomic and released on failure. Uploads now fail closed when storage is not writable or `.htaccess`, `web.config`, and `index.php` cannot be created, reporting the failing files, and the admin notice is persistent and non-dismissible for blocking states and Nginx. Tests cover duplicate creation, migration dedupe, reindex locking, and storage protection.
- Compatibility note: the first update runs the one-time deduplication automatically; it is called out in the upgrade notice.

### SEC-011 — Settings: invalid upload limit discarded the uninstall data opt-in

- Severity: Medium (data retention/privacy)
- Status: Fixed in 3.5.1
- Location: `admin/views/settings-page.php`, `includes/class-mstv-settings.php`, `includes/class-mstv-admin.php`, `includes/class-mstv-rest-controller.php`
- Evidence: the plugin default `mstv_max_file_size` was 50 MB, while the settings input rendered `max` from `wp_max_upload_size()`. On servers with a lower PHP upload limit (for example 30 MB), the page loaded with an invalid value and the browser blocked the entire form submission. No option was saved, including "Delete all data on uninstall"; uninstalling the plugin then left the private storage and database tables in place. The server also accepted any `absint` value because only the HTML attribute enforced the bound.
- Resolution: the settings model now exposes the server limit and an effective value; the admin and REST save paths clamp the value to the server limit; the settings page renders the effective value and omits the `max` attribute when the server limit is unknown. A success notice explains any adjustment. Regression tests cover the settings model, admin handler, view, and REST update path. Verified live on WordPress 7.2 RC1: the form saves with the default 50 MB value, the uninstall opt-in persists, and the option is restored when unchecked.
- Compatibility note: existing installs keep their stored value until the next save; the settings page and upload paths always use the effective limit.

## Residual Risks

- Storage protection still depends on server configuration: the generated `.htaccess`/`web.config` cover Apache/IIS, while Nginx requires an equivalent deny rule (surfaced to operators by the persistent notice; out-of-webroot storage is the documented recommendation).
- Header semantics for private streams remain guarded by source-level tests plus live HTTP verification; a CLI unit test cannot observe `header()` output.
- The whitelist, quota, and notification surfaces rely on the capability floor plus, where applicable, database advisory locks; they were re-verified through the live governance battery but not fuzzed beyond their regression suites.
- The notification filter resolves access per recipient and folder, which adds one cached permission lookup per event recipient; very large selected-recipient sets are bounded by the notification configuration and were not benchmarked at extreme scale.
- The uniqueness migration scans folders and files once during the upgrade; very large libraries pay a one-time upgrade cost that was not benchmarked at extreme scale.
- The Studio staging site uses SQLite, which cannot execute the MySQL `GET_LOCK` advisory lock used for quota enforcement. Uploads were smoke-tested with quotas disabled; the quota lock path remains covered by unit tests but was not exercised against MySQL in this cycle.
