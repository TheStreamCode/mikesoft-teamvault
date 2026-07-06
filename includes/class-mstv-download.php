<?php

defined('ABSPATH') || exit;

class MSTV_Download
{
    use MSTV_Binary_Stream;
    private MSTV_Storage $storage;
    private MSTV_Repository_Files $filesRepo;
    private MSTV_Auth $auth;
    private MSTV_Logger $logger;
    private ?MSTV_Permissions $permissions;

    public function __construct(
        MSTV_Storage $storage,
        MSTV_Repository_Files $filesRepo,
        MSTV_Auth $auth,
        MSTV_Logger $logger,
        ?MSTV_Permissions $permissions = null
    ) {
        $this->storage = $storage;
        $this->filesRepo = $filesRepo;
        $this->auth = $auth;
        $this->logger = $logger;
        $this->permissions = $permissions;
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

        if ($this->permissions && !$this->permissions->current_user_can(
            $files->folder_id ? (int) $files->folder_id : null,
            MSTV_Permissions::ACTION_DOWNLOAD
        )) {
            wp_die(
                esc_html__('Access denied.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 403]
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
        if (!is_readable($path)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side diagnostic; not exposed to users.
            error_log('TeamVault: file not readable for download: ' . $path);
            wp_die(
                esc_html__('Unable to read the file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        nocache_headers();

        $safeMime = sanitize_mime_type(str_replace(["\r", "\n"], '', $mimeType));
        header('Content-Type: ' . $safeMime);
        header('Content-Disposition: attachment; filename="' . $this->sanitize_filename($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');

        if (!$this->stream_binary($path, $fileSize)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side diagnostic; not exposed to users.
            error_log('TeamVault: stream failed for download: ' . $path);
            wp_die(
                esc_html__('Unable to read the file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        exit;
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
