<?php

defined('ABSPATH') || exit;

class MSTV_Download
{
    private MSTV_Storage $storage;
    private MSTV_Repository_Files $filesRepo;
    private MSTV_Auth $auth;
    private MSTV_Logger $logger;

    public function __construct(
        MSTV_Storage $storage,
        MSTV_Repository_Files $filesRepo,
        MSTV_Auth $auth,
        MSTV_Logger $logger
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
                esc_html__('Access denied.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 403]
            );
        }

        $files = $this->filesRepo->find($fileId);
        if (!$files) {
            wp_die(
                esc_html__('File not found.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 404]
            );
        }

        $filesystem = $this->storage->get_filesystem();
        $fullPath = $filesystem->resolve($files->relative_path);

        if (!$filesystem->is_file($files->relative_path)) {
            wp_die(
                esc_html__('File not found in the filesystem.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 404]
            );
        }

        if (!$filesystem->verify_path($fullPath)) {
            wp_die(
                esc_html__('Access denied.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 403]
            );
        }

        $this->logger->log('download', 'file', $fileId, [
            'filename' => $files->display_name,
        ]);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_file_downloaded($fileId, [
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
        return MSTV_Helpers::build_safe_download_filename($displayName, $extension);
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
                esc_html__('Unable to read the file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        if (!$this->stream_absolute_file($path)) {
            wp_die(
                esc_html__('Unable to read the file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        exit;
    }

    private function stream_absolute_file(string $path): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Authenticated binary downloads need chunked streaming; WP_Filesystem::get_contents() loads full files into memory.
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        while (!feof($handle)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Authenticated binary downloads need chunked streaming; WP_Filesystem::get_contents() loads full files into memory.
            $chunk = fread($handle, 1048576);
            if ($chunk === false) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
                fclose($handle);
                return false;
            }

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary files stream output must not be escaped.
            echo $chunk;
            flush();
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
        fclose($handle);

        return true;
    }

    private function sanitize_filename(string $filename): string
    {
        return MSTV_Helpers::sanitize_archive_entry_segment($filename);
    }

    public function get_download_url(int $fileId): string
    {
        return add_query_arg([
            'action' => 'mstv_download_file',
            'file_id' => $fileId,
            'mstv_stream_nonce' => wp_create_nonce('mstv_stream_action'),
        ], admin_url('admin-post.php'));
    }
}
