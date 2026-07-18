<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin tables require direct queries.

class MSTV_Repository_Groups
{
    private string $groupsTable;
    private string $membersTable;

    public function __construct()
    {
        global $wpdb;
        $prefix = $wpdb->get_blog_prefix(get_current_blog_id());
        $this->groupsTable = $prefix . 'mstv_groups';
        $this->membersTable = $prefix . 'mstv_group_members';
    }

    public function find(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->groupsTable} WHERE id = %d", $id)
        );
    }

    public function find_all_groups(): array
    {
        global $wpdb;

        return $wpdb->get_results("SELECT * FROM {$this->groupsTable} ORDER BY name ASC");
    }

    public function exists_by_slug(string $slug, int $excludeId = 0): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->groupsTable} WHERE slug = %s AND id <> %d",
                $slug,
                $excludeId
            )
        );

        return $count > 0;
    }

    public function create(array $data): int
    {
        global $wpdb;

        $inserted = $wpdb->insert($this->groupsTable, [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? '',
            'created_by' => $data['created_by'],
        ], ['%s', '%s', '%s', '%d']);

        return $inserted === false ? 0 : (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        return $wpdb->update($this->groupsTable, $data, ['id' => $id]) !== false;
    }

    public function delete(int $id, bool $manageTransaction = true): bool
    {
        global $wpdb;

        if ($manageTransaction && $wpdb->query('START TRANSACTION') === false) {
            return false;
        }

        if ($wpdb->delete($this->membersTable, ['group_id' => $id], ['%d']) === false
            || $wpdb->delete($this->groupsTable, ['id' => $id], ['%d']) === false) {
            if ($manageTransaction) {
                $wpdb->query('ROLLBACK');
            }
            return false;
        }

        if ($manageTransaction && $wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        return true;
    }

    public function find_members(int $groupId): array
    {
        global $wpdb;

        return array_map(
            'intval',
            $wpdb->get_col(
                $wpdb->prepare("SELECT user_id FROM {$this->membersTable} WHERE group_id = %d", $groupId)
            )
        );
    }

    public function find_groups_for_user(int $userId): array
    {
        global $wpdb;

        if ($userId <= 0) {
            return [];
        }

        return array_map(
            'intval',
            $wpdb->get_col(
                $wpdb->prepare("SELECT group_id FROM {$this->membersTable} WHERE user_id = %d", $userId)
            )
        );
    }

    public function add_member(int $groupId, int $userId): bool
    {
        global $wpdb;

        // INSERT IGNORE avoids duplicate-key errors thanks to the unique (group_id, user_id) index.
        return $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$this->membersTable} (group_id, user_id, created_at) VALUES (%d, %d, NOW())",
                $groupId,
                $userId
            )
        ) !== false;
    }

    public function remove_member(int $groupId, int $userId): bool
    {
        global $wpdb;

        return $wpdb->delete($this->membersTable, ['group_id' => $groupId, 'user_id' => $userId], ['%d', '%d']) !== false;
    }

    public function set_members(int $groupId, array $userIds, bool $manageTransaction = true): bool
    {
        global $wpdb;

        $userIds = array_values(array_unique(array_filter(array_map('absint', $userIds))));

        if ($manageTransaction && $wpdb->query('START TRANSACTION') === false) {
            return false;
        }

        if ($wpdb->delete($this->membersTable, ['group_id' => $groupId], ['%d']) === false) {
            if ($manageTransaction) {
                $wpdb->query('ROLLBACK');
            }
            return false;
        }

        foreach ($userIds as $userId) {
            if (!$this->add_member($groupId, $userId)) {
                if ($manageTransaction) {
                    $wpdb->query('ROLLBACK');
                }
                return false;
            }
        }

        if ($manageTransaction && $wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        return true;
    }

    public function delete_memberships_for_user(int $userId): void
    {
        global $wpdb;

        $wpdb->delete($this->membersTable, ['user_id' => $userId], ['%d']);
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
