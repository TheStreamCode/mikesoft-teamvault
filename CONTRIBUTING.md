# Contributing to Mikesoft TeamVault

Thank you for your interest in contributing!

## Contribution Policy

This repository is a read-only public mirror used for portfolio and transparency purposes. The plugin is maintained internally by Mikesoft.

- **Pull Requests:** We do not accept external pull requests. Any PR submitted will be automatically closed.
- **Issues:** GitHub Issues are disabled or unmonitored. 
- **Support:** For bug reports or support, please use the [official WordPress.org support forum](https://wordpress.org/support/plugin/mikesoft-teamvault/).

If you are setting up the project locally for review or learning purposes, please follow the internal development setup below.

## Internal Development Setup

This section is mainly for the project maintainer or trusted collaborators working directly on the codebase.

If you need to reproduce a bug locally, collect at least:

- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected vs actual behavior
- Screenshots (if applicable)
- Error logs (from `wp-content/debug.log` if available)

## Development Setup

### Prerequisites

- Local WordPress environment (Local, XAMPP, Docker, etc.)
- PHP 8.0+
- Git

### Steps

1. Fork and clone the repository
```bash
git clone https://github.com/TheStreamCode/mikesoft-teamvault.git mikesoft-teamvault-src
cd mikesoft-teamvault-src
```

2. Link to your WordPress plugins directory
```bash
# Linux/macOS
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/mikesoft-teamvault

# Windows (PowerShell, run as Admin)
New-Item -ItemType Junction -Path "C:\path\to\wordpress\wp-content\plugins\mikesoft-teamvault" -Target "$(pwd)"
```

Recommended local naming:

- source checkout: `mikesoft-teamvault-src`
- runtime/install slug: `mikesoft-teamvault`
- generated release artifact folder: `mikesoft-teamvault`

Keep the source checkout and the generated release folder separate. Install or symlink the plugin into WordPress using the runtime slug `mikesoft-teamvault`.

3. Activate in WordPress admin (Plugins menu)

4. Start developing!

## Coding Standards

### PHP

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use strict typing where possible
- Document public methods with PHPDoc

```php
/**
 * Store an uploaded file in the private storage.
 *
 * @param array  $file       The uploaded file array from $_FILES.
 * @param int|null $folderId Optional target folder ID.
 * @return array Result with 'success' bool and 'error' string or file data.
 */
public function store_uploaded_file(
    array $uploadedFile,
    ?int $folderId
): array {
    // Implementation
}
```

### JavaScript

- Use ES6+ syntax
- Keep functions small and focused
- Comment complex logic

### CSS

- Use CSS custom properties (variables)
- Follow BEM-like naming: `.pdm-component-element--modifier`

## Translations

The plugin currently uses a lightweight runtime translation layer. To add a new language:

### 1. Add PHP strings (`includes/class-mstv-i18n.php`)

```php
private const ITALIAN_MAP = [
    // Add new translations here.
    'English source string' => 'Italian translation',
    // ...existing strings
];
```

### 2. Add JS strings (`includes/class-mstv-assets.php`)

```php
'i18n' => [
    // Add your strings here.
    'yourKey' => __('English source string', 'mikesoft-teamvault'),
    // ...existing strings
],
```

### 3. Update both files in the same change when adding translations

## Internal Change Process

1. Create a feature branch
```bash
git checkout -b feature/your-feature-name
```

2. Make your changes

3. Test thoroughly in your local environment

4. Update documentation if needed (README.md, inline comments)

5. Commit with clear messages
```bash
git commit -m "Add: description of your feature"
git commit -m "Fix: description of the bug fix"
git commit -m "Refactor: description of the refactoring"
```

6. Push your branch to the canonical repository

### WordPress.org Release Deploy

For WordPress.org releases, use the workspace script `deployment/deploy-to-wordpress.ps1` instead of copying files manually.

- Source of truth: `mikesoft-teamvault-src/`
- WordPress.org assets: `.wordpress-org/assets/`
- SVN staging checkout: generated locally by the deploy script

Example:

```powershell
.\deployment\deploy-to-wordpress.ps1 -Version 1.1.30 -Username thestreamcode -SvnPassword "YOUR_SVN_PASSWORD"
```

The deploy script builds a clean release payload, excludes development-only files, syncs `trunk/` plus `tags/<version>/`, and uploads WordPress.org assets separately.

### Commit Guidelines

- One feature or fix per commit/branch when practical
- Clear description of changes
- Keep commits atomic and well-described

## Project Structure

```
mikesoft-teamvault/
├── mikesoft-teamvault.php       # Main plugin file
├── uninstall.php                 # Cleanup on uninstall
├── readme.txt                   # WordPress.org readme
├── README.md                    # GitHub readme
├── CONTRIBUTING.md             # This file
├── LICENSE                      # GPL v2+
├── changelog.txt                # Extended release history
├── .wordpress-org/              # WordPress.org-specific assets
│   └── assets/
│       ├── banner-772x250.png
│       ├── icon-128x128.png
│       ├── icon-256x256.png
│       └── icon.svg
├── includes/                    # PHP classes
│   ├── class-mstv-bootstrap.php     # Service container
│   ├── class-mstv-activator.php    # Activation hooks
│   ├── class-mstv-settings.php     # Settings management
│   ├── class-mstv-admin.php        # Admin handlers
│   ├── class-mstv-auth.php         # Authentication
│   ├── class-mstv-storage.php      # File storage
│   ├── class-mstv-filesystem.php   # File operations
│   ├── class-mstv-rest-controller.php
│   ├── class-mstv-i18n.php        # Translations
│   └── ...
├── languages/                   # Translation loading path
│   └── index.php
├── admin/views/                # Admin templates
│   ├── file-manager-page.php
│   ├── settings-page.php
│   └── logs-page.php
└── assets/                     # Frontend assets
    ├── css/admin.css
    ├── js/admin-app.js
    └── logo-teamvault.svg
```

## Developer Hooks Reference

### Action Hooks

```php
// After file upload
do_action('mstv_file_uploaded', $file_id, $file_data);

// After file deletion
do_action('mstv_file_deleted', $file_id, $file_data);

// After folder creation
do_action('mstv_folder_created', $folder_id, $folder_data);

// After file preview
do_action('mstv_file_previewed', $file_id, $file_data);

// After file download
do_action('mstv_file_downloaded', $file_id, $file_data);

// After ZIP export completion
do_action('mstv_export_completed', $folder_id, $zip_path, $file_count);
```

### Filter Hooks

```php
// Modify allowed file extensions
add_filter('mstv_allowed_extensions', function($extensions) {
    $extensions[] = 'psd';
    return $extensions;
});

// Modify maximum file size
add_filter('mstv_max_file_size', function($bytes) {
    return 100 * 1024 * 1024; // 100MB
});

// Modify storage path
add_filter('mstv_storage_path', function($path) {
    return '/custom/path/to/documents';
});

// Final upload validation
add_filter('mstv_upload_validation', function($result, $file) {
    return $result;
}, 10, 2);
```

## Contact

- **WordPress.org**: [plugin support forum](https://wordpress.org/plugins/mikesoft-teamvault/) for public support after publication
- **Website**: https://mikesoft.it
- **Email**: info@mikesoft.it

Thank you for contributing!
