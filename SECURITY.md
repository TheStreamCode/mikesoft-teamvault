# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.1.28  | :white_check_mark: |
| < 1.1.28 | :x:              |

## Reporting Security Issues

Since this plugin is distributed through the [WordPress.org Plugin Directory](https://wordpress.org/plugins/mikesoft-teamvault/), security issues should be reported through the official channels:

**WordPress.org Support Forum**: https://wordpress.org/support/plugin/mikesoft-teamvault/

For urgent security concerns or private disclosures, contact the maintainer directly through the [Mikesoft website](https://mikesoft.it).

## Security Practices

This plugin follows WordPress security best practices:

- **Capability-based access control** - All operations require the `manage_private_documents` capability
- **Nonce verification** - All form submissions and AJAX requests verify WordPress nonces
- **Input sanitization** - All user input is sanitized before processing
- **Output escaping** - All output is escaped appropriately
- **Prepared SQL queries** - Database operations use `$wpdb->prepare()` to prevent SQL injection
- **Protected storage** - Files are stored outside the web root and served through authenticated handlers only

## Scope

This security policy applies to the Mikesoft TeamVault plugin code only. The private storage directory and any custom storage paths configured by administrators are outside the scope of this policy and should be protected at the server level.
