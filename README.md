# Mikesoft TeamVault

[![Plugin Version](https://img.shields.io/badge/version-1.1.29-blue.svg)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-Published-green.svg)](https://wordpress.org/plugins/mikesoft-teamvault/)

**Secure shared document management for WordPress**, fully separated from the Media Library. Perfect for teams, partners, and clients who need a private space to collaborate on documents within your own hosting environment.

## Features

- **Shared Team Storage** - Files stored in protected directory, accessible only to authorized users
- **Team Access Control** - Limit access to specific users, roles, or use capability-based permissions
- **Folder Management** - Create, rename, move, and delete folders and subfolders
- **Drag & Drop Upload** - Intuitive file upload with progress feedback
- **Image Thumbnails** - Automatic preview thumbnails for image files
- **PDF Preview** - Inline PDF preview in supported browsers
- **ZIP Export** - Export folders or entire document tree as ZIP archive
- **Activity Logging** - Track uploads, downloads, moves, and deletions
- **Orphan Cleanup** - Detect and remove database records whose files are missing from private storage
- **Multilingual** - English (default) with optional Italian translation
- **Disk Space Indicator** - Visual storage usage in sidebar
- **Responsive Mobile UI** - Optimized sidebar navigation with off-canvas drawer pattern

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Write permissions for storage directory

## Compatibility

- Tested up to WordPress `6.9`
- Built on core WordPress APIs including REST routes, `admin-post` handlers, capabilities, settings, and multisite-aware table prefixes
- Designed for the classic WordPress admin experience on current WordPress releases

## Installation

### From WordPress.org (Recommended)

The easiest way to install:

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "Mikesoft TeamVault"
3. Click **Install Now**
4. Activate the plugin
5. Configure in **TeamVault > Settings**

### Manual Installation

1. Download the plugin archive from the [WordPress.org Plugin Directory](https://wordpress.org/plugins/mikesoft-teamvault/)
2. Upload to `/wp-content/plugins/mikesoft-teamvault/`
3. Activate in WordPress Plugins menu
4. Configure in **TeamVault > Settings**

### From GitHub

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/TheStreamCode/mikesoft-teamvault.git mikesoft-teamvault
```

For local plugin development outside a live WordPress install, a separate source checkout name is recommended:

```bash
git clone https://github.com/TheStreamCode/mikesoft-teamvault.git mikesoft-teamvault-src
```

## Repository & Support

This repository serves as a public code portfolio and reference for the Mikesoft TeamVault plugin.

- **Installation & Updates:** The official distribution channel is the [WordPress.org Plugin Directory](https://wordpress.org/plugins/mikesoft-teamvault/). Please install the plugin from there to ensure you receive automatic updates.
- **Support:** For end-user support, bug reports, and feature requests, please use the [official WordPress.org support forum](https://wordpress.org/support/plugin/mikesoft-teamvault/).
- **Contributions:** This plugin is maintained internally by Mikesoft. While the source code is public for transparency, we do not accept external pull requests or issues on GitHub.

## Release Workflow

- Stable releases are built from `mikesoft-teamvault-src/` and published to WordPress.org via SVN.
- WordPress.org directory assets live in `.wordpress-org/assets/` and are deployed to the repository-level `/assets/` folder, not inside the plugin package.
- Development-only files such as `.git/`, `.worktrees/`, `tests/`, `composer.lock`, and GitHub-only documentation are intentionally excluded from SVN releases.

## Configuration

### Storage Directory

Default: `wp-content/uploads/private-documents/`

The directory is created automatically when the plugin initializes and is protected via `.htaccess` and `web.config`.

If you move a site between environments, copy this private storage folder too; otherwise the database records will remain visible but the original binaries will be missing. The settings page includes a maintenance cleanup action for orphaned records.

### User Access Control

**Option 1: Role-based (default)**

- Administrators and Editors have access via `manage_private_documents` capability
- Add capability to other roles programmatically:
```php
$role = get_role('author');
$role->add_cap('manage_private_documents');
```

**Option 2: User whitelist (recommended)**

1. Go to **TeamVault > Settings > User Access**
2. Enable "Limit access to specific users"
3. Search and add users to the whitelist
4. Users will automatically receive the `manage_private_documents` capability
5. Only whitelisted users with the plugin capability will see the menu and have access

> **Note:** When the whitelist is enabled, it becomes an extra authorization gate on top of the plugin capability. Keep your current account in the whitelist before saving to avoid locking yourself out.

### Allowed File Types

Configure extensions in Settings. Default: PDF, Office documents, images, archives, media files.

## Documentation

- `readme.txt` contains the WordPress.org-facing description and release notes
- `changelog.txt` contains the extended release history
- `CONTRIBUTING.md` documents contribution and development guidelines

## Developer Hooks

### Actions

```php
// After file upload
do_action('mstv_file_uploaded', $file_id, $file_data);

// After file deletion
do_action('mstv_file_deleted', $file_id, $file_data);

// After folder creation
do_action('mstv_folder_created', $folder_id, $folder_data);

// After file preview/download/export
do_action('mstv_file_previewed', $file_id, $file_data);
do_action('mstv_file_downloaded', $file_id, $file_data);
do_action('mstv_export_completed', $folder_id, $zip_path, $file_count);
```

### Filters

```php
// Modify allowed extensions
add_filter('mstv_allowed_extensions', function($extensions) {
    $extensions[] = 'psd';
    return $extensions;
});

// Modify max file size
add_filter('mstv_max_file_size', function($bytes) {
    return 100 * 1024 * 1024; // 100MB
});

// Modify storage path
add_filter('mstv_storage_path', function($path) {
    return '/custom/path/to/documents';
});

// Final upload validation hook
add_filter('mstv_upload_validation', function($result, $file) {
    return $result;
}, 10, 2);
```

## Screenshots

Screenshots for the public directory listing are maintained through the WordPress.org `assets/` repository. See the plugin page for the official screenshot gallery.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Changelog

See [changelog.txt](changelog.txt) for the complete release history and [readme.txt](readme.txt) for the WordPress.org release summary.

### Recent Changes

**v1.1.29**
- Enhancement: added TeamVault logo SVG to sidebar header (desktop and mobile off-canvas menu)
- Enhancement: enlarged logo display size for better visibility

**v1.1.28**
- Security: replaced !empty() with wp_validate_boolean() for all boolean form inputs in settings handling
- Security: replaced (bool) cast with wp_validate_boolean() in REST API settings updates
- Security: added dedicated nonce verification for export selection with explicit check
- Compliance: added wp_unslash() to all $_POST handling and PHPCS ignore comments for wp_validate_boolean
- Compliance: added PHPCS ignore comments for orderClause in repository files (whitelist-sanitized values)
- Enhancement: added TeamVault logo SVG to sidebar header in file manager
- Refactor: extracted create_protection_files() to MSTV_Helpers to eliminate code duplication
- Refactor: simplified repository files queries with build_order_clause() method
- Refactor: removed side-effect from MSTV_Storage constructor, explicit directory creation
- Refactor: injected MSTV_Settings into MSTV_Logger and MSTV_Assets via constructor
- Refactor: moved data access logic from logs-page view to admin controller
- Compliance: eliminated redundant MSTV_Settings instantiations in view templates

**v1.1.27**
- Security: added proper sanitization for uploaded file arrays (sanitize_file_name, sanitize_mime_type, sanitize_text_field)
- Security: replaced FILTER_DEFAULT with proper array sanitization
- Security: added detailed PHPCS documentation for nonce verification patterns
- Compliance: prefixed all global variables in templates with "mstv_"
- Compliance: prefixed all hook names with "mstv_" via class constants
- Compliance: changed all prefixes from "pdm" to "mstv" per WordPress.org guidelines
- Fix: corrected JavaScript config variable from "pdmConfig" to "mstvConfig"
- Fix: resolved syntax error in settings class (ternary operator compatibility)

**v1.1.26**
- Kept the mobile header toolbar on a single row by compacting filters and action controls
- Reduced the mobile footprint of the Upload and Export actions for a cleaner responsive header

See `changelog.txt` for the complete release history.

## Author

**Michael Gasperini** - [mikesoft.it](https://mikesoft.it)

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.
