<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin tables require direct queries.

class PDM_Repository_Files
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->get_blog_prefix(get_current_blog_id()) . 'pdm_files';
    }

    public function find(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );
    }

    public function find_by_folder(?int $folderId, string $orderBy = 'display_name', string $order = 'ASC'): array
    {
        global $wpdb;

        $orderBy = $this->sanitize_order_by($orderBy);
        $order = $this->sanitize_order_direction($order);

        if (null === $folderId) {
            if ('created_at' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY created_at DESC")
                    : $wpdb->get_results("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY created_at ASC");
            }

            if ('file_size' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY file_size DESC")
                    : $wpdb->get_results("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY file_size ASC");
            }

            if ('extension' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY extension DESC")
                    : $wpdb->get_results("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY extension ASC");
            }

            return 'DESC' === $order
                ? $wpdb->get_results("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY display_name DESC")
                : $wpdb->get_results("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY display_name ASC");
        }

        if ('created_at' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY created_at DESC", $folderId))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY created_at ASC", $folderId));
        }

        if ('file_size' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY file_size DESC", $folderId))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY file_size ASC", $folderId));
        }

        if ('extension' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY extension DESC", $folderId))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY extension ASC", $folderId));
        }

        return 'DESC' === $order
            ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY display_name DESC", $folderId))
            : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY display_name ASC", $folderId));
    }

    public function find_all(string $orderBy = 'display_name', string $order = 'ASC'): array
    {
        global $wpdb;

        $orderBy = $this->sanitize_order_by($orderBy);
        $order = $this->sanitize_order_direction($order);

        if ('created_at' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY created_at DESC")
                : $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY created_at ASC");
        }

        if ('file_size' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY file_size DESC")
                : $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY file_size ASC");
        }

        if ('extension' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY extension DESC")
                : $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY extension ASC");
        }

        return 'DESC' === $order
            ? $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY display_name DESC")
            : $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY display_name ASC");
    }

    public function search(string $query, ?int $folderId = null): array
    {
        global $wpdb;

        $searchTerm = '%' . $wpdb->esc_like($query) . '%';

        if (null === $folderId) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY display_name ASC",
                    $searchTerm
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY display_name ASC",
                $folderId,
                $searchTerm,
                $searchTerm
            )
        );
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert($this->table, [
            'folder_id' => $data['folder_id'] ?? null,
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'display_name' => $data['display_name'],
            'relative_path' => $data['relative_path'],
            'extension' => $data['extension'],
            'mime_type' => $data['mime_type'],
            'file_size' => $data['file_size'],
            'checksum' => $data['checksum'],
            'created_by' => $data['created_by'],
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d']);

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);

        return $result !== false;
    }

    public function count_by_folder(?int $folderId): int
    {
        global $wpdb;

        if (null === $folderId) {
            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table} WHERE folder_id IS NULL"
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE folder_id = %d",
                $folderId
            )
        );
    }

    public function move_to_folder(int $fileId, ?int $newFolderId, string $newRelativePath): bool
    {
        return $this->update($fileId, [
            'folder_id' => $newFolderId,
            'relative_path' => $newRelativePath,
        ]);
    }

    public function rename(int $fileId, string $newDisplayName): bool
    {
        return $this->update($fileId, [
            'display_name' => $newDisplayName,
        ]);
    }

    public function find_by_checksum(string $checksum): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE checksum = %s",
                $checksum
            )
        );
    }

    public function get_total_size(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(file_size), 0) FROM {$this->table}"
        );
    }

    public function get_count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table}"
        );
    }

    private function sanitize_order_by(string $orderBy): string
    {
        $allowed = ['display_name', 'created_at', 'file_size', 'extension'];

        return in_array($orderBy, $allowed, true) ? $orderBy : 'display_name';
    }

    private function sanitize_order_direction(string $order): string
    {
        return strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    }

}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
