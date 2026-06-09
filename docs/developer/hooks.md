# Developer Hooks

## Overview

Mikesoft TeamVault exposes WordPress actions and filters for integrations, policy customization, and operational observability.

Hook names are defined in `includes/class-mstv-hooks.php`.

## Actions

### `mstv_file_uploaded`

Runs after a file record is created.

Parameters:

- `int $fileId`
- `array $fileData`

Payload keys currently include:

- `display_name`
- `folder_id`
- `extension`
- `mime_type`
- `file_size`

```php
add_action('mstv_file_uploaded', function (int $fileId, array $fileData): void {
    error_log('Uploaded file ID: ' . $fileId);
}, 10, 2);
```

### `mstv_file_deleted`

Runs after a file record and its stored file are deleted.

Parameters:

- `int $fileId`
- `array $fileData`

Payload keys currently include:

- `display_name`
- `folder_id`
- `relative_path`

### `mstv_file_renamed`

Runs after a file display name is updated.

Parameters:

- `int $fileId`
- `string $oldName`
- `string $newName`

### `mstv_file_moved`

Runs after a file is moved to another folder.

Parameters:

- `int $fileId`
- `?int $oldFolderId`
- `?int $newFolderId`

### `mstv_file_downloaded`

Runs before the binary download stream is sent.

Parameters:

- `int $fileId`
- `array $fileData`

Payload keys currently include:

- `display_name`
- `extension`
- `mime_type`

### `mstv_file_previewed`

Runs before the preview stream is sent.

Parameters:

- `int $fileId`
- `array $fileData`

Payload keys currently include:

- `display_name`
- `extension`
- `mime_type`

### `mstv_folder_created`

Runs after a folder record is created.

Parameters:

- `int $folderId`
- `array $folderData`

Payload keys currently include:

- `name`
- `parent_id`
- `relative_path`

### `mstv_folder_deleted`

Runs after a folder is deleted.

Parameters:

- `int $folderId`
- `string $folderName`

### `mstv_folder_renamed`

Runs after a folder rename completes.

Parameters:

- `int $folderId`
- `string $oldName`
- `string $newName`

### `mstv_folder_moved`

Runs after a folder is moved under a different parent folder.

Parameters:

- `int $folderId`
- `?int $oldParentId`
- `?int $newParentId`

`$oldParentId` and `$newParentId` are `null` for folders at the storage root.

### `mstv_export_started`

Runs when a ZIP export starts.

Parameters:

- `?int $folderId`
- `string $zipPath`

`$folderId` is `null` for selected-folder exports that are built as a combined archive.

### `mstv_export_completed`

Runs after a ZIP export archive is created and before it is streamed.

Parameters:

- `?int $folderId`
- `string $zipPath`
- `int $filesCount`

### `mstv_access_denied`

Runs when a request is rejected by the per-folder permission engine (since 2.6).

Parameters:

- `int $userId`
- `?int $folderId` (`null` for the storage root)
- `string $action` (one of `view`, `upload`, `download`, `delete`, `manage`)

```php
add_action('mstv_access_denied', function (int $userId, ?int $folderId, string $action): void {
    error_log(sprintf('TeamVault denied %s on folder %s for user %d', $action, $folderId ?? 'root', $userId));
}, 10, 3);
```

## Filters

### `mstv_allowed_extensions`

Filters the resolved allowed upload extensions.

Parameters:

- `array $extensions`

```php
add_filter('mstv_allowed_extensions', function (array $extensions): array {
    $extensions[] = 'md';
    return array_values(array_unique($extensions));
});
```

### `mstv_max_file_size`

Filters the maximum file size in bytes.

Parameters:

- `int $bytes`

### `mstv_storage_path`

Filters the resolved storage path after plugin settings are applied.

Parameters:

- `string $path`

```php
add_filter('mstv_storage_path', function (string $path): string {
    return WP_CONTENT_DIR . '/teamvault-private';
});
```

### `mstv_file_name`

Filters the display name used during upload before final normalization.

Parameters:

- `string $name`
- `string $originalName`

### `mstv_upload_validation`

Filters the final upload validation result.

Parameters:

- `array $result`
- `array $files`

The `$result` array currently contains:

- `valid` (`bool`)
- `errors` (`array`)
- `extension` (`string`, when available)
- `mime_type` (`string`, when available)
- `size` (`int`, when available)

```php
add_filter('mstv_upload_validation', function (array $result, array $files): array {
    if (($result['valid'] ?? false) && str_ends_with(strtolower((string) ($files['name'] ?? '')), '.csv')) {
        $result['errors'][] = 'CSV uploads are disabled by site policy.';
        $result['valid'] = false;
    }

    return $result;
}, 10, 2);
```

## Notes

- These hooks are intended for WordPress customizations and must be used from site code, custom plugins, or mu-plugins.
- Hook payloads reflect the current implementation and may expand in future releases.
