=== Private Document Manager ===
Contributors: thestreamcode
Tags: documents, private, secure, file-manager, access-control
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private document management separated from the WordPress Media Library with secure access control, previews, ZIP export, and drag-and-drop uploads.

== Description ==

Private Document Manager helps teams manage confidential documents in a protected storage area outside the normal WordPress Media Library workflow.

Files are stored in a private directory and delivered only through authenticated WordPress handlers. The plugin includes folder management, previews, access control, activity logs, and export tools in a modern admin interface.

**Main features:**

* Protected private storage outside the Media Library flow
* Role-based and user-specific access control
* Folder creation, rename, move, and delete operations
* Drag-and-drop uploads with image and PDF previews
* ZIP export for the full library or a specific folder
* Activity logging for uploads, downloads, moves, and deletes
* English default interface with optional Italian translation
* Multisite-aware database tables and secure file streaming

The plugin does not require any external service to work.

== Installation ==

1. Upload the `private-document-manager` folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress Plugins screen
3. The plugin creates its database tables and private storage directory automatically
4. Administrators and Editors receive the `manage_private_documents` capability by default
5. Open **Private Documents** in the WordPress admin menu

== Frequently Asked Questions ==

= Are the files really private? =

Yes. Files are stored in a protected directory and are not served through public direct URLs. Access is checked before preview, download, and export operations.

= Can I use selected users instead of roles? =

Yes. In the plugin settings you can enable a user whitelist and grant access only to selected WordPress users.

= Can I change the storage directory? =

Yes. You can configure a custom writable path in the plugin settings.

= What file types are supported? =

By default the plugin allows common office documents, images, archives, text files, and media files. You can customize the allowed extensions in the settings.

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
