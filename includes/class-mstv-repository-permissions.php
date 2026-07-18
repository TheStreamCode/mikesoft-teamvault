<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin tables require direct queries.

class MSTV_Repository_Permissions
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->get_blog_prefix(get_current_blog_id()) . 'mstv_folder_permissions';
    }

    /**
     * All grant rows for a folder (0 = virtual root).
     *
     * @return object[] rows with principal_type, principal_id, action
     */
    public function find_rules_for_folder(int $folderId): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE folder_id = %d", $folderId)
        );
    }

    public function has_any_rule(int $folderId): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE folder_id = %d", $folderId)
        ) > 0;
    }

    /**
     * Whether any permission rule exists in the whole vault (governance is in use).
     */
    public function has_any_rules(): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}") > 0;
    }

    /**
     * IDs of the real folders (excluding the virtual root, 0) that carry at least one
     * explicit rule, in a single query. Used to badge governed folders in the browser.
     *
     * @return int[]
     */
    public function folders_with_rules(): array
    {
        global $wpdb;

        $ids = $wpdb->get_col("SELECT DISTINCT folder_id FROM {$this->table} WHERE folder_id > 0");

        return array_map('intval', $ids);
    }

    /**
     * Replace in one shot all rules of a folder with the provided grant set.
     *
     * @param array $rules each: ['principal_type' => 'user'|'group', 'principal_id' => int, 'actions' => string[]]
     */
    public function set_rules(int $folderId, array $rules, int $createdBy): bool
    {
        global $wpdb;

        if ($wpdb->query('START TRANSACTION') === false) {
            return false;
        }

        if ($wpdb->delete($this->table, ['folder_id' => $folderId], ['%d']) === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        foreach ($rules as $rule) {
            $principalType = ($rule['principal_type'] ?? '') === 'group' ? 'group' : 'user';
            $principalId = absint($rule['principal_id'] ?? 0);
            $actions = is_array($rule['actions'] ?? null) ? $rule['actions'] : [];

            if ($principalId <= 0) {
                continue;
            }

            foreach ($actions as $action) {
                if (!in_array($action, MSTV_Permissions::ACTIONS, true)) {
                    continue;
                }

                $inserted = $wpdb->insert($this->table, [
                    'folder_id' => $folderId,
                    'principal_type' => $principalType,
                    'principal_id' => $principalId,
                    'action' => $action,
                    'created_by' => $createdBy,
                ], ['%d', '%s', '%d', '%s', '%d']);

                if ($inserted === false) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }
        }

        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        return true;
    }

    public function delete_for_folder(int $folderId): bool
    {
        global $wpdb;

        return $wpdb->delete($this->table, ['folder_id' => $folderId], ['%d']) !== false;
    }

    public function delete_for_principal(string $principalType, int $principalId): bool
    {
        global $wpdb;

        $principalType = $principalType === 'group' ? 'group' : 'user';
        return $wpdb->delete($this->table, ['principal_type' => $principalType, 'principal_id' => $principalId], ['%s', '%d']) !== false;
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
