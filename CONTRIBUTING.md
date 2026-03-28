# Contributing to Mikesoft TeamVault

Thank you for your interest in contributing!

## Bug Reports

Please use [GitHub Issues](https://github.com/mikesoft-codex/mikesoft-teamvault/issues) and include:

- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected vs actual behavior
- Screenshots (if applicable)
- Error logs (from `wp-content/debug.log` if available)

## Feature Requests

Open a [GitHub Discussion](https://github.com/mikesoft-codex/mikesoft-teamvault/discussions) with the `ideas` label.

## Development Setup

### Prerequisites

- Local WordPress environment (Local, XAMPP, Docker, etc.)
- PHP 8.0+
- Git

### Steps

1. Fork and clone the repository
```bash
git clone https://github.com/YOUR_USERNAME/mikesoft-teamvault.git mikesoft-teamvault-src
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
 * @param array       $file       The uploaded file array from $_FILES.
 * @param int|null    $folderId  Optional target folder ID.
 * @param PDM_Repository_Folders $folderRepo Folder repository instance.
 * @return array Result with 'success' bool and 'error' string or file data.
 */
public function store_uploaded_file(
    array $uploadedFile,
    ?int $folderId,
    PDM_Repository_Folders $folderRepo
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

### 1. Add PHP strings (`includes/class-pdm-i18n.php`)

```php
private const ITALIAN_MAP = [
    // Add new translations here.
    'English source string' => 'Italian translation',
    // ...existing strings
];
```

### 2. Add JS strings (`includes/class-pdm-assets.php`)

```php
'i18n' => [
    // Add your strings here.
    'yourKey' => __('English source string', 'mikesoft-teamvault'),
    // ...existing strings
],
```

### 3. Submit a Pull Request with both files updated

## Pull Request Process

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

6. Push and open a Pull Request

### PR Guidelines

- One feature or fix per PR
- Clear description of changes
- Reference related issues: `Fixes #123` or `Closes #456`
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
├── includes/                    # PHP classes
│   ├── class-pdm-bootstrap.php     # Service container
│   ├── class-pdm-activator.php    # Activation hooks
│   ├── class-pdm-settings.php     # Settings management
│   ├── class-pdm-auth.php         # Authentication
│   ├── class-pdm-storage.php      # File storage
│   ├── class-pdm-filesystem.php   # File operations
│   ├── class-pdm-rest-controller.php
│   ├── class-pdm-i18n.php        # Translations
│   └── ...
├── languages/                   # Translation loading path
│   └── index.php
├── admin/views/                # Admin templates
│   ├── file-manager-page.php
│   ├── settings-page.php
│   └── logs-page.php
└── assets/                     # Frontend assets
    ├── css/admin.css
    └── js/admin-app.js
```

## Contact

- **Issues**: [GitHub Issues](https://github.com/mikesoft-codex/mikesoft-teamvault/issues)
- **Discussions**: [GitHub Discussions](https://github.com/mikesoft-codex/mikesoft-teamvault/discussions)
- **Email**: info@mikesoft.it

Thank you for contributing!
