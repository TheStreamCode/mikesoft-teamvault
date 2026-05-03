<?php

defined('ABSPATH') || exit;

class MSTV_Export
{
    private const MAX_EXPORT_FILES = 5000;

    private MSTV_Storage $storage;
    private MSTV_Repository_Files $filesRepo;
    private MSTV_Repository_Folders $folderRepo;
    private MSTV_Auth $auth;
    private string $currentZipPath = '';
    private int $currentFileCount = 0;
    private array $reservedArchivePaths = [];

    public function __construct(
        MSTV_Storage $storage,
        MSTV_Repository_Files $filesRepo,
        MSTV_Repository_Folders $folderRepo,
        MSTV_Auth $auth
    ) {
        $this->storage = $storage;
        $this->filesRepo = $filesRepo;
        $this->folderRepo = $folderRepo;
        $this->auth = $auth;
    }

    public function export_all(): void
    {
        $this->export_folder(null, 'documents-export');
    }

    public function export_selection(array $folderIds, ?string $zipName = null): void
    {
        if (!$this->auth->can_read()) {
            wp_die(
                esc_html__('Access denied.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 403]
            );
        }

        if (!class_exists('ZipArchive')) {
            wp_die(
                esc_html__('ZipArchive is not available on this server. Contact the server administrator.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        $selectedFolders = $this->get_selected_export_folders($folderIds);

        if (empty($selectedFolders)) {
            wp_die(
                esc_html__('No folders selected for export.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 400]
            );
        }

        $zipName = $zipName ?: 'selected-folders-export';
        $zipPath = $this->create_temp_zip_path($zipName);

        $this->currentZipPath = $zipPath;
        $this->currentFileCount = 0;
        $this->reservedArchivePaths = [];
        register_shutdown_function([$this, 'cleanup_zip']);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_export_started(null, $zipPath);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            wp_delete_file($zipPath);
            wp_die(
                esc_html__('Unable to create the ZIP file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        try {
            $usedPaths = [];

            foreach ($selectedFolders as $folder) {
                $folderPath = $this->build_unique_zip_path($folder->name, $usedPaths);
                $zip->addEmptyDir($folderPath);
                $this->add_folder_to_zip($zip, (int) $folder->id, $folderPath);
            }

            $zip->close();
        } catch (\Exception $e) {
            $zip->close();
            wp_delete_file($zipPath);
            wp_die(
                esc_html__('Error while creating the ZIP file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_export_completed(null, $zipPath, $this->currentFileCount);
        }

        $this->stream_zip($zipPath, $zipName);
    }

    public function export_folder(?int $folderId, ?string $zipName = null): void
    {
        if (!$this->auth->can_read()) {
            wp_die(
                esc_html__('Access denied.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 403]
            );
        }

        if (!class_exists('ZipArchive')) {
            wp_die(
                esc_html__('ZipArchive is not available on this server. Contact the server administrator.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        $folder = $folderId ? $this->folderRepo->find($folderId) : null;

        if ($folderId && !$folder) {
            wp_die(
                esc_html__('Folder not found.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 404]
            );
        }

        $zipName = $zipName ?: ($folder ? $this->sanitize_zip_name($folder->name) : 'documents-export');
        $zipPath = $this->create_temp_zip_path($zipName);

        $this->currentZipPath = $zipPath;
        $this->currentFileCount = 0;
        $this->reservedArchivePaths = [];
        register_shutdown_function([$this, 'cleanup_zip']);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_export_started($folderId, $zipPath);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            wp_delete_file($zipPath);
            wp_die(
                esc_html__('Unable to create the ZIP file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        try {
            $this->add_folder_to_zip($zip, $folderId, '');
            $zip->close();
        } catch (\Exception $e) {
            $zip->close();
            wp_delete_file($zipPath);
            wp_die(
                esc_html__('Error while creating the ZIP file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_export_completed($folderId, $zipPath, $this->currentFileCount);
        }

        $this->stream_zip($zipPath, $zipName);
    }

    public function cleanup_zip(): void
    {
        if (!empty($this->currentZipPath) && file_exists($this->currentZipPath)) {
            wp_delete_file($this->currentZipPath);
        }
    }

    private function add_folder_to_zip(ZipArchive $zip, ?int $folderId, string $basePath): void
    {
        $folders = $this->folderRepo->find_by_parent($folderId);

        foreach ($folders as $folder) {
            $folderPath = $this->build_unique_folder_archive_path($basePath, $folder->name);
            $zip->addEmptyDir($folderPath);
            $this->add_folder_to_zip($zip, $folder->id, $folderPath);
        }

        $files = $this->filesRepo->find_by_folder($folderId);

        foreach ($files as $files) {
            if ($this->currentFileCount >= self::MAX_EXPORT_FILES) {
                throw new \RuntimeException('Export limit exceeded');
            }

            $filesPath = $this->storage->get_filesystem()->get_verified_path((string) $files->relative_path);

            if ($filesPath !== false && file_exists($filesPath) && is_readable($filesPath)) {
                $zipPath = $this->build_unique_file_archive_path($basePath, $files);
                $zip->addFile($filesPath, $zipPath);
                $this->currentFileCount++;
            }
        }
    }

    private function get_filename_with_extension(object $files): string
    {
        return MSTV_Helpers::build_safe_download_filename((string) $files->display_name, (string) $files->extension);
    }

    private function sanitize_zip_name(string $name): string
    {
        return MSTV_Helpers::sanitize_archive_entry_segment($name, 'export');
    }

    private function get_selected_export_folders(array $folderIds): array
    {
        $folderIds = array_values(array_unique(array_filter(array_map('absint', $folderIds))));

        if (empty($folderIds)) {
            return [];
        }

        $folderMap = [];
        foreach ($this->folderRepo->find_all() as $folder) {
            $folderMap[(int) $folder->id] = $folder;
        }

        $selectedFolders = [];

        foreach ($folderIds as $folderId) {
            if (!isset($folderMap[$folderId])) {
                continue;
            }

            $current = $folderMap[$folderId];
            $skip = false;

            while ($current && $current->parent_id !== null) {
                $parentId = (int) $current->parent_id;

                if (in_array($parentId, $folderIds, true)) {
                    $skip = true;
                    break;
                }

                $current = $folderMap[$parentId] ?? null;
            }

            if (!$skip) {
                $selectedFolders[] = $folderMap[$folderId];
            }
        }

        return $selectedFolders;
    }

    private function build_unique_zip_path(string $folderName, array &$usedPaths): string
    {
        $baseName = $this->sanitize_zip_name($folderName);
        $candidate = $baseName;
        $suffix = 2;

        while (in_array($candidate, $usedPaths, true)) {
            $candidate = $baseName . '-' . $suffix;
            $suffix++;
        }

        $usedPaths[] = $candidate;

        return $candidate . '/';
    }

    private function build_unique_folder_archive_path(string $basePath, string $folderName): string
    {
        $baseName = MSTV_Helpers::sanitize_archive_entry_segment($folderName, 'folder');
        $candidate = $basePath . $baseName . '/';
        $suffix = 2;

        while ($this->is_reserved_archive_path($candidate)) {
            $candidate = $basePath . $baseName . '-' . $suffix . '/';
            $suffix++;
        }

        $this->reserve_archive_path($candidate);

        return $candidate;
    }

    private function build_unique_file_archive_path(string $basePath, object $files): string
    {
        $baseName = $this->get_filename_with_extension($files);
        $extension = pathinfo($baseName, PATHINFO_EXTENSION);
        $filename = pathinfo($baseName, PATHINFO_FILENAME);
        $candidate = $basePath . $baseName;
        $suffix = 2;

        while ($this->is_reserved_archive_path($candidate)) {
            $candidateName = $filename . '-' . $suffix;
            if ($extension !== '') {
                $candidateName .= '.' . $extension;
            }
            $candidate = $basePath . $candidateName;
            $suffix++;
        }

        $this->reserve_archive_path($candidate);

        return $candidate;
    }

    private function reserve_archive_path(string $path): void
    {
        $this->reservedArchivePaths[strtolower($path)] = true;
    }

    private function is_reserved_archive_path(string $path): bool
    {
        return isset($this->reservedArchivePaths[strtolower($path)]);
    }

    private function create_temp_zip_path(string $name): string
    {
        $tempDir = sys_get_temp_dir();

        if (!wp_is_writable($tempDir)) {
            $tempDir = $this->storage->get_base_path();
        }

        $filename = $name . '-' . gmdate('Y-m-d-His') . '.zip';

        return $tempDir . DIRECTORY_SEPARATOR . $filename;
    }

    private function stream_zip(string $zipPath, string $zipName): void
    {
        $filesize = filesize($zipPath);

        nocache_headers();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . str_replace(["\r", "\n"], '', $zipName) . '.zip"');
        header('Content-Length: ' . $filesize);
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

        if (!is_readable($zipPath)) {
            wp_delete_file($zipPath);
            wp_die(
                esc_html__('Unable to read the ZIP file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        if (!$this->stream_zip_file($zipPath)) {
            wp_delete_file($zipPath);

            wp_die(
                esc_html__('Unable to read the ZIP file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        wp_delete_file($zipPath);

        exit;
    }

    private function stream_zip_file(string $zipPath): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- ZIP exports need chunked streaming; WP_Filesystem::get_contents() loads full archives into memory.
        $handle = @fopen($zipPath, 'rb');

        if ($handle === false) {
            return false;
        }

        while (!feof($handle)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- ZIP exports need chunked streaming; WP_Filesystem::get_contents() loads full archives into memory.
            $chunk = fread($handle, 1048576);
            if ($chunk === false) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
                fclose($handle);
                return false;
            }

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary ZIP stream output must not be escaped.
            echo $chunk;
            flush();
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
        fclose($handle);

        return true;
    }
}
