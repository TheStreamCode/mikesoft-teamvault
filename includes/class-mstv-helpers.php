<?php

defined('ABSPATH') || exit;

class MSTV_Helpers
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
        $name = preg_replace('~[\\/]+~', ' ', $name);
        $name = preg_replace('/\.{2,}/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', (string) $name);

        return trim((string) $name);
    }

    public static function resolve_file_display_name(string $displayName, string $originalName): string
    {
        $displayName = self::sanitize_file_display_name($displayName);

        if ($displayName !== '') {
            return $displayName;
        }

        $fallback = pathinfo($originalName, PATHINFO_FILENAME);
        $fallback = self::sanitize_file_display_name((string) $fallback);

        if ($fallback !== '') {
            return $fallback;
        }

        return self::sanitize_file_display_name($originalName);
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
        $previewMimeMap = [
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
        ];

        $extension = strtolower(trim($extension));
        $mimeType = self::normalize_mime_type($mimeType);

        return isset($previewMimeMap[$extension])
            && in_array($mimeType, $previewMimeMap[$extension], true);
    }

    public static function mime_matches_extension(string $extension, string $mimeType): bool
    {
        $mimeMap = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/x-ole-storage', 'application/cdfv2'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/cdfv2'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/cdfv2'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
            'rar' => ['application/vnd.rar', 'application/x-rar', 'application/x-rar-compressed'],
            '7z' => ['application/x-7z-compressed'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
            'rtf' => ['application/rtf', 'text/rtf', 'application/x-rtf'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
            'ogg' => ['audio/ogg', 'application/ogg'],
            'mp4' => ['video/mp4', 'application/mp4'],
            'avi' => ['video/x-msvideo', 'video/avi'],
            'mov' => ['video/quicktime'],
            'mkv' => ['video/x-matroska'],
        ];

        /**
         * Filter the accepted MIME signatures for each TeamVault extension.
         *
         * @param array<string, string[]> $mimeMap Extension-to-MIME map.
         */
        $mimeMap = apply_filters('mstv_extension_mime_map', $mimeMap);
        $extension = strtolower(trim($extension));
        $mimeType = self::normalize_mime_type($mimeType);

        $acceptedMimes = isset($mimeMap[$extension]) && is_array($mimeMap[$extension])
            ? array_values(array_filter($mimeMap[$extension], 'is_string'))
            : [];

        return in_array($mimeType, array_map([self::class, 'normalize_mime_type'], $acceptedMimes), true);
    }

    private static function normalize_mime_type(string $mimeType): string
    {
        return strtolower(trim(explode(';', $mimeType, 2)[0]));
    }

    public static function human_time_diff_mysql(string $mysqlTime): string
    {
        $time = strtotime($mysqlTime);
        if ($time === false) {
            return '';
        }
        return human_time_diff($time, time()) . ' ' . __('ago', 'mikesoft-teamvault');
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

    /**
     * Names and contents of the files that protect the private storage directory.
     *
     * The first three entries are required for the directory to be considered
     * protected; the storage marker is informational and never blocks uploads.
     *
     * @return array<string,string>
     */
    public static function protection_file_contents(): array
    {
        // Cover Apache 2.4 (mod_authz_core), Apache 2.2 (mod_access_compat) and,
        // as a last resort, mod_rewrite. On Apache 2.4 the legacy "Order/Deny"
        // directives are inert unless mod_access_compat is loaded, so the native
        // "Require all denied" must be emitted too.
        $htaccess = "# Mikesoft TeamVault - Access Denied\n";
        $htaccess .= "<IfModule mod_authz_core.c>\n";
        $htaccess .= "  Require all denied\n";
        $htaccess .= "</IfModule>\n";
        $htaccess .= "<IfModule !mod_authz_core.c>\n";
        $htaccess .= "  Order deny,allow\n";
        $htaccess .= "  Deny from all\n";
        $htaccess .= "</IfModule>\n";
        $htaccess .= "<IfModule mod_rewrite.c>\n";
        $htaccess .= "  RewriteEngine On\n";
        $htaccess .= "  RewriteRule .* - [F]\n";
        $htaccess .= "</IfModule>\n";

        $webconfig = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $webconfig .= "<configuration>\n";
        $webconfig .= "  <system.webServer>\n";
        $webconfig .= "    <handlers>\n";
        $webconfig .= "      <clear />\n";
        $webconfig .= "    </handlers>\n";
        $webconfig .= "    <httpProtocol>\n";
        $webconfig .= "      <customHeaders>\n";
        $webconfig .= "        <add name=\"X-Content-Type-Options\" value=\"nosniff\" />\n";
        $webconfig .= "      </customHeaders>\n";
        $webconfig .= "    </httpProtocol>\n";
        $webconfig .= "  </system.webServer>\n";
        $webconfig .= "</configuration>";

        return [
            '.htaccess' => $htaccess,
            'web.config' => $webconfig,
            'index.php' => "<?php // Silence is golden",
            '.mstv-storage' => "Mikesoft TeamVault storage marker\n",
        ];
    }

    /**
     * Create the web-server protection files in a storage directory.
     *
     * Fail-closed contract: the result reports every file that could not be
     * created so callers can refuse uploads instead of serving private documents
     * from an unprotected directory. An existing non-empty file counts as
     * protection even when the directory is not writable any more; an empty or
     * missing file is rewritten and reported as failed when the write fails.
     *
     * @param string        $path   Absolute storage directory.
     * @param callable|null $writer Optional writer receiving (string $file, string $content): bool,
     *                              used by tests to simulate a failing filesystem.
     * @return array{files: array<string,bool>, failed: string[], marker: bool, ok: bool}
     */
    public static function ensure_protection_files(string $path, ?callable $writer = null): array
    {
        $path = rtrim($path, '/\\');
        $results = [
            'files' => [],
            'failed' => [],
            'marker' => true,
            'ok' => true,
        ];

        if ($path === '') {
            // Never write protection files relative to the filesystem root.
            $results['failed'] = ['.htaccess', 'web.config', 'index.php'];
            $results['marker'] = false;
            $results['ok'] = false;

            return $results;
        }

        foreach (self::protection_file_contents() as $name => $content) {
            $target = $path . '/' . $name;
            $isMarker = $name === '.mstv-storage';
            $exists = file_exists($target);

            if ($exists && ($isMarker || $name === 'index.php' || filesize($target) > 0)) {
                $results['files'][$name] = true;
                continue;
            }

            $written = $writer !== null
                ? (bool) call_user_func($writer, $target, $content)
                : self::write_protection_file($target, $content);

            $results['files'][$name] = $written;

            if (!$written) {
                if ($isMarker) {
                    $results['marker'] = false;
                } else {
                    $results['failed'][] = $name;
                }
            }
        }

        $results['ok'] = $results['failed'] === [];

        return $results;
    }

    /**
     * Backward-compatible wrapper that only creates the protection files.
     */
    public static function create_protection_files(string $path): void
    {
        self::ensure_protection_files($path);
    }

    private static function write_protection_file(string $target, string $content): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Protection files live at the storage root and must be written even when WP_Filesystem is not initialized.
        return @file_put_contents($target, $content) !== false;
    }
}
