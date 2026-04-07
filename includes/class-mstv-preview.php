<?php

defined('ABSPATH') || exit;

class MSTV_Preview
{
    private MSTV_Storage $storage;
    private MSTV_Repository_Files $filesRepo;
    private MSTV_Auth $auth;
    private MSTV_Settings $settings;

    public function __construct(
        MSTV_Storage $storage,
        MSTV_Repository_Files $filesRepo,
        MSTV_Auth $auth,
        MSTV_Settings $settings
    ) {
        $this->storage = $storage;
        $this->filesRepo = $filesRepo;
        $this->auth = $auth;
        $this->settings = $settings;
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
            $mimeType ?: (string) $files->mime_type,
            $fileSize > 0 ? $fileSize : (int) $files->file_size
        );
    }

    private function stream_preview(string $path, string $filename, string $mimeType, int $fileSize): void
    {
        nocache_headers();

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Content-Disposition: inline; filename="' . $this->sanitize_filename($filename) . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');

        if ($mimeType === 'application/pdf') {
            header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\';');
        }

        if (!is_readable($path)) {
            wp_die(
                esc_html__('Unable to read the file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        $contents = $this->storage->get_filesystem()->read_absolute_file($path);

        if ($contents === false) {
            wp_die(
                esc_html__('Unable to read the file.', 'mikesoft-teamvault'),
                esc_html__('Error', 'mikesoft-teamvault'),
                ['response' => 500]
            );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary preview stream output must not be escaped.
        echo $contents;

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
