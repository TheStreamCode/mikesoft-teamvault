# Security Review — 2026-10-21

## Scope and Method

This review covers the 3.4.0 working tree: the folder-aware notification path, the permission resolver and governance REST surface, the export/temp-path handling, the validator messages, the admin JavaScript rendering layer, and the test infrastructure. It combines manual source review with the expanded PHPUnit suite, behavioral node tests for the shared helpers, PHP lint on the full tree, Composer validation and audit, JavaScript syntax checks, WordPress Plugin Check on an active local staging installation (no errors), and a WP-CLI functional notification test on that installation. It was not exercised through a browser session or a real mail transport in this cycle; those release checks remain required before publication.

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

## Residual Risks

- Storage protection still depends on server configuration: the generated `.htaccess`/`web.config` cover Apache/IIS, while Nginx requires an equivalent deny rule (surfaced to operators by the settings notice).
- Header semantics for private streams remain guarded by source-level tests plus live HTTP verification; a CLI unit test cannot observe `header()` output.
- The whitelist, quota, and notification surfaces rely on the capability floor plus, where applicable, database advisory locks; they were re-verified through the live governance battery but not fuzzed beyond their regression suites.
- The notification filter resolves access per recipient and folder, which adds one cached permission lookup per event recipient; very large selected-recipient sets are bounded by the notification configuration and were not benchmarked at extreme scale.
