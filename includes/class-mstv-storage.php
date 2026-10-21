<?php

defined('ABSPATH') || exit;

class MSTV_Storage
{
    private const STORAGE_USAGE_CACHE_TTL = 300;

    private MSTV_Settings $settings;
    private MSTV_Filesystem $filesystem;

    /** @var callable|null Optional writer used to create the storage protection files. */
    private $protectionWriter;

    public function __construct(MSTV_Settings $settings, ?callable $protectionWriter = null)
    {
        $this->settings = $settings;
        $this->filesystem = new MSTV_Filesystem($settings->get_storage_path());
        $this->protectionWriter = $protectionWriter;
    }

    public function get_filesystem(): MSTV_Filesystem
    {
        return $this->filesystem;
    }

    public function get_base_path(): string
    {
        return $this->filesystem->get_base_path();
    }

    public function get_storage_stats(MSTV_Repository_Files $filesRepo): array
    {
        $pluginUsedBytes = $this->get_plugin_used_bytes($filesRepo);

        return [
            'plugin_used_bytes' => $pluginUsedBytes,
            'plugin_used_formatted' => MSTV_Helpers::format_filesize($pluginUsedBytes),
        ];
    }

    private function get_plugin_used_bytes(MSTV_Repository_Files $filesRepo): int
    {
        $cachedValue = get_transient($this->get_storage_usage_cache_key());

        if (is_numeric($cachedValue)) {
            return max(0, (int) $cachedValue);
        }

        $total = 0;
        $processedPaths = [];

        foreach ($filesRepo->find_all('created_at', 'DESC') as $fileRecord) {
            $relativePath = isset($fileRecord->relative_path) ? (string) $fileRecord->relative_path : '';

            if ($relativePath === '' || isset($processedPaths[$relativePath]) || !$this->filesystem->is_file($relativePath)) {
                continue;
            }

            $processedPaths[$relativePath] = true;
            $total += $this->filesystem->get_file_size($relativePath);
        }

        set_transient($this->get_storage_usage_cache_key(), $total, self::STORAGE_USAGE_CACHE_TTL);

        return $total;
    }

    private function invalidate_storage_usage_cache(): void
    {
        delete_transient($this->get_storage_usage_cache_key());
    }

    private function get_storage_usage_cache_key(): string
    {
        return 'mstv_storage_usage_' . get_current_blog_id() . '_' . md5(wp_normalize_path($this->get_base_path()));
    }

    public function reindex_storage_records(
        MSTV_Repository_Folders $folderRepo,
        MSTV_Repository_Files $filesRepo,
        int $createdBy
    ): array {
        if (!$this->ensure_storage_directory()) {
            return [
                'success' => false,
                'error' => __('Unable to initialize the storage directory.', 'mikesoft-teamvault'),
            ];
        }

        $folderMap = [];
        foreach ($folderRepo->find_all() as $folder) {
            $folderMap[(string) $folder->relative_path] = (int) $folder->id;
        }

        $fileMap = [];
        foreach ($filesRepo->find_all() as $files) {
            $fileMap[(string) $files->relative_path] = true;
        }

        $stats = [
            'folders_created' => 0,
            'files_created' => 0,
            'files_skipped' => 0,
        ];

        $reindexed = $this->reindex_directory('', $folderRepo, $filesRepo, $folderMap, $fileMap, $createdBy, $stats);
        $this->invalidate_storage_usage_cache();

        if (!$reindexed) {
            return [
                'success' => false,
                'error' => __('Unable to save the changes. Please try again.', 'mikesoft-teamvault'),
                'folders_created' => $stats['folders_created'],
                'files_created' => $stats['files_created'],
                'files_skipped' => $stats['files_skipped'],
            ];
        }

        return [
            'success' => true,
            'folders_created' => $stats['folders_created'],
            'files_created' => $stats['files_created'],
            'files_skipped' => $stats['files_skipped'],
        ];
    }

    public function has_reindexable_content(): bool
    {
        foreach ($this->filesystem->list_directory('') as $item) {
            if (!$this->should_skip_reindex_item('', $item)) {
                return true;
            }
        }

        return false;
    }

    public function get_folder_path(?int $folderId, MSTV_Repository_Folders $folderRepo): string
    {
        if (null === $folderId) {
            return '';
        }

        $folder = $folderRepo->find($folderId);
        if (!$folder) {
            return '';
        }

        return $folder->relative_path;
    }

    public function store_uploaded_file(
        array $uploadedFile,
        ?int $folderId,
        MSTV_Repository_Folders $folderRepo
    ): array {
        if (!$this->ensure_storage_directory()) {
            return [
                'success' => false,
                'error' => __('Unable to initialize the storage directory.', 'mikesoft-teamvault'),
            ];
        }

        $protection = $this->get_storage_protection_status();

        if (!$protection['ok'] || !$protection['directory_writable']) {
            // Fail closed: without the web-server protection files the directory may
            // be reachable by exact URL, so refuse to add another private document.
            return [
                'success' => false,
                'error_code' => 'storage_unprotected',
                'error' => sprintf(
                    /* translators: %s: comma-separated list of protection files that could not be created. */
                    __('Upload refused: TeamVault could not create the protection files (%s) in the private storage directory. Make the directory writable, or use a storage path outside the public webroot, then retry.', 'mikesoft-teamvault'),
                    $protection['failed'] === []
                        ? __('directory not writable', 'mikesoft-teamvault')
                        : implode(', ', $protection['failed'])
                ),
            ];
        }

        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $storedName = MSTV_Helpers::generate_secure_filename($extension);
        
        $folderPath = $this->get_folder_path($folderId, $folderRepo);
        $relativePath = $this->build_file_path($folderPath, $storedName);
        $fullPath = $this->filesystem->get_verified_path($relativePath, true);

        if ($fullPath === false) {
            return [
                'success' => false,
                'error' => __('Invalid destination path.', 'mikesoft-teamvault'),
            ];
        }

        $targetDir = dirname($fullPath);
        if (!is_dir($targetDir)) {
            wp_mkdir_p($targetDir);
        }

        if (!$this->copy_uploaded_file((string) $uploadedFile['tmp_name'], $fullPath)) {
            return [
                'success' => false,
                'error' => __('Unable to save the file.', 'mikesoft-teamvault'),
            ];
        }

        $checksum = $this->filesystem->get_file_checksum($relativePath);
        $fileSize = $this->filesystem->get_file_size($relativePath);
        $this->invalidate_storage_usage_cache();

        return [
            'success' => true,
            'stored_name' => $storedName,
            'relative_path' => $relativePath,
            'extension' => $extension,
            'file_size' => $fileSize,
            'checksum' => $checksum,
        ];
    }

    public function create_folder(string $name, ?int $parentId, MSTV_Repository_Folders $folderRepo): array
    {
        if (!$this->ensure_storage_directory()) {
            return [
                'success' => false,
                'error' => __('Unable to initialize the storage directory.', 'mikesoft-teamvault'),
            ];
        }

        $slug = MSTV_Helpers::sanitize_folder_name($name);
        
        if (empty($slug)) {
            return [
                'success' => false,
                'error' => __('Invalid folder name.', 'mikesoft-teamvault'),
            ];
        }

        $parentPath = '';
        if ($parentId) {
            $parent = $folderRepo->find($parentId);
            if (!$parent) {
                return [
                    'success' => false,
                    'error' => __('Parent folder not found.', 'mikesoft-teamvault'),
                ];
            }
            $parentPath = $parent->relative_path;
        }

        $relativePath = $this->build_folder_path($parentPath, $slug);

        if (!$this->filesystem->is_path_within_base($this->filesystem->resolve($relativePath))) {
            return [
                'success' => false,
                'error' => __('Invalid folder path.', 'mikesoft-teamvault'),
            ];
        }

        if ($this->filesystem->exists($relativePath)) {
            if ($this->filesystem->is_dir($relativePath)) {
                return [
                    'success' => true,
                    'slug' => $slug,
                    'relative_path' => $relativePath,
                ];
            }

            return [
                'success' => false,
                'error' => __('Folder already exists.', 'mikesoft-teamvault'),
            ];
        }

        $created = $this->filesystem->create_directory($relativePath);
        if (!$created) {
            return [
                'success' => false,
                'error' => __('Unable to create the folder.', 'mikesoft-teamvault'),
            ];
        }

        return [
            'success' => true,
            'slug' => $slug,
            'relative_path' => $relativePath,
        ];
    }

    public function delete_folder(int $folderId, MSTV_Repository_Folders $folderRepo, MSTV_Repository_Files $filesRepo): array
    {
        $folder = $folderRepo->find($folderId);
        if (!$folder) {
            return [
                'success' => false,
                'error' => __('Folder not found.', 'mikesoft-teamvault'),
            ];
        }

        $children = $folderRepo->find_by_parent($folderId);
        if (!empty($children)) {
            return [
                'success' => false,
                'error' => __('The folder contains subfolders. Delete the subfolders first.', 'mikesoft-teamvault'),
            ];
        }

        $files = $filesRepo->find_by_folder($folderId);
        if (!empty($files)) {
            return [
                'success' => false,
                'error' => __('The folder contains files. Delete the files first.', 'mikesoft-teamvault'),
            ];
        }

        $deleted = $this->filesystem->delete_directory($folder->relative_path);
        if (!$deleted) {
            return [
                'success' => false,
                'error' => __('Unable to delete the folder from the filesystem.', 'mikesoft-teamvault'),
            ];
        }

        return ['success' => true];
    }

    public function delete_file(int $fileId, MSTV_Repository_Files $filesRepo): array
    {
        $files = $filesRepo->find($fileId);
        if (!$files) {
            return [
                'success' => false,
                'error' => __('File not found.', 'mikesoft-teamvault'),
            ];
        }

        $trashDirectory = '.mstv-trash';
        if (!$this->filesystem->create_directory($trashDirectory)) {
            return [
                'success' => false,
                'error' => __('Unable to delete the files from the filesystem.', 'mikesoft-teamvault'),
            ];
        }

        $stagedRelativePath = $trashDirectory . '/' . bin2hex(random_bytes(16)) . '.trash';
        if (!$this->filesystem->move_file((string) $files->relative_path, $stagedRelativePath)) {
            return [
                'success' => false,
                'error' => __('Unable to delete the files from the filesystem.', 'mikesoft-teamvault'),
            ];
        }

        return [
            'success' => true,
            'original_relative_path' => (string) $files->relative_path,
            'staged_relative_path' => $stagedRelativePath,
        ];
    }

    public function restore_staged_file(string $stagedRelativePath, string $originalRelativePath): bool
    {
        return $this->filesystem->move_file($stagedRelativePath, $originalRelativePath);
    }

    public function finalize_staged_file_deletion(string $stagedRelativePath): bool
    {
        $deleted = $this->filesystem->delete_file($stagedRelativePath);

        if ($deleted) {
            $this->invalidate_storage_usage_cache();
        }

        return $deleted;
    }

    public function move_file(
        int $fileId,
        ?int $targetFolderId,
        MSTV_Repository_Files $filesRepo,
        MSTV_Repository_Folders $folderRepo
    ): array {
        $files = $filesRepo->find($fileId);
        if (!$files) {
            return [
                'success' => false,
                'error' => __('File not found.', 'mikesoft-teamvault'),
            ];
        }

        $targetPath = $this->get_folder_path($targetFolderId, $folderRepo);
        $newRelativePath = $this->build_file_path($targetPath, $files->stored_name);

        if ($files->folder_id === $targetFolderId) {
            return [
                'success' => false,
                'error' => __('The file is already in the destination folder.', 'mikesoft-teamvault'),
            ];
        }

        $moved = $this->filesystem->move_file($files->relative_path, $newRelativePath);
        if (!$moved) {
            return [
                'success' => false,
                'error' => __('Unable to move the file.', 'mikesoft-teamvault'),
            ];
        }

        return [
            'success' => true,
            'new_relative_path' => $newRelativePath,
        ];
    }

    public function rename_folder(
        int $folderId,
        string $newName,
        MSTV_Repository_Folders $folderRepo
    ): array {
        $folder = $folderRepo->find($folderId);
        if (!$folder) {
            return [
                'success' => false,
                'error' => __('Folder not found.', 'mikesoft-teamvault'),
            ];
        }

        $newSlug = MSTV_Helpers::sanitize_folder_name($newName);
        if (empty($newSlug)) {
            return [
                'success' => false,
                'error' => __('Invalid folder name.', 'mikesoft-teamvault'),
            ];
        }

        $parentPath = dirname($folder->relative_path);
        if ($parentPath === '.') {
            $parentPath = '';
        }

        $newRelativePath = $this->build_folder_path($parentPath, $newSlug);

        if ($folder->slug === $newSlug) {
            return [
                'success' => false,
                'error' => __('The new name is identical to the current name.', 'mikesoft-teamvault'),
            ];
        }

        if ($this->filesystem->exists($newRelativePath)) {
            return [
                'success' => false,
                'error' => __('A folder with this name already exists.', 'mikesoft-teamvault'),
            ];
        }

        $renamed = $this->filesystem->rename_directory($folder->relative_path, $newRelativePath);
        if (!$renamed) {
            return [
                'success' => false,
                'error' => __('Unable to rename the folder.', 'mikesoft-teamvault'),
            ];
        }

        return [
            'success' => true,
            'new_slug' => $newSlug,
            'new_relative_path' => $newRelativePath,
        ];
    }

    public function move_folder(
        int $folderId,
        ?int $targetParentId,
        MSTV_Repository_Folders $folderRepo
    ): array {
        $folder = $folderRepo->find($folderId);
        if (!$folder) {
            return [
                'success' => false,
                'error' => __('Folder not found.', 'mikesoft-teamvault'),
            ];
        }

        $currentParentId = $folder->parent_id !== null ? (int) $folder->parent_id : null;
        if ($currentParentId === $targetParentId) {
            return [
                'success' => false,
                'error' => __('The folder is already in the destination folder.', 'mikesoft-teamvault'),
            ];
        }

        $targetParentPath = '';
        if ($targetParentId !== null) {
            $targetParent = $folderRepo->find($targetParentId);
            if (!$targetParent) {
                return [
                    'success' => false,
                    'error' => __('Parent folder not found.', 'mikesoft-teamvault'),
                ];
            }

            if ($targetParentId === $folderId
                || $this->is_descendant_path((string) $targetParent->relative_path, (string) $folder->relative_path)) {
                return [
                    'success' => false,
                    'error' => __('You cannot move a folder into itself or one of its subfolders.', 'mikesoft-teamvault'),
                ];
            }

            $targetParentPath = (string) $targetParent->relative_path;
        }

        $newRelativePath = $this->build_folder_path($targetParentPath, (string) $folder->slug);

        if (!$this->filesystem->is_path_within_base($this->filesystem->resolve($newRelativePath))) {
            return [
                'success' => false,
                'error' => __('Invalid folder path.', 'mikesoft-teamvault'),
            ];
        }

        if ($this->filesystem->exists($newRelativePath)) {
            return [
                'success' => false,
                'error' => __('A folder with this name already exists.', 'mikesoft-teamvault'),
            ];
        }

        $moved = $this->filesystem->rename_directory((string) $folder->relative_path, $newRelativePath);
        if (!$moved) {
            return [
                'success' => false,
                'error' => __('Unable to move the folder.', 'mikesoft-teamvault'),
            ];
        }

        return [
            'success' => true,
            'new_relative_path' => $newRelativePath,
        ];
    }

    public function ensure_storage_directory(): bool
    {
        $basePath = $this->get_base_path();
        
        if (!is_dir($basePath) && !wp_mkdir_p($basePath)) {
            return false;
        }

        MSTV_Helpers::ensure_protection_files($basePath, $this->protectionWriter);

        return $this->filesystem->is_writable($basePath);
    }

    /**
     * Report whether the private storage directory is writable and protected.
     *
     * The protection files are created here when they are missing, so the result
     * reflects the live state instead of a cached assumption. Callers that write
     * private documents must refuse the operation when the result is not safe.
     *
     * @return array{
     *     directory_writable: bool,
     *     files: array<string,bool>,
     *     failed: string[],
     *     marker: bool,
     *     ok: bool,
     *     inside_uploads: bool,
     *     server_software: string
     * }
     */
    public function get_storage_protection_status(): array
    {
        $basePath = $this->get_base_path();
        $directoryWritable = $basePath !== '' && is_dir($basePath) && $this->filesystem->is_writable($basePath);
        $protection = MSTV_Helpers::ensure_protection_files($basePath, $this->protectionWriter);

        $serverSoftware = '';
        if (isset($_SERVER['SERVER_SOFTWARE']) && is_string($_SERVER['SERVER_SOFTWARE'])) {
            $serverSoftware = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']));
        }

        return [
            'directory_writable' => $directoryWritable,
            'files' => $protection['files'],
            'failed' => $protection['failed'],
            'marker' => $protection['marker'],
            'ok' => $protection['ok'],
            'inside_uploads' => $this->settings->is_storage_path_inside_uploads(),
            'server_software' => $serverSoftware,
        ];
    }

    private function reindex_directory(
        string $relativePath,
        MSTV_Repository_Folders $folderRepo,
        MSTV_Repository_Files $filesRepo,
        array &$folderMap,
        array &$fileMap,
        int $createdBy,
        array &$stats
    ): bool {
        foreach ($this->filesystem->list_directory($relativePath) as $item) {
            if ($this->should_skip_reindex_item($relativePath, $item)) {
                continue;
            }

            $itemRelativePath = $this->join_relative_paths($relativePath, $item);

            if ($this->filesystem->is_dir($itemRelativePath)) {
                $folderId = $folderMap[$itemRelativePath] ?? 0;

                if ($folderId <= 0) {
                    $parentPath = $this->get_parent_relative_path($itemRelativePath);
                    $folderId = $folderRepo->create([
                        'parent_id' => $parentPath === '' ? null : ($folderMap[$parentPath] ?? null),
                        'name' => $item,
                        'slug' => MSTV_Helpers::sanitize_folder_name($item),
                        'relative_path' => $itemRelativePath,
                        'created_by' => $createdBy,
                    ]);

                    if ($folderId <= 0) {
                        $existingFolder = $folderRepo->find_by_relative_path($itemRelativePath);

                        if ($existingFolder === null) {
                            return false;
                        }

                        // A concurrent reindex created the same folder first: reuse
                        // the surviving row instead of failing the whole scan.
                        $folderId = (int) $existingFolder->id;
                        $folderMap[$itemRelativePath] = $folderId;
                    } else {
                        $folderMap[$itemRelativePath] = $folderId;
                        $stats['folders_created']++;
                    }
                }

                if (!$this->reindex_directory($itemRelativePath, $folderRepo, $filesRepo, $folderMap, $fileMap, $createdBy, $stats)) {
                    return false;
                }
                continue;
            }

            if (isset($fileMap[$itemRelativePath])) {
                continue;
            }

            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $displayName = MSTV_Helpers::resolve_file_display_name('', (string) $item);
            $parentPath = $this->get_parent_relative_path($itemRelativePath);
            $mimeType = $this->filesystem->get_mime_type($itemRelativePath);

            if (!$this->is_reindexable_file($itemRelativePath, $extension, $mimeType)) {
                $stats['files_skipped']++;
                continue;
            }

            $fileId = $filesRepo->create([
                'folder_id' => $parentPath === '' ? null : ($folderMap[$parentPath] ?? null),
                'original_name' => $item,
                'stored_name' => $item,
                'display_name' => $displayName,
                'relative_path' => $itemRelativePath,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'file_size' => $this->filesystem->get_file_size($itemRelativePath),
                'checksum' => $this->filesystem->get_file_checksum($itemRelativePath),
                'created_by' => $createdBy,
            ]);

            if ($fileId <= 0) {
                if ($filesRepo->find_by_relative_path($itemRelativePath) === null) {
                    return false;
                }

                // A concurrent reindex created the same file path first: keep the
                // surviving row and continue the scan.
                $fileMap[$itemRelativePath] = true;
                $stats['files_skipped']++;
                continue;
            }

            $fileMap[$itemRelativePath] = true;
            $stats['files_created']++;
        }

        return true;
    }

    private function should_skip_reindex_item(string $relativePath, string $item): bool
    {
        if ($relativePath !== '') {
            return false;
        }

        return in_array($item, ['.htaccess', 'web.config', 'index.php', '.mstv-storage', '.mstv-trash'], true);
    }

    private function is_reindexable_file(string $relativePath, string $extension, string $mimeType): bool
    {
        $absolutePath = $this->filesystem->get_verified_path($relativePath);
        if ($absolutePath === false) {
            return false;
        }

        $validator = new MSTV_Validator($this->settings);
        if (!$validator->validate_extension($extension)
            || !$validator->validate_extension_mime_pair($extension, $mimeType)) {
            return false;
        }

        $scanResult = $validator->scan_file_content($absolutePath, $extension, $mimeType);

        return !empty($scanResult['valid']);
    }

    private function join_relative_paths(string $base, string $item): string
    {
        if ($base === '') {
            return str_replace('\\', '/', $item);
        }

        return str_replace('\\', '/', trim($base, '/\\') . '/' . $item);
    }

    private function get_parent_relative_path(string $relativePath): string
    {
        $parentPath = dirname(str_replace('\\', '/', $relativePath));

        return $parentPath === '.' ? '' : trim(str_replace('\\', '/', $parentPath), '/');
    }

    private function build_file_path(string $folderPath, string $filename): string
    {
        if (empty($folderPath)) {
            return $filename;
        }
        return rtrim($folderPath, '/\\') . '/' . $filename;
    }

    private function copy_uploaded_file(string $sourcePath, string $destinationPath): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Uploads are already validated with is_uploaded_file(); chunked copy avoids loading the full upload into memory.
        $source = @fopen($sourcePath, 'rb');
        if ($source === false) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Destination is a verified plugin storage path; chunked copy avoids loading the full upload into memory.
        $destination = @fopen($destinationPath, 'wb');
        if ($destination === false) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked upload storage.
            fclose($source);
            return false;
        }

        $success = true;

        while (!feof($source)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Chunked upload storage avoids loading the full upload into memory.
            $chunk = fread($source, 1048576);
            if ($chunk === false) {
                $success = false;
                break;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Chunked upload storage writes to a verified plugin storage path.
            $written = fwrite($destination, $chunk);
            if ($written === false || $written !== strlen($chunk)) {
                $success = false;
                break;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked upload storage.
        fclose($source);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked upload storage.
        fclose($destination);

        if (!$success) {
            wp_delete_file($destinationPath);
        }

        return $success;
    }

    private function build_folder_path(string $parentPath, string $slug): string
    {
        if (empty($parentPath)) {
            return $slug;
        }
        return rtrim($parentPath, '/\\') . '/' . $slug;
    }

    private function is_descendant_path(string $candidatePath, string $ancestorPath): bool
    {
        $candidatePath = trim(str_replace('\\', '/', $candidatePath), '/');
        $ancestorPath = trim(str_replace('\\', '/', $ancestorPath), '/');

        if ($ancestorPath === '') {
            return false;
        }

        return $candidatePath === $ancestorPath
            || str_starts_with($candidatePath . '/', $ancestorPath . '/');
    }
}
