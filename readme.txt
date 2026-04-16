=== Mikesoft TeamVault ===
Contributors: thestreamcode
Tags: documents, secure, collaboration, privacy, file-manager
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.31
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private team document storage for WordPress with controlled access, previews, ZIP export, and drag-and-drop uploads.

== Description ==

Mikesoft TeamVault provides a private document area inside the WordPress admin for teams, partners, and clients who need controlled access to shared files.

Files are stored in protected storage and served through authenticated WordPress requests instead of public Media Library URLs.

Key features:

* Private document storage separated from the Media Library workflow
* Capability-based access control with optional per-user whitelist mode
* Folder create, rename, move, and delete operations
* Drag-and-drop uploads with upload validation
* Inline preview for supported files, including PDFs
* ZIP export for folders or the full library
* Activity logging for uploads, downloads, moves, and deletions
* Maintenance tools for orphan cleanup and storage reindex
* English interface with optional Italian translation

== Installation ==

1. Upload the `mikesoft-teamvault` folder to `/wp-content/plugins/`, or install it from the WordPress plugin screen.
2. Activate the plugin.
3. Open `TeamVault > Settings`.
4. Review the storage path, allowed file types, and access settings.

== Frequently Asked Questions ==

= Are the files really private? =

Yes. Files are stored in protected storage and delivered only through authenticated WordPress handlers.

= Who can access TeamVault by default? =

Administrators and Editors receive the `manage_private_documents` capability on activation. You can also enable whitelist mode to limit access to selected users.

= Can I change the storage directory? =

Yes. You can configure a custom writable storage path in the plugin settings.

= What happens if I migrate the database but not the private files? =

The database records can remain visible even if the original binaries are missing. TeamVault includes cleanup and reindex maintenance tools for these recovery scenarios.

= Does the plugin support PDF preview? =

Yes. Inline PDF preview can be enabled or disabled in the settings.

= What happens on uninstall? =

By default, TeamVault keeps its data for safety. You can enable full data removal before uninstall if you want the plugin to delete its files, folders, logs, and settings.

== Changelog ==

= 1.1.31 =
* Improved whitelist input handling for safer user access settings processing.

= 1.1.30 =
* Fixed whitelist user selection visibility in settings.
* Fixed persistence of selected whitelist users.

= 1.1.29 =
* Added TeamVault branding in the admin interface.

For the full release history, see `changelog.txt` in the plugin package.

== Upgrade Notice ==

= 1.1.31 =

Recommended maintenance update for safer whitelist settings handling.

= 1.1.30 =

Recommended bugfix update for whitelist selection and persistence.
