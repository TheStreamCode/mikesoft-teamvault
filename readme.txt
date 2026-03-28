=== Mikesoft TeamVault ===
Contributors: thestreamcode
Tags: documents, secure, collaboration, file-manager, privacy
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.26
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure private team document storage for WordPress with controlled access, previews, ZIP export, and drag-and-drop uploads.

== Description ==

Mikesoft TeamVault helps teams manage confidential documents in a protected storage area outside the normal WordPress Media Library workflow. Perfect for sharing files with partners, clients, or team members within your own hosting environment.

Files are stored in a private directory and delivered only through authenticated WordPress handlers. The plugin includes folder management, previews, access control, activity logs, and export tools in a modern admin interface.

**Main features:**

* Protected private storage outside the Media Library flow
* Shared access for teams, partners, and clients
* Role-based and user-specific access control
* Folder creation, rename, move, and delete operations
* Drag-and-drop uploads with image and PDF previews
* ZIP export for the full library or a specific folder
* Activity logging for uploads, downloads, moves, and deletes
* Orphaned-record detection and cleanup after local or staging migrations
* English default interface with optional Italian translation
* Multisite-aware database tables and secure file streaming

The plugin does not require any external service to work.

== Installation ==

1. Upload the `mikesoft-teamvault` folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress Plugins screen
3. The plugin creates its database tables and private storage directory automatically
4. Administrators and Editors receive the `manage_private_documents` capability by default
5. Open **TeamVault** in the WordPress admin menu

== Frequently Asked Questions ==

= Are the files really private? =

Yes. Files are stored in a protected directory and are not served through public direct URLs. Access is checked before preview, download, and export operations.

= Can I use selected users instead of roles? =

Yes. In the plugin settings you can enable a user whitelist and grant access only to selected WordPress users.

= Can I change the storage directory? =

Yes. You can configure a custom writable path in the plugin settings.

= What file types are supported? =

By default the plugin allows common office documents, images, archives, text files, and media files. You can customize the allowed extensions in the settings.

= Why do I see files listed but they cannot be opened after a local migration? =

The plugin stores binaries in its private storage directory, not in the Media Library. If you move the database without copying `wp-content/uploads/private-documents/` (or your custom storage path), the database records remain but the physical files are missing. The settings page includes a maintenance tool to clean orphaned records.

= What happens on uninstall? =

You can choose whether all plugin data should be removed on uninstall. By default the cleanup option is disabled for safety.

= Is an Italian interface available? =

Yes. The plugin uses English by default and includes an Italian interface option in the settings.

== Screenshots ==

1. File manager grid view with folders, file cards, previews, and quick actions.
2. File manager list view with metadata and row actions.
3. Settings page with user access controls, storage options, and plugin preferences.
4. Logs page for document activity and administrative review.

== Changelog ==

= 1.1.26 =
* Kept the mobile header toolbar on a single row by compacting filters and action controls
* Reduced the mobile footprint of the Upload and Export buttons for a cleaner responsive header

= 1.1.25 =
* Fixed file rename fallback handling for legacy records with empty display names
* Added safer display-name fallback resolution during upload, reindex, browser payload formatting, and rename flows
* Tightened the file rename request handling and added regression coverage for the rename path

= 1.1.24 =
* Renamed the plugin branding to Mikesoft TeamVault and aligned the release package with the new slug
* Removed SVG from default allowed uploads, blocked unsafe inline preview paths, and enforced the PDF preview setting
* Fixed nested folder rename path updates so descendant files keep working after folder renames
* Rejected invalid destination folder IDs instead of silently falling back to the root folder
* Improved admin UI consistency, mobile details controls, and release hardening files

= 1.1.23 =
* Refined the mobile file manager with off-canvas navigation, responsive filters, and sidebar scrolling
* Fixed rename validation edge cases and several Italian translation issues

= 1.1.22 =
* Fixed Plugin Check compliance issues in filesystem operations, schema migration safety, and packaging

= 1.1.21 =
* Hardened whitelist enforcement so role-based access and user whitelists are applied consistently across REST, admin screens, and streamed handlers
* Normalized legacy log target types, improved storage cleanup safety, and switched file delivery to chunked streaming for large exports and previews

= 1.1.20 =
* Added clearer move-destination selection feedback and restored the root node in the sidebar tree
* Completed the latest Italian translation review for pagination, maintenance, export, and storage recovery strings

= 1.1.19 =
* Fixed the remaining Plugin Check findings in uninstall cleanup and admin request sanitization paths

= 1.1.18 =
* Added automatic storage self-healing on browser load and folder creation so missing database records are restored without manual maintenance steps

= 1.1.17 =
* Added maintenance reindex to restore folder and file records from the storage directory when database entries are missing
* Restored creation of folders whose physical directory still exists after uninstall or partial cleanup

= 1.1.16 =
* Restored folder creation when a directory already exists on disk but its database record was removed

= 1.1.13 =
* Fixed Plugin Check issues around paginated queries, admin request sanitization, and filesystem fallbacks
* Normalized line endings across the plugin files flagged by the report

= 1.1.12 =
* Simplified the export modal to two choices only: export all or export selected folders

= 1.1.11 =
* Removed create/upload reliance on the WordPress filesystem abstraction for local file writes to improve compatibility on local environments
* Improved admin API error parsing so critical backend responses surface a readable message in the UI

= 1.1.10 =
* Added export choices for the full library, the current folder, or selected folders directly from the export modal
* Fixed the sort-order button icon so it matches ascending and descending states correctly
* Added live filesystem metadata fallback for preview and download streams to reduce issues with stale stored metadata

= 1.1.9 =
* Fixed upload validation regressions that could block new file uploads
* Fixed duplicate upload controls shown inside the upload overlay
* Added runtime self-healing for the private storage directory
* Added live filesystem metadata fallback so existing files keep working even if stored MIME or size metadata is stale
* Marked missing binaries clearly in the file manager and disabled invalid preview/download actions
* Added a settings maintenance action to clean orphaned file records after local migrations

= 1.1.8 =
* Standardized the main plugin interface and project documentation around English source text
* Refined contribution and README documentation for a more professional release presentation
* Repaired internal naming regressions introduced during the language normalization pass

= 1.1.7 =
* Improved binary streaming handlers for preview, download, and ZIP export
* Improved custom table handling and whitelist capability cleanup
* Hardened allowed extensions sanitization in admin settings

= 1.1.6 =
* Fixed Windows path normalization in filesystem boundary checks
* Resolved false "Invalid destination path" upload failures caused by mixed slash formats
* Improved upload compatibility for root and nested folder destinations on local Windows environments

= 1.1.5 =
* Fixed internal drag and drop so files can be moved reliably into folders
* Added drop targets for content folders, sidebar tree folders, and the root breadcrumb
* Prevented internal drag operations from incorrectly opening the upload overlay

= 1.1.4 =
* Moved folder and file quick actions from hover overlays into the details sidebar
* Clicking a folder now selects it and shows actions in the sidebar; double-click opens it
* Simplified card and list layouts by removing inline hover action areas

= 1.1.3 =
* Improved translator comments for placeholder-based strings
* Reworked file query ordering for safer database access patterns
* Replaced remaining streamed `readfile()` usage with filesystem-backed reads
* Improved server input handling in admin settings and repository logs

= 1.1.2 =
* Improved admin view escaping and packaging metadata
* Reworked streamed preview and download handlers to use authenticated admin-post endpoints with dedicated nonce support
* Reworked filesystem operations and upload handling for better WordPress compatibility
* Fixed settings form handling with safer input unslashing, redirects, and transient-based success notice
* Added `languages/` directory support and removed deprecated manual textdomain loading

= 1.1.1 =
* Security: REST API now enforces WordPress REST nonce validation in permission checks
* Security: Removed nonce usage from preview image URLs in the admin UI
* Security: Hardened download and preview filename sanitization against header injection
* Security: Strengthened filesystem base-path boundary validation
* Security: Rejects dangerous double-extension uploads like `file.php.pdf`
* Security: Added destination path checks before storing files and folders
* Fixed: Streamed download and preview URLs now use secure admin-post handlers
* Fixed: Multisite uninstall now cleans site-specific tables and options correctly
* Improved: Folder repository now caches tree and all-folder lookups during the request lifecycle
* Improved: Export modal flow now submits through admin-post with a dedicated stream nonce
* Added: Hooks are now wired into upload, rename, delete, move, preview, download, export, and folder operations

= 1.1.0 =
* Security: Fixed path traversal vulnerability in the filesystem layer
* Security: Added content sniffing to detect polyglot and malicious uploads
* Security: Improved upload validation with full content scanning
* Fixed: Multisite compatibility for plugin tables
* Fixed: ZIP export cleanup on failure with a shutdown handler
* Added: Developer hooks and filters for extensibility

For older release history, see `changelog.txt` in the plugin package.

== Upgrade Notice ==

= 1.1.26 =

Recommended mobile UI refinement update. Keeps the file manager header controls on a single row on small screens.

= 1.1.25 =

Recommended maintenance update for file rename reliability and safer display-name fallback handling.

= 1.1.24 =

Recommended security and consistency update. Improves release packaging, blocks unsafe preview scenarios, and fixes nested folder rename path handling.

= 1.1.23 =

Recommended mobile UX update. Improves off-canvas navigation, filters, and responsive file manager behavior.

= 1.1.22 =

Recommended maintenance update for Plugin Check compliance and safer low-level filesystem and migration handling.

= 1.1.20 =

Recommended UX and localization update. Improves move selection clarity, restores the root node in navigation, and completes recent Italian translations.

= 1.1.19 =

Recommended maintenance update for final Plugin Check cleanup on uninstall and admin request handling.

= 1.1.18 =

Recommended usability update. The plugin now attempts automatic recovery from leftover storage data without requiring technical maintenance actions.

= 1.1.17 =

Recommended maintenance update for recovering folders and files that still exist on disk after uninstall or partial data loss.

= 1.1.16 =

Recommended maintenance update for environments where physical folders survived after uninstall while database records were removed.

= 1.1.13 =

Recommended maintenance update for Plugin Check compliance and cleaner filesystem compatibility fallbacks.

= 1.1.12 =

Recommended UX refinement. The export modal now offers only full-library export or selected-folder export.

= 1.1.11 =

Recommended compatibility fix for local environments. Improves folder creation and uploads, and makes backend failures easier to diagnose from the UI.

= 1.1.10 =

Recommended feature and maintenance update. Adds selective folder export, fixes sort direction feedback, and makes preview/download more resilient to stale stored metadata.

= 1.1.9 =

Recommended maintenance update. Fixes upload regressions, improves missing-file handling after local migrations, and adds orphaned-record cleanup.

= 1.1.8 =

Recommended maintenance update. Improves English-first presentation, documentation quality, and release consistency.

= 1.1.7 =

Recommended maintenance update. Improves streaming reliability, settings hardening, and release quality.

= 1.1.6 =

Recommended upload stability fix for Windows and Local environments. Prevents false path validation failures during file uploads.

= 1.1.5 =

Recommended drag-and-drop fix. Internal file moves now work reliably across the document browser and folder tree.

= 1.1.4 =

Recommended UI update. File and folder actions now live in the details sidebar for a cleaner browser experience.

= 1.1.3 =

Recommended maintenance update. Improves translator metadata, streaming behavior, and request sanitization.

= 1.1.2 =

Recommended maintenance update. Improves admin escaping, upload handling, and streamed file access.

= 1.1.1 =

Recommended security and release-hardening update. Improves nonce enforcement, streaming security, upload validation, and multisite cleanup.

== Security Considerations ==

This plugin includes multiple WordPress.org-friendly security measures:

* Capability-based access checks
* WordPress nonce validation for mutating REST requests
* Deep upload validation for extension, MIME type, size, and dangerous content patterns
* Path boundary validation on the server side
* Authenticated preview, download, and export handlers
* Private storage protected from direct public access

== Credits ==

Author: Michael Gasperini - https://mikesoft.it

Supported languages: English, Italian
