<?php

defined('ABSPATH') || exit;

class MSTV_Filesystem
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function get_base_path(): string
    {
        return $this->basePath;
    }

    public function exists(string $relativePath): bool
    {
        $fullPath = $this->get_verified_path($relativePath);

        return $fullPath !== false && file_exists($fullPath);
    }

    public function is_file(string $relativePath): bool
    {
        $fullPath = $this->get_verified_path($relativePath);

        return $fullPath !== false && is_file($fullPath);
    }

    public function is_dir(string $relativePath): bool
    {
        $fullPath = $this->get_verified_path($relativePath);

        return $fullPath !== false && is_dir($fullPath);
    }

    public function resolve(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/\\');

        return wp_normalize_path($this->basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
    }

    public function verify_path(string $path): bool
    {
        $realBase = realpath($this->basePath);

        if (false === $realBase) {
            return false;
        }

        $normalizedBase = trailingslashit(wp_normalize_path($realBase));

        $realPath = realpath($path);

        if (false !== $realPath) {
            $normalizedRealPath = wp_normalize_path($realPath);

            return $normalizedRealPath === untrailingslashit($normalizedBase)
                || strpos(trailingslashit($normalizedRealPath), $normalizedBase) === 0;
        }

        $directory = realpath(dirname($path));

        if (false === $directory) {
            return false;
        }

        $normalizedPath = wp_normalize_path($directory . DIRECTORY_SEPARATOR . basename($path));

        return $normalizedPath === untrailingslashit($normalizedBase)
            || strpos(trailingslashit($normalizedPath), $normalizedBase) === 0;
    }

    public function get_verified_path(string $relativePath, bool $allowMissing = false): string|false
    {
        $fullPath = $this->resolve($relativePath);

        if (!$this->verify_path($fullPath)) {
            return false;
        }

        if (!$allowMissing && !file_exists($fullPath)) {
            return false;
        }

        if ($this->path_has_symlink($fullPath)) {
            return false;
        }

        return $fullPath;
    }

    public function is_path_within_base(string $path): bool
    {
        $realBase = realpath($this->basePath);

        if (false === $realBase) {
            return false;
        }

        if (strpos($path, '..') !== false) {
            return false;
        }

        $normalizedPath = wp_normalize_path($path);
        $normalizedBase = trailingslashit(wp_normalize_path($realBase));

        return $normalizedPath === untrailingslashit($normalizedBase)
            || strpos(trailingslashit($normalizedPath), $normalizedBase) === 0;
    }

    public function create_directory(string $relativePath): bool
    {
        $fullPath = $this->get_verified_path($relativePath, true);

        if ($fullPath === false) {
            return false;
        }

        if ($this->exists($relativePath)) {
            return true;
        }

        $wpFilesystem = $this->get_wp_filesystem();

        if ($wpFilesystem) {
            $dirMode = defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : false;

            if ($wpFilesystem->mkdir($fullPath, $dirMode)) {
                return true;
            }
        }

        return wp_mkdir_p($fullPath);
    }

    public function delete_directory(string $relativePath): bool
    {
        $fullPath = $this->get_verified_path($relativePath);

        if ($fullPath === false) {
            return false;
        }

        if (!$this->is_dir($relativePath)) {
            return false;
        }

        return $this->recursive_delete($fullPath);
    }

    public function delete_file(string $relativePath): bool
    {
        $fullPath = $this->get_verified_path($relativePath);

        if ($fullPath === false) {
            return false;
        }

        if (!$this->is_file($relativePath)) {
            return false;
        }

        $wpFilesystem = $this->get_wp_filesystem();

        if ($wpFilesystem && $wpFilesystem->delete($fullPath, false, 'f')) {
            if (!file_exists($fullPath)) {
                return true;
            }
        }

        if (function_exists('wp_delete_file')) {
            wp_delete_file($fullPath);
        } else {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Fallback only when WordPress deletion helpers are unavailable.
            @unlink($fullPath);
        }

        return !file_exists($fullPath);
    }

    public function move_file(string $fromRelative, string $toRelative): bool
    {
        $from = $this->get_verified_path($fromRelative);
        $to = $this->get_verified_path($toRelative, true);

        if ($from === false || $to === false) {
            return false;
        }

        if (!$this->is_file($fromRelative)) {
            return false;
        }

        $toDir = dirname($to);
        if (!is_dir($toDir)) {
            wp_mkdir_p($toDir);
        }

        $wpFilesystem = $this->get_wp_filesystem();

        if ($wpFilesystem && $wpFilesystem->move($from, $to, true)) {
            if (!file_exists($from) && file_exists($to)) {
                return true;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Fallback only when WP_Filesystem move is unavailable.
        return @rename($from, $to);
    }

    public function rename_directory(string $oldRelative, string $newRelative): bool
    {
        $oldPath = $this->get_verified_path($oldRelative);
        $newPath = $this->get_verified_path($newRelative, true);

        if ($oldPath === false || $newPath === false) {
            return false;
        }

        if (!$this->is_dir($oldRelative)) {
            return false;
        }

        if ($this->exists($newRelative)) {
            return false;
        }

        $wpFilesystem = $this->get_wp_filesystem();

        if ($wpFilesystem && $wpFilesystem->move($oldPath, $newPath, false)) {
            if (!file_exists($oldPath) && file_exists($newPath)) {
                return true;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Fallback only when WP_Filesystem move is unavailable.
        return @rename($oldPath, $newPath);
    }

    public function write_file(string $relativePath, string $content): bool
    {
        $fullPath = $this->get_verified_path($relativePath, true);

        if ($fullPath === false) {
            return false;
        }

        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $wpFilesystem = $this->get_wp_filesystem();

        if ($wpFilesystem) {
            $filesMode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : false;
            $result = $wpFilesystem->put_contents($fullPath, $content, $filesMode);

            if ($result) {
                return true;
            }
        }

        $result = @file_put_contents($fullPath, $content);

        return $result !== false;
    }

    public function read_files(string $relativePath): string|false
    {
        $fullPath = $this->get_verified_path($relativePath);

        return $fullPath === false ? false : $this->read_absolute_file($fullPath);
    }

    public function read_absolute_file(string $path): string|false
    {
        if (!$this->verify_path($path) || $this->path_has_symlink($path)) {
            return false;
        }

        $wpFilesystem = $this->get_wp_filesystem();

        if ($wpFilesystem) {
            $contents = $wpFilesystem->get_contents($path);

            if ($contents !== false) {
                return $contents;
            }
        }

        return @file_get_contents($path);
    }

    public function get_file_size(string $relativePath): int
    {
        $fullPath = $this->get_verified_path($relativePath);
        if ($fullPath === false) {
            return 0;
        }

        $size = @filesize($fullPath);
        return $size !== false ? $size : 0;
    }

    public function get_file_checksum(string $relativePath): string
    {
        $fullPath = $this->get_verified_path($relativePath);
        if ($fullPath === false) {
            return '';
        }

        return @md5_file($fullPath) ?: '';
    }

    public function get_mime_type(string $relativePath): string
    {
        $fullPath = $this->get_verified_path($relativePath);
        if ($fullPath === false) {
            return 'application/octet-stream';
        }
        
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($fullPath);
            if ($mime !== false) {
                return $mime;
            }
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $fullPath);
            @finfo_close($finfo);
            if ($mime !== false) {
                return $mime;
            }
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        return $this->get_mime_by_extension($extension);
    }

    private function get_mime_by_extension(string $extension): string
    {
        $mimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'rtf' => 'application/rtf',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
        ];

        return $mimes[$extension] ?? 'application/octet-stream';
    }

    public function list_directory(string $relativePath = ''): array
    {
        $fullPath = $this->get_verified_path($relativePath);

        if ($fullPath === false) {
            return [];
        }

        if (!$this->is_dir($relativePath)) {
            return [];
        }

        $items = @scandir($fullPath);
        if ($items === false) {
            return [];
        }

        return array_values(array_filter($items, function ($item) use ($fullPath) {
            if (in_array($item, ['.', '..'], true)) {
                return false;
            }

            return !is_link($fullPath . DIRECTORY_SEPARATOR . $item);
        }));
    }

    private function recursive_delete(string $path): bool
    {
        if (!$this->verify_path($path) || $this->path_has_symlink($path)) {
            return false;
        }

        if (!file_exists($path)) {
            return true;
        }

        if (is_file($path)) {
            wp_delete_file($path);

            return !file_exists($path);
        }

        $items = @scandir($path);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if (in_array($item, ['.', '..'])) {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (!$this->recursive_delete($itemPath)) {
                return false;
            }
        }

        $wpFilesystem = $this->get_wp_filesystem();

        if ($wpFilesystem && $wpFilesystem->rmdir($path, false)) {
            if (!file_exists($path)) {
                return true;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Fallback only when WP_Filesystem directory removal is unavailable.
        return @rmdir($path);
    }

    public function is_writable(string $path): bool
    {
        $wpFilesystem = $this->get_wp_filesystem();

        if ($wpFilesystem) {
            return $wpFilesystem->is_writable($path);
        }

        if (file_exists($path)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Fallback only when WP_Filesystem is unavailable.
            return is_writable($path);
        }

        $parent = dirname($path);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Fallback only when WP_Filesystem is unavailable.
        return $parent !== '' && is_dir($parent) && is_writable($parent);
    }

    private function get_wp_filesystem()
    {
        global $wp_filesystem;

        if ($wp_filesystem) {
            return $wp_filesystem;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        if (!@WP_Filesystem()) {
            return null;
        }

        return $wp_filesystem;
    }

    public function get_relative_path(string $fullPath): string
    {
        $realBase = realpath($this->basePath);
        $realPath = realpath($fullPath);

        if (false === $realBase || false === $realPath) {
            return '';
        }

        return ltrim(substr($realPath, strlen($realBase)), DIRECTORY_SEPARATOR);
    }

    public function get_disk_stats(): array
    {
        $targetPath = $this->basePath;

        if (!file_exists($targetPath)) {
            $targetPath = dirname($targetPath);
        }

        $total = @disk_total_space($targetPath);
        $free = @disk_free_space($targetPath);

        if ($total === false || $free === false || $total <= 0) {
            return [
                'available' => false,
                'total_bytes' => 0,
                'free_bytes' => 0,
                'used_bytes' => 0,
                'free_percentage' => 0,
            ];
        }

        $used = max(0, (int) $total - (int) $free);

        return [
            'available' => true,
            'total_bytes' => (int) $total,
            'free_bytes' => (int) $free,
            'used_bytes' => $used,
            'free_percentage' => round(((int) $free / (int) $total) * 100, 2),
        ];
    }

    private function path_has_symlink(string $path): bool
    {
        $realBase = realpath($this->basePath);

        if (false === $realBase) {
            return true;
        }

        $normalizedBase = wp_normalize_path($realBase);
        $normalizedPath = wp_normalize_path($path);

        if ($normalizedPath === $normalizedBase) {
            return is_link($realBase);
        }

        if (strpos(trailingslashit($normalizedPath), trailingslashit($normalizedBase)) !== 0) {
            return true;
        }

        $relative = ltrim(substr($normalizedPath, strlen($normalizedBase)), '/');
        if ($relative === '') {
            return false;
        }

        $current = $realBase;
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '') {
                continue;
            }

            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                return true;
            }
        }

        return false;
    }
}
