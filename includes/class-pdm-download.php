<?php

defined('ABSPATH') || exit;

class PDM_Download
{
    private PDM_Storage $storage;
    private PDM_Repository_Files $filesRepo;
    private PDM_Auth $auth;
    private PDM_Logger $logger;

    public function __construct(
        PDM_Storage $storage,
        PDM_Repository_Files $filesRepo,
        PDM_Auth $auth,
        PDM_Logger $logger
    ) {
        $this->storage = $storage;
        $this->filesRepo = $filesRepo;
        $this->auth = $auth;
        $this->logger = $logger;
    }

    public function serve(int $fileId): void
    {
        if (!$this->auth->can_read()) {
            wp_die(
                esc_html__('Access denied.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 403]
            );
        }

        $files = $this->filesRepo->find($fileId);
        if (!$files) {
            wp_die(
                esc_html__('File not found.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 404]
            );
        }

        $filesystem = $this->storage->get_filesystem();
        $fullPath = $filesystem->resolve($files->relative_path);

        if (!$filesystem->is_file($files->relative_path)) {
            wp_die(
                esc_html__('File not found in the filesystem.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 404]
            );
        }

        if (!$filesystem->verify_path($fullPath)) {
            wp_die(
                esc_html__('Access denied.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 403]
            );
        }

        $this->logger->log('download', 'file', $fileId, [
            'filename' => $files->display_name,
        ]);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_file_downloaded($fileId, [
                'display_name' => $files->display_name,
                'extension' => $files->extension,
                'mime_type' => $files->mime_type,
            ]);
        }

        $downloadFilename = $this->build_download_filename($files->display_name, $files->extension);
        $mimeType = $filesystem->get_mime_type($files->relative_path);
        $fileSize = $filesystem->get_file_size($files->relative_path);

        $this->stream_file(
            $fullPath,
            $downloadFilename,
            $mimeType ?: (string) $files->mime_type,
            $fileSize > 0 ? $fileSize : (int) $files->file_size
        );
    }

    private function build_download_filename(string $displayName, string $extension): string
    {
        return PDM_Helpers::build_safe_download_filename($displayName, $extension);
    }

    private function stream_file(string $path, string $filename, string $mimeType, int $fileSize): void
    {
        nocache_headers();

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $this->sanitize_filename($filename) . '"');
        header('Content-Length: ' . $fileSize);
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');

        if (!is_readable($path)) {
            wp_die(
                esc_html__('Unable to read the file.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 500]
            );
        }

        $contents = $this->storage->get_filesystem()->read_absolute_file($path);

        if ($contents === false) {
            wp_die(
                esc_html__('Unable to read the file.', 'private-document-manager'),
                esc_html__('Error', 'private-document-manager'),
                ['response' => 500]
            );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary files stream output must not be escaped.
        echo $contents;

        exit;
    }

    private function sanitize_filename(string $filename): string
    {
        return PDM_Helpers::sanitize_archive_entry_segment($filename);
    }

    public function get_download_url(int $fileId): string
    {
        return add_query_arg([
            'action' => 'pdm_download_file',
            'file_id' => $fileId,
            'pdm_stream_nonce' => wp_create_nonce('pdm_stream_action'),
        ], admin_url('admin-post.php'));
    }
}
