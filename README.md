# Private Document Manager

[![Plugin Version](https://img.shields.io/badge/version-1.1.8-blue.svg)](https://github.com/mikesoft-codex/wp-private-document-manager/releases)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.9-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)

**Secure private document management for WordPress**, fully separated from the Media Library.

## Features

- **Private Storage** - Files stored in protected directory, not accessible via public URL
- **User Access Control** - Limit access to specific users or use role-based permissions
- **Folder Management** - Create, rename, delete folders and subfolders
- **Drag & Drop Upload** - Intuitive file upload with progress feedback
- **Image Thumbnails** - Automatic preview thumbnails for image files
- **PDF Preview** - Inline PDF preview in supported browsers
- **ZIP Export** - Export folders or entire document tree as ZIP archive
- **Activity Logging** - Track uploads, downloads, moves, and deletions
- **Multilingual** - English (default) with optional Italian translation
- **Disk Space Indicator** - Visual storage usage in sidebar

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Write permissions for storage directory

## Compatibility

- Tested up to WordPress `6.9`
- Built on core WordPress APIs including REST routes, `admin-post` handlers, capabilities, settings, and multisite-aware table prefixes
- Designed for the classic WordPress admin experience on current WordPress releases

## Installation

### Manual Installation

1. Download the [latest release](https://github.com/mikesoft-codex/wp-private-document-manager/releases)
2. Upload to `/wp-content/plugins/private-document-manager/`
3. Activate in WordPress Plugins menu
4. Configure in **Private Documents > Settings**

### From GitHub

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/mikesoft-codex/wp-private-document-manager.git private-document-manager
```

## Distribution

- GitHub repository and source of truth: `https://github.com/mikesoft-codex/wp-private-document-manager`
- WordPress.org can be used as the public distribution channel for stable releases and auto-updates
- Recommended workflow: develop on GitHub, release stable versions to WordPress.org SVN

## Support

- GitHub Issues: bug reports, development discussion, roadmap work
- WordPress.org support forum: installation help and end-user support after directory publication

## Configuration

### Storage Directory

Default: `wp-content/uploads/private-documents/`

Custom path configurable in Settings. Directory is protected via `.htaccess` and `web.config`.

### User Access Control

**Option 1: Role-based (default)**

- Administrators and Editors have access via `manage_private_documents` capability
- Add capability to other roles programmatically:
```php
$role = get_role('author');
$role->add_cap('manage_private_documents');
```

**Option 2: User whitelist (recommended)**

1. Go to **Private Documents > Settings > User Access**
2. Enable "Limit access to specific users"
3. Search and add users to the whitelist
4. Users will automatically receive the `manage_private_documents` capability
5. Only whitelisted users will see the menu and have access

> **Note:** The whitelist uses WordPress native capability system. Users receive the capability when added to the whitelist and lose it when removed or when the whitelist is disabled.

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

See [readme.txt](readme.txt) for full changelog.

### Recent Changes

**v1.1.8**
- Standardized the main plugin presentation around English-first source text
- Polished public documentation and repaired naming inconsistencies introduced during the language cleanup

**v1.1.7**
- Improved release hardening for streaming handlers and admin settings sanitization

**v1.1.6**
- Fixed Windows path normalization for uploads and storage validation

**v1.1.5**
- Improved internal drag and drop for moving files between folders

See `changelog.txt` for the complete release history.

## Author

**Michael Gasperini** - [mikesoft.it](https://mikesoft.it)

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.
