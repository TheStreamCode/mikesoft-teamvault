<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin tables require direct queries; orderClause built from sanitized whitelist values.

class MSTV_Repository_Files
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->get_blog_prefix(get_current_blog_id()) . 'mstv_files';
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

        $orderClause = $this->build_order_clause($orderBy, $order);
        $where = null === $folderId ? 'folder_id IS NULL' : $wpdb->prepare('folder_id = %d', $folderId);

        return $wpdb->get_results("SELECT * FROM {$this->table} WHERE {$where} {$orderClause}");
    }

    public function find_by_folder_paginated(
        ?int $folderId,
        string $orderBy = 'display_name',
        string $order = 'ASC',
        int $page = 1,
        int $perPage = 50
    ): array {
        global $wpdb;

        $orderBy = $this->sanitize_order_by($orderBy);
        $order = $this->sanitize_order_direction($order);
        $page = $this->sanitize_page($page);
        $perPage = $this->sanitize_per_page($perPage);

        $totalItems = $this->count_by_folder($folderId);
        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
        $page = $this->normalize_page($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $orderClause = $this->build_order_clause($orderBy, $order);
        $where = null === $folderId ? 'folder_id IS NULL' : $wpdb->prepare('folder_id = %d', $folderId);
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE {$where} {$orderClause} LIMIT %d OFFSET %d", $perPage, $offset));

        return $this->build_paginated_result($items, $page, $perPage, $totalItems, $offset);
    }

    public function find_all(string $orderBy = 'display_name', string $order = 'ASC'): array
    {
        global $wpdb;

        $orderClause = $this->build_order_clause($orderBy, $order);

        return $wpdb->get_results("SELECT * FROM {$this->table} {$orderClause}");
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

    public function search_paginated(
        string $query,
        ?int $folderId = null,
        string $orderBy = 'display_name',
        string $order = 'ASC',
        int $page = 1,
        int $perPage = 50
    ): array {
        global $wpdb;

        $searchTerm = '%' . $wpdb->esc_like($query) . '%';
        $orderBy = $this->sanitize_order_by($orderBy);
        $order = $this->sanitize_order_direction($order);
        $page = $this->sanitize_page($page);
        $perPage = $this->sanitize_per_page($perPage);

        if (null === $folderId) {
            $totalItems = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE display_name LIKE %s",
                    $searchTerm
                )
            );
        } else {
            $totalItems = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s)",
                    $folderId,
                    $searchTerm,
                    $searchTerm
                )
            );
        }

        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
        $page = $this->normalize_page($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $orderClause = $this->build_order_clause($orderBy, $order);

        if (null === $folderId) {
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s {$orderClause} LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset));
        } else {
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) {$orderClause} LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset));
        }

        return $this->build_paginated_result($items, $page, $perPage, $totalItems, $offset);
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

    public function update_relative_paths_for_folder_rename(string $oldPrefix, string $newPrefix): int
    {
        global $wpdb;

        $oldPrefix = trim($oldPrefix, '/\\');
        $newPrefix = trim($newPrefix, '/\\');

        if ($oldPrefix === '' || $oldPrefix === $newPrefix) {
            return 0;
        }

        $search = $oldPrefix . '/%';
        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, relative_path FROM {$this->table} WHERE relative_path LIKE %s",
                $search
            )
        );

        $updated = 0;

        foreach ($records as $record) {
            $currentPath = (string) $record->relative_path;
            if (!str_starts_with($currentPath, $oldPrefix . '/')) {
                continue;
            }

            $newPath = $newPrefix . substr($currentPath, strlen($oldPrefix));
            $result = $wpdb->update(
                $this->table,
                ['relative_path' => $newPath],
                ['id' => (int) $record->id]
            );

            if ($result !== false) {
                $updated++;
            }
        }

        return $updated;
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

    private function build_order_clause(string $orderBy, string $order): string
    {
        $orderBy = $this->sanitize_order_by($orderBy);
        $order = $this->sanitize_order_direction($order);

        return "ORDER BY {$orderBy} {$order}";
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

    private function sanitize_page(int $page): int
    {
        return max(1, $page);
    }

    private function sanitize_per_page(int $perPage): int
    {
        return max(1, min(200, $perPage));
    }

    private function normalize_page(int $page, int $totalPages): int
    {
        if ($totalPages < 1) {
            return 1;
        }

        return min(max(1, $page), $totalPages);
    }

    private function build_paginated_result(array $items, int $page, int $perPage, int $totalItems, int $offset): array
    {
        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
        $fromItem = $totalItems > 0 ? $offset + 1 : 0;
        $toItem = $totalItems > 0 ? min($offset + count($items), $totalItems) : 0;

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $totalPages > 0 && $page < $totalPages,
                'from_item' => $fromItem,
                'to_item' => $toItem,
            ],
        ];
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
