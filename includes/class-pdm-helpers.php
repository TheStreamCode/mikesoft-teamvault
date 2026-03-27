<?php

defined('ABSPATH') || exit;

class PDM_Helpers
{
    private const FALLBACK_ARCHIVE_NAME = 'document';

    public static function sanitize_folder_name(string $name): string
    {
        $name = trim($name);
        $name = sanitize_file_name($name);
        $name = preg_replace('/[^a-zA-Z0-9\.\-_]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-.');
        
        return $name;
    }

    public static function sanitize_file_display_name(string $name): string
    {
        $name = trim($name);
        $name = sanitize_text_field($name);
        $name = preg_replace('/[\\\/]+/', ' ', $name);
        $name = preg_replace('/\.{2,}/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', (string) $name);

        return trim((string) $name);
    }

    public static function sanitize_archive_entry_segment(string $name, string $fallback = self::FALLBACK_ARCHIVE_NAME): string
    {
        $name = sanitize_file_name($name);
        $name = str_replace(['..', '/', '\\'], '-', $name);
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '-', (string) $name);
        $name = preg_replace('/\s+/', '-', (string) $name);
        $name = preg_replace('/-+/', '-', (string) $name);
        $name = trim((string) $name, '-._');

        return $name !== '' ? $name : $fallback;
    }

    public static function build_safe_download_filename(string $displayName, string $extension): string
    {
        $extension = strtolower(trim($extension));
        $sanitized = self::sanitize_archive_entry_segment($displayName);

        if ($extension !== '' && !preg_match('/\.' . preg_quote($extension, '/') . '$/i', $sanitized)) {
            $sanitized .= '.' . $extension;
        }

        return $sanitized;
    }

    public static function generate_secure_filename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . strtolower($extension);
    }

    public static function format_filesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public static function get_file_icon(string $extension): string
    {
        $icons = [
            'pdf' => 'pdf',
            'doc' => 'word', 'docx' => 'word',
            'xls' => 'excel', 'xlsx' => 'excel',
            'ppt' => 'powerpoint', 'pptx' => 'powerpoint',
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image', 'svg' => 'image',
            'zip' => 'archive', 'rar' => 'archive', '7z' => 'archive', 'tar' => 'archive', 'gz' => 'archive',
            'mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio',
            'mp4' => 'video', 'avi' => 'video', 'mov' => 'video', 'mkv' => 'video',
            'txt' => 'text', 'rtf' => 'text',
            'csv' => 'csv',
            'json' => 'code', 'xml' => 'code', 'html' => 'code', 'css' => 'code',
        ];

        return $icons[strtolower($extension)] ?? 'default';
    }

    public static function is_previewable(string $extension, string $mimeType): bool
    {
        $previewable = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'pdf',
        ];

        $previewableMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf',
        ];

        return in_array(strtolower($extension), $previewable, true) 
            || in_array(strtolower($mimeType), $previewableMimes, true);
    }

    public static function human_time_diff_mysql(string $mysqlTime): string
    {
        $time = strtotime($mysqlTime);
        return human_time_diff($time, time()) . ' ' . __('ago', 'private-document-manager');
    }

    public static function build_breadcrumb(array $folders, int $currentFolderId): array
    {
        $breadcrumb = [];
        $folderMap = [];

        foreach ($folders as $folder) {
            $folderMap[$folder->id] = $folder;
        }

        $current = $folderMap[$currentFolderId] ?? null;
        while ($current) {
            array_unshift($breadcrumb, [
                'id' => $current->id,
                'name' => $current->name,
            ]);
            $current = isset($current->parent_id) ? ($folderMap[$current->parent_id] ?? null) : null;
        }

        return $breadcrumb;
    }

    public static function verify_nonce(string $action, ?string $nonce = null): bool
    {
        if (null === $nonce) {
            $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
        }
        
        return wp_verify_nonce($nonce, $action) !== false;
    }

    public static function send_json_error(string $message, int $statusCode = 400): void
    {
        wp_send_json_error(['message' => $message], $statusCode);
    }

    public static function send_json_success(array $data = [], int $statusCode = 200): void
    {
        wp_send_json_success($data, $statusCode);
    }
}
