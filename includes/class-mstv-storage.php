<?php

defined('ABSPATH') || exit;

class MSTV_Storage
{
    private const STORAGE_USAGE_CACHE_TTL = 300;

    private MSTV_Settings $settings;
    private MSTV_Filesystem $filesystem;

    public function __construct(MSTV_Settings $settings)
    {
        $this->settings = $settings;
        $this->filesystem = new MSTV_Filesystem($settings->get_storage_path());
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
        $diskStats = $this->filesystem->get_disk_stats();
        $pluginUsedBytes = $this->get_plugin_used_bytes();
        $otherUsedBytes = max(0, (int) $diskStats['used_bytes'] - $pluginUsedBytes);

        return [
            'disk' => $diskStats,
            'plugin_used_bytes' => $pluginUsedBytes,
            'plugin_used_formatted' => MSTV_Helpers::format_filesize($pluginUsedBytes),
            'other_used_bytes' => $otherUsedBytes,
            'other_used_formatted' => MSTV_Helpers::format_filesize($otherUsedBytes),
            'disk_total_formatted' => MSTV_Helpers::format_filesize($diskStats['total_bytes']),
            'disk_free_formatted' => MSTV_Helpers::format_filesize($diskStats['free_bytes']),
            'disk_used_formatted' => MSTV_Helpers::format_filesize($diskStats['used_bytes']),
        ];
    }

    private function get_plugin_used_bytes(string $relativePath = ''): int
    {
        if ($relativePath === '') {
            $cachedValue = get_transient($this->get_storage_usage_cache_key());

            if (is_numeric($cachedValue)) {
                return max(0, (int) $cachedValue);
            }
        }

        $total = 0;

        foreach ($this->filesystem->list_directory($relativePath) as $item) {
            if ($this->should_skip_reindex_item($relativePath, $item)) {
                continue;
            }

            $itemRelativePath = $this->join_relative_paths($relativePath, $item);

            if ($this->filesystem->is_dir($itemRelativePath)) {
                $total += $this->get_plugin_used_bytes($itemRelativePath);
                continue;
            }

            $total += $this->filesystem->get_file_size($itemRelativePath);
        }

        if ($relativePath === '') {
            set_transient($this->get_storage_usage_cache_key(), $total, self::STORAGE_USAGE_CACHE_TTL);
        }

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
        ];

        $this->reindex_directory('', $folderRepo, $filesRepo, $folderMap, $fileMap, $createdBy, $stats);
        $this->invalidate_storage_usage_cache();

        return [
            'success' => true,
            'folders_created' => $stats['folders_created'],
            'files_created' => $stats['files_created'],
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

        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $storedName = MSTV_Helpers::generate_secure_filename($extension);
        
        $folderPath = $this->get_folder_path($folderId, $folderRepo);
        $relativePath = $this->build_file_path($folderPath, $storedName);
        $fullPath = $this->filesystem->resolve($relativePath);

        if (!$this->filesystem->is_path_within_base($fullPath)) {
            return [
                'success' => false,
                'error' => __('Invalid destination path.', 'mikesoft-teamvault'),
            ];
        }

        $targetDir = dirname($fullPath);
        if (!is_dir($targetDir)) {
            wp_mkdir_p($targetDir);
        }

        $contents = @file_get_contents($uploadedFile['tmp_name']);

        if ($contents === false || !$this->filesystem->write_file($relativePath, $contents)) {
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

        $deleted = $this->filesystem->delete_file($files->relative_path);
        if (!$deleted) {
            return [
                'success' => false,
                'error' => __('Unable to delete the files from the filesystem.', 'mikesoft-teamvault'),
            ];
        }

        $this->invalidate_storage_usage_cache();

        return ['success' => true];
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
                'error' => __('The files is already in the destination folder.', 'mikesoft-teamvault'),
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

    public function ensure_storage_directory(): bool
    {
        $basePath = $this->get_base_path();
        
        if (!is_dir($basePath)) {
            wp_mkdir_p($basePath);
        }

        if (is_dir($basePath)) {
            $this->create_protection_files($basePath);
        }

        return is_dir($basePath) && $this->filesystem->is_writable($basePath);
    }

    private function create_protection_files(string $path): void
    {
        MSTV_Helpers::create_protection_files($path);
    }

    private function reindex_directory(
        string $relativePath,
        MSTV_Repository_Folders $folderRepo,
        MSTV_Repository_Files $filesRepo,
        array &$folderMap,
        array &$fileMap,
        int $createdBy,
        array &$stats
    ): void {
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
                    $folderMap[$itemRelativePath] = $folderId;
                    $stats['folders_created']++;
                }

                $this->reindex_directory($itemRelativePath, $folderRepo, $filesRepo, $folderMap, $fileMap, $createdBy, $stats);
                continue;
            }

            if (isset($fileMap[$itemRelativePath])) {
                continue;
            }

            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $displayName = MSTV_Helpers::resolve_file_display_name('', (string) $item);
            $parentPath = $this->get_parent_relative_path($itemRelativePath);

            $filesRepo->create([
                'folder_id' => $parentPath === '' ? null : ($folderMap[$parentPath] ?? null),
                'original_name' => $item,
                'stored_name' => $item,
                'display_name' => $displayName,
                'relative_path' => $itemRelativePath,
                'extension' => $extension,
                'mime_type' => $this->filesystem->get_mime_type($itemRelativePath),
                'file_size' => $this->filesystem->get_file_size($itemRelativePath),
                'checksum' => $this->filesystem->get_file_checksum($itemRelativePath),
                'created_by' => $createdBy,
            ]);

            $fileMap[$itemRelativePath] = true;
            $stats['files_created']++;
        }
    }

    private function should_skip_reindex_item(string $relativePath, string $item): bool
    {
        if ($relativePath !== '') {
            return false;
        }

        return in_array($item, ['.htaccess', 'web.config', 'index.php', '.mstv-storage'], true);
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

    private function build_folder_path(string $parentPath, string $slug): string
    {
        if (empty($parentPath)) {
            return $slug;
        }
        return rtrim($parentPath, '/\\') . '/' . $slug;
    }
}
