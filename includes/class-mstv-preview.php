<?php

defined('ABSPATH') || exit;

class MSTV_Preview
{
    use MSTV_Binary_Stream;
    private MSTV_Storage $storage;
    private MSTV_Repository_Files $filesRepo;
    private MSTV_Auth $auth;
    private MSTV_Settings $settings;
    private ?MSTV_Permissions $permissions;
    private ?MSTV_Logger $logger;

    public function __construct(
        MSTV_Storage $storage,
        MSTV_Repository_Files $filesRepo,
        MSTV_Auth $auth,
        MSTV_Settings $settings,
        ?MSTV_Permissions $permissions = null,
        ?MSTV_Logger $logger = null
    ) {
        $this->storage = $storage;
        $this->filesRepo = $filesRepo;
        $this->auth = $auth;
        $this->settings = $settings;
        $this->permissions = $permissions;
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

        if ($this->permissions && !$this->permissions->current_user_can(
            $files->folder_id ? (int) $files->folder_id : null,
            MSTV_Permissions::ACTION_VIEW
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

        $mimeType = $filesystem->get_mime_type($files->relative_path);
        $fileSize = $filesystem->get_file_size($files->relative_path);

        if (!$this->can_preview((object) [
            'extension' => $files->extension,
            'mime_type' => $mimeType ?: (string) $files->mime_type,
        ])) {
            wp_die(
                esc_html__('Preview is not available for this file type.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 400]
            );
        }

        if ($this->logger) {
            $this->logger->log('preview', 'file', $fileId, [
                'filename' => $files->display_name,
            ]);
        }

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_file_previewed($fileId, [
                'display_name' => $files->display_name,
                'extension' => $files->extension,
                'mime_type' => $files->mime_type,
            ]);
        }

        $this->stream_preview(
            $fullPath,
            $files->display_name,
            $files->extension,
            $mimeType ?: (string) $files->mime_type,
            $fileSize > 0 ? $fileSize : (int) $files->file_size
        );
    }

    private function stream_preview(string $path, string $filename, string $extension, string $mimeType, int $fileSize): void
    {
        if (!is_readable($path)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side diagnostic; not exposed to users.
            error_log('TeamVault: file not readable for preview: ' . $path);
            wp_die(
                esc_html__('Unable to read the file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        nocache_headers();

        $safeMime = sanitize_mime_type(str_replace(["\r", "\n"], '', $mimeType));

        if (!MSTV_Helpers::is_previewable($extension, $safeMime)) {
            wp_die(
                esc_html__('Preview is not available for this file type.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 400]
            );
        }

        header('Content-Type: ' . $safeMime);
        header('Content-Disposition: inline; filename="' . $this->sanitize_filename($filename) . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');

        if ($safeMime === 'application/pdf') {
            header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\';');
        }

        if (!$this->stream_binary($path, $fileSize)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side diagnostic; not exposed to users.
            error_log('TeamVault: stream failed for preview: ' . $path);
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

    public function get_preview_url(int $fileId): string
    {
        return add_query_arg([
            'action' => 'mstv_preview_file',
            'file_id' => $fileId,
            'mstv_stream_nonce' => wp_create_nonce('mstv_stream_action'),
        ], admin_url('admin-post.php'));
    }

    public function can_preview(object $files): bool
    {
        if (strtolower((string) $files->extension) === 'pdf' && !$this->settings->is_pdf_preview_enabled()) {
            return false;
        }

        return MSTV_Helpers::is_previewable($files->extension, $files->mime_type);
    }
}
