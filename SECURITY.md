# Security Policy

## Supported Versions

Security fixes are provided for the latest public release published on WordPress.org.

If you are running an older version, update to the current release before requesting support or reporting a vulnerability.

## Reporting a Vulnerability

Do not disclose security issues in public support forums or public issue trackers.

Report vulnerabilities privately to:

- `info@mikesoft.it`

Include the following details when possible:

- affected plugin version
- WordPress version
- PHP version
- clear reproduction steps
- proof of concept or request details
- impact assessment

## Response Expectations

- Reports are reviewed privately.
- Confirmed issues are prioritized for a maintenance release.
- Public disclosure should wait until a fix is available to users.

## Security Scope

This policy applies to the Mikesoft TeamVault plugin code distributed through WordPress.org.

Server configuration, third-party plugins, themes, and custom hosting environments remain outside the plugin's direct control and should be reviewed separately.

## Operational Security Notes

- The `manage_private_documents` capability grants full TeamVault workspace access, including upload, download, export, rename, move, and delete actions.
- New activations grant `manage_private_documents` to Administrators only. Sites upgraded from older releases should review role capabilities if Editors previously had TeamVault access.
- Default storage uses `wp-content/uploads/private-documents/` and creates deny files for webservers that support them.
- Nginx does not read `.htaccess`; configure an equivalent deny rule or use a custom storage path outside the public webroot for sensitive deployments.
