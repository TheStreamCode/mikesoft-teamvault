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
        $items = $this->get_paginated_folder_items($folderId, $orderBy, $order, $perPage, $offset);

        return $this->build_paginated_result($items, $page, $perPage, $totalItems, $offset);
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

            $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
            $page = $this->normalize_page($page, $totalPages);
            $offset = ($page - 1) * $perPage;
            $items = $this->get_paginated_search_items(null, $searchTerm, $orderBy, $order, $perPage, $offset);

            return $this->build_paginated_result($items, $page, $perPage, $totalItems, $offset);
        }

        $totalItems = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s)",
                $folderId,
                $searchTerm,
                $searchTerm
            )
        );

        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
        $page = $this->normalize_page($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $items = $this->get_paginated_search_items($folderId, $searchTerm, $orderBy, $order, $perPage, $offset);

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

    private function get_paginated_folder_items(?int $folderId, string $orderBy, string $order, int $perPage, int $offset): array
    {
        global $wpdb;

        if (null === $folderId) {
            if ('created_at' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY created_at DESC LIMIT %d OFFSET %d", $perPage, $offset))
                    : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY created_at ASC LIMIT %d OFFSET %d", $perPage, $offset));
            }

            if ('file_size' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY file_size DESC LIMIT %d OFFSET %d", $perPage, $offset))
                    : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY file_size ASC LIMIT %d OFFSET %d", $perPage, $offset));
            }

            if ('extension' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY extension DESC LIMIT %d OFFSET %d", $perPage, $offset))
                    : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY extension ASC LIMIT %d OFFSET %d", $perPage, $offset));
            }

            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY display_name DESC LIMIT %d OFFSET %d", $perPage, $offset))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id IS NULL ORDER BY display_name ASC LIMIT %d OFFSET %d", $perPage, $offset));
        }

        if ('created_at' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $folderId, $perPage, $offset))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY created_at ASC LIMIT %d OFFSET %d", $folderId, $perPage, $offset));
        }

        if ('file_size' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY file_size DESC LIMIT %d OFFSET %d", $folderId, $perPage, $offset))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY file_size ASC LIMIT %d OFFSET %d", $folderId, $perPage, $offset));
        }

        if ('extension' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY extension DESC LIMIT %d OFFSET %d", $folderId, $perPage, $offset))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY extension ASC LIMIT %d OFFSET %d", $folderId, $perPage, $offset));
        }

        return 'DESC' === $order
            ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY display_name DESC LIMIT %d OFFSET %d", $folderId, $perPage, $offset))
            : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d ORDER BY display_name ASC LIMIT %d OFFSET %d", $folderId, $perPage, $offset));
    }

    private function get_paginated_search_items(?int $folderId, string $searchTerm, string $orderBy, string $order, int $perPage, int $offset): array
    {
        global $wpdb;

        if (null === $folderId) {
            if ('created_at' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset))
                    : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY created_at ASC LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset));
            }

            if ('file_size' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY file_size DESC LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset))
                    : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY file_size ASC LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset));
            }

            if ('extension' === $orderBy) {
                return 'DESC' === $order
                    ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY extension DESC LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset))
                    : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY extension ASC LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset));
            }

            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY display_name DESC LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE display_name LIKE %s ORDER BY display_name ASC LIMIT %d OFFSET %d", $searchTerm, $perPage, $offset));
        }

        if ('created_at' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY created_at DESC LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY created_at ASC LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset));
        }

        if ('file_size' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY file_size DESC LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY file_size ASC LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset));
        }

        if ('extension' === $orderBy) {
            return 'DESC' === $order
                ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY extension DESC LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset))
                : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY extension ASC LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset));
        }

        return 'DESC' === $order
            ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY display_name DESC LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset))
            : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d AND (display_name LIKE %s OR original_name LIKE %s) ORDER BY display_name ASC LIMIT %d OFFSET %d", $folderId, $searchTerm, $searchTerm, $perPage, $offset));
    }

}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
