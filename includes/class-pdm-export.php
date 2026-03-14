<?php

defined('ABSPATH') || exit;

class PDM_Export
{
    private const MAX_EXPORT_FILES = 5000;

    private PDM_Storage $storage;
    private PDM_Repository_Files $filesRepo;
    private PDM_Repository_Folders $folderRepo;
    private PDM_Auth $auth;
    private string $currentZipPath = '';
    private int $currentFileCount = 0;

    public function __construct(
        PDM_Storage $storage,
        PDM_Repository_Files $filesRepo,
        PDM_Repository_Folders $folderRepo,
        PDM_Auth $auth
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

    public function export_folder(?int $folderId, ?string $zipName = null): void
    {
        if (!$this->auth->can_read()) {
            wp_die(
                esc_html__('Access denied.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 403]
            );
        }

        if (!class_exists('ZipArchive')) {
            wp_die(
                esc_html__('ZipArchive is not available on this server. Contact the server administrator.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 500]
            );
        }

        $folder = $folderId ? $this->folderRepo->find($folderId) : null;

        if ($folderId && !$folder) {
            wp_die(
                esc_html__('Folder not found.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 404]
            );
        }

        $zipName = $zipName ?: ($folder ? $this->sanitize_zip_name($folder->name) : 'documents-export');
        $zipPath = $this->create_temp_zip_path($zipName);

        $this->currentZipPath = $zipPath;
        $this->currentFileCount = 0;
        register_shutdown_function([$this, 'cleanup_zip']);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_export_started($folderId, $zipPath);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            wp_delete_file($zipPath);
            wp_die(
                esc_html__('Unable to create the ZIP file.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
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
                esc_html__('Error while creating the ZIP file.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 500]
            );
        }

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_export_completed($folderId, $zipPath, $this->currentFileCount);
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
            $folderPath = $basePath . $folder->name . '/';
            $zip->addEmptyDir($folderPath);
            $this->add_folder_to_zip($zip, $folder->id, $folderPath);
        }

        $files = $this->filesRepo->find_by_folder($folderId);

        foreach ($files as $files) {
            if ($this->currentFileCount >= self::MAX_EXPORT_FILES) {
                throw new \RuntimeException('Export limit exceeded');
            }

            $filesPath = $this->storage->get_filesystem()->resolve($files->relative_path);

            if (file_exists($filesPath) && is_readable($filesPath)) {
                $zipPath = $basePath . $this->get_filename_with_extension($files);
                $zip->addFile($filesPath, $zipPath);
                $this->currentFileCount++;
            }
        }
    }

    private function get_filename_with_extension(object $files): string
    {
        $name = $files->display_name;
        $ext = $files->extension;

        if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $name)) {
            $name .= '.' . $ext;
        }

        return $name;
    }

    private function sanitize_zip_name(string $name): string
    {
        $name = sanitize_file_name($name);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-._');

        if (empty($name)) {
            $name = 'export';
        }

        return $name;
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
                esc_html__('Unable to read the ZIP file.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 500]
            );
        }

        $contents = $this->storage->get_filesystem()->read_absolute_file($zipPath);

        if ($contents === false) {
            wp_delete_file($zipPath);

            wp_die(
                esc_html__('Unable to read the ZIP file.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 500]
            );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary ZIP stream output must not be escaped.
        echo $contents;

        wp_delete_file($zipPath);

        exit;
    }
}
