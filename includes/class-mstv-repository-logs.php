<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin tables require direct queries; filtered queries are built from a strict column allowlist with %d/%s placeholders and passed through $wpdb->prepare() with bound params.

class MSTV_Repository_Logs
{
    private string $table;
    private string $filesTable;

    private const ALLOWED_ACTIONS = ['upload', 'download', 'preview', 'delete', 'rename', 'move', 'create', 'export'];
    private const ACCESS_ACTIONS = ['preview', 'download'];

    public function __construct()
    {
        global $wpdb;
        $prefix = $wpdb->get_blog_prefix(get_current_blog_id());
        $this->table = $prefix . 'mstv_logs';
        $this->filesTable = $prefix . 'mstv_files';
    }

    public function create(array $data): int
    {
        global $wpdb;

        $targetType = MSTV_Logger::normalize_target_type((string) ($data['target_type'] ?? 'file'));

        $wpdb->insert($this->table, [
            'user_id' => $data['user_id'],
            'action' => $data['action'],
            'target_type' => $targetType,
            'target_id' => $data['target_id'] ?? null,
            'context' => isset($data['context']) ? json_encode($data['context']) : null,
            'ip_address' => $data['ip_address'] ?? $this->get_client_ip(),
            'user_agent' => $data['user_agent'] ?? $this->get_user_agent(),
        ], ['%d', '%s', '%s', '%d', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public function find_by_user(int $userId, int $limit = 50): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $userId,
                $limit
            )
        );
    }

    public function find_by_target(string $targetType, int $targetId, int $limit = 50): array
    {
        global $wpdb;

        $targetType = MSTV_Logger::normalize_target_type($targetType);

        if ($targetType === 'file') {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE target_type IN (%s, %s) AND target_id = %d ORDER BY created_at DESC LIMIT %d",
                    'file',
                    'files',
                    $targetId,
                    $limit
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE target_type = %s AND target_id = %d ORDER BY created_at DESC LIMIT %d",
                $targetType,
                $targetId,
                $limit
            )
        );
    }

    public function find_recent(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, u.user_login FROM {$this->table} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID ORDER BY l.created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    public function find_recent_paginated(int $page = 1, int $perPage = 50): array
    {
        global $wpdb;

        $page = $this->sanitize_page($page);
        $perPage = $this->sanitize_per_page($perPage);
        $totalItems = $this->get_count();
        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
        $page = $this->normalize_page($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, u.user_login FROM {$this->table} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID ORDER BY l.created_at DESC LIMIT %d OFFSET %d",
                $perPage,
                $offset
            )
        );

        return $this->build_paginated_result($items, $page, $perPage, $totalItems, $offset);
    }

    public function get_count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    /**
     * Filtered, paginated log query for the audit/report screens and CSV export.
     * Filters: date_from, date_to, user_id, action, folder_id, file_type (extension).
     */
    public function find_filtered(array $filters, int $page = 1, int $perPage = 50): array
    {
        global $wpdb;

        $page = $this->sanitize_page($page);
        $perPage = $this->sanitize_per_page($perPage);

        [$where, $params, $join] = $this->build_filter_clause($filters);

        $countSql = "SELECT COUNT(*) FROM {$this->table} l {$join} {$where}";
        $totalItems = (int) $wpdb->get_var($params ? $wpdb->prepare($countSql, ...$params) : $countSql);

        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
        $page = $this->normalize_page($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT l.*, u.user_login FROM {$this->table} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID {$join} {$where} ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
        $items = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($params, [$perPage, $offset])));

        return $this->build_paginated_result($items, $page, $perPage, $totalItems, $offset);
    }

    /**
     * Aggregate "who viewed/downloaded what" over preview+download events.
     *
     * @param string $groupBy one of 'user', 'file', 'folder'
     * @return object[] rows with group key, label fields, event count and last access time
     */
    public function aggregate_access(string $groupBy, array $filters): array
    {
        global $wpdb;

        $filters['__access_only'] = true;
        [$where, $params, $join] = $this->build_filter_clause($filters);

        if ($groupBy === 'folder') {
            $sql = "SELECT f.folder_id AS group_id, COUNT(*) AS events, MAX(l.created_at) AS last_access
                    FROM {$this->table} l
                    INNER JOIN {$this->filesTable} f ON l.target_id = f.id AND l.target_type IN ('file','files')
                    {$where}
                    GROUP BY f.folder_id ORDER BY events DESC";
        } elseif ($groupBy === 'file') {
            $sql = "SELECT l.target_id AS group_id, MAX(f.display_name) AS label, COUNT(*) AS events, MAX(l.created_at) AS last_access
                    FROM {$this->table} l
                    INNER JOIN {$this->filesTable} f ON l.target_id = f.id AND l.target_type IN ('file','files')
                    {$where}
                    GROUP BY l.target_id ORDER BY events DESC";
        } else { // user
            $sql = "SELECT l.user_id AS group_id, MAX(u.user_login) AS label, COUNT(*) AS events, MAX(l.created_at) AS last_access
                    FROM {$this->table} l
                    LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                    {$join}
                    {$where}
                    GROUP BY l.user_id ORDER BY events DESC";
        }

        return $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql);
    }

    /**
     * Build a WHERE clause from a sanitized filter set.
     *
     * @return array{0:string,1:array,2:string} [whereSql, params, joinSql]
     */
    private function build_filter_clause(array $filters): array
    {
        $conditions = [];
        $params = [];
        $needsFilesJoin = false;

        if (!empty($filters['__access_only'])) {
            $placeholders = implode(',', array_fill(0, count(self::ACCESS_ACTIONS), '%s'));
            $conditions[] = "l.action IN ($placeholders)";
            array_push($params, ...self::ACCESS_ACTIONS);
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'l.created_at >= %s';
            $params[] = $this->normalize_datetime((string) $filters['date_from'], false);
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'l.created_at <= %s';
            $params[] = $this->normalize_datetime((string) $filters['date_to'], true);
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = 'l.user_id = %d';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['action']) && in_array($filters['action'], self::ALLOWED_ACTIONS, true)) {
            $conditions[] = 'l.action = %s';
            $params[] = (string) $filters['action'];
        }

        if (isset($filters['folder_id']) && $filters['folder_id'] !== '' && $filters['folder_id'] !== null) {
            $needsFilesJoin = true;
            $conditions[] = 'f.folder_id = %d';
            $params[] = (int) $filters['folder_id'];
        }

        if (!empty($filters['file_type'])) {
            $needsFilesJoin = true;
            $conditions[] = 'f.extension = %s';
            $params[] = strtolower((string) $filters['file_type']);
        }

        $join = $needsFilesJoin
            ? "INNER JOIN {$this->filesTable} f ON l.target_id = f.id AND l.target_type IN ('file','files')"
            : '';

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $params, $join];
    }

    private function normalize_datetime(string $value, bool $endOfDay): string
    {
        $value = trim($value);

        // Date-only input (YYYY-MM-DD): expand to full-day bounds.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }

        return $value;
    }

    public function delete_old(int $daysOld = 90): int
    {
        global $wpdb;

        $date = gmdate('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE created_at < %s",
                $date
            )
        );
    }

    private function get_client_ip(): string
    {
        return !empty($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
    }

    private function get_user_agent(): string
    {
        return isset($_SERVER['HTTP_USER_AGENT']) 
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) 
            : '';
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

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
