<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin tables require direct queries.

class MSTV_Repository_Logs
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->get_blog_prefix(get_current_blog_id()) . 'mstv_logs';
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
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return $ip;
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

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
