# Mikesoft TeamVault

[![Plugin Version](https://img.shields.io/badge/version-1.1.24-blue.svg)](https://github.com/mikesoft-codex/mikesoft-teamvault/releases)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)

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

Recommended local layout:

- source checkout: `mikesoft-teamvault-src/`
- generated release folder: `mikesoft-teamvault/`
- final package: `mikesoft-teamvault.zip`

The supported plugin release slug and installed folder name are always `mikesoft-teamvault`.

### Manual Installation

1. Download the [latest release](https://github.com/mikesoft-codex/mikesoft-teamvault/releases)
2. Upload to `/wp-content/plugins/mikesoft-teamvault/`
3. Activate in WordPress Plugins menu
4. Configure in **TeamVault > Settings**

### From GitHub

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/mikesoft-codex/mikesoft-teamvault.git mikesoft-teamvault
```

For local plugin development outside a live WordPress install, a separate source checkout name is recommended:

```bash
git clone https://github.com/mikesoft-codex/mikesoft-teamvault.git mikesoft-teamvault-src
```

## Distribution

- GitHub repository and source of truth: `https://github.com/mikesoft-codex/mikesoft-teamvault`
- WordPress.org can be used as the public distribution channel for stable releases and auto-updates
- Recommended workflow: develop on GitHub, release stable versions to WordPress.org SVN
- Release packages should always install as `mikesoft-teamvault/` with `mikesoft-teamvault.php` as the main plugin file
- Keep source and release folders separate locally: `mikesoft-teamvault-src/` for development, `mikesoft-teamvault/` only as a generated packaging artifact

## Support

- GitHub Issues: bug reports, development discussion, roadmap work
- WordPress.org support forum: installation help and end-user support after directory publication

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
do_action('pdm_file_uploaded', $file_id, $file_data);

// After file deletion
do_action('pdm_file_deleted', $file_id, $file_data);

// After folder creation
do_action('pdm_folder_created', $folder_id, $folder_data);

// After file preview/download/export
do_action('pdm_file_previewed', $file_id, $file_data);
do_action('pdm_file_downloaded', $file_id, $file_data);
do_action('pdm_export_completed', $folder_id, $zip_path, $file_count);
```

### Filters

```php
// Modify allowed extensions
add_filter('pdm_allowed_extensions', function($extensions) {
    $extensions[] = 'psd';
    return $extensions;
});

// Modify max file size
add_filter('pdm_max_file_size', function($bytes) {
    return 100 * 1024 * 1024; // 100MB
});

// Modify storage path
add_filter('pdm_storage_path', function($path) {
    return '/custom/path/to/documents';
});

// Final upload validation hook
add_filter('pdm_upload_validation', function($result, $file) {
    return $result;
}, 10, 2);
```

## Screenshots

Screenshots for the public directory listing should be maintained through the WordPress.org `assets/` repository. GitHub documentation can be updated with in-repository screenshots once stable image assets are added to the project.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Changelog

See [changelog.txt](changelog.txt) for the complete release history and [readme.txt](readme.txt) for the WordPress.org release summary.

### Recent Changes

**v1.1.24**
- Renamed plugin from "Private Document Manager" to "Mikesoft TeamVault" for WordPress.org compliance
- Updated textdomain to "mikesoft-teamvault"
- Sorted folder tree alphabetically
- Added scrollable sidebar with fixed header/footer
- Implemented collapsible tree for deep folder hierarchies
- Reverted mobile sidebar to off-canvas drawer pattern

**v1.1.23**
- Fixed critical CSS typos (invalid background color, font-family misspellings)
- Added mobile backdrop overlay for sidebar/details panels with click-to-close
- Implemented ESC key handler for closing mobile panels
- Added body scroll lock when sidebar/details panels are open on mobile
- Increased touch targets to minimum 44x44px for better mobile interaction
- Improved modal responsiveness with adaptive sizing for small screens
- Added focus-visible states for better keyboard navigation and accessibility
- Added prefers-reduced-motion support for users who prefer reduced animations
- Added prefers-contrast support for high contrast mode
- Added safe area insets support for notched devices
- Fixed file rename sanitization issue where names with dots could become empty

See `changelog.txt` for the complete release history.

## Author

**Michael Gasperini** - [mikesoft.it](https://mikesoft.it)

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.
