# Mikesoft TeamVault

[![Plugin Version](https://img.shields.io/badge/version-1.1.29-blue.svg)](https://github.com/TheStreamCode/mikesoft-teamvault/releases)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)

**Secure shared document management for WordPress**, fully separated from the Media Library. Perfect for teams, partners, and clients who need a private space to collaborate on documents within your own hosting environment.

> **Distribution:** The primary distribution channel is [WordPress.org Plugin Directory](https://wordpress.org/plugins/mikesoft-teamvault/). GitHub serves as a public code reference and release visibility. Stable versions are released to WordPress.org SVN.

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

Recommended local layout:

- source checkout: `mikesoft-teamvault-src/`
- generated release folder: `mikesoft-teamvault/`
- final package: `mikesoft-teamvault.zip`

The supported plugin release slug and installed folder name are always `mikesoft-teamvault`.

### Manual Installation

1. Download the [latest release](https://github.com/TheStreamCode/mikesoft-teamvault/releases)
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

## Distribution

- **WordPress.org Plugin Directory**: https://wordpress.org/plugins/mikesoft-teamvault/ — primary distribution channel for stable releases and auto-updates
- **GitHub repository**: https://github.com/TheStreamCode/mikesoft-teamvault — public code reference and release visibility
- Recommended workflow: develop on GitHub, release stable versions to WordPress.org SVN
- Release packages should always install as `mikesoft-teamvault/` with `mikesoft-teamvault.php` as the main plugin file
- Keep source and release folders separate locally: `mikesoft-teamvault-src/` for development, `mikesoft-teamvault/` only as a generated packaging artifact

## Repository Policy

- This repository is public for transparency, code visibility, and release distribution
- The plugin is maintained internally by Mikesoft; external contributions are not part of the current development workflow
- Pull requests may be closed without review if they are outside the planned roadmap or maintenance priorities
- GitHub is not the primary support channel for end users

## Support

- **WordPress.org support forum**: installation help and end-user support through the plugin directory
- **Mikesoft website**: official product and business communication channel
- **GitHub repository**: public code reference and release visibility, not a collaborative support queue

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
