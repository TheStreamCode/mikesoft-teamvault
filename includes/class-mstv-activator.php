<?php

defined('ABSPATH') || exit;

class MSTV_Activator
{
    private const VERSION_OPTION = 'mstv_plugin_version';
    private const STORAGE_MARKER_FILE = '.mstv-storage';
    private const SCHEMA_OPTION = 'mstv_schema_migrations';
    private const MAX_DEDUPE_PASSES = 10;

    public static function activate(bool $networkWide = false): void
    {
        if ($networkWide && is_multisite()) {
            $siteIds = get_sites(['fields' => 'ids']);

            foreach ($siteIds as $siteId) {
                self::initialize_site((int) $siteId);
            }
        } else {
            self::run_setup();
        }

        flush_rewrite_rules();
    }

    public static function maybe_upgrade(): void
    {
        $installedVersion = get_option(self::VERSION_OPTION, '0.0.0');

        if (version_compare((string) $installedVersion, MSTV_VERSION, '>=')) {
            return;
        }

        self::run_setup();
    }

    public static function initialize_site(int $siteId): void
    {
        if ($siteId <= 0) {
            return;
        }

        switch_to_blog($siteId);
        self::run_setup();
        restore_current_blog();
    }

    private static function sync_user_whitelist_capabilities(): void
    {
        $settings = new MSTV_Settings();
        $settings->sync_existing_whitelist();
    }

    private static function run_setup(): void
    {
        self::create_tables();
        self::create_storage_directory();
        self::register_capabilities();
        self::set_default_options();
        self::sync_user_whitelist_capabilities();
        self::cleanup_removed_options();
        update_option(self::VERSION_OPTION, MSTV_VERSION);
    }

    /**
     * Remove options for features dropped in later versions so upgraded installs do
     * not keep orphaned rows. Safe to run repeatedly.
     */
    private static function cleanup_removed_options(): void
    {
        // White-label branding was removed in 3.2.0.
        delete_option('mstv_white_label_enabled');
        delete_option('mstv_brand_name');
        delete_option('mstv_brand_logo_url');
        delete_option('mstv_brand_accent');
    }

    private static function create_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $blog_prefix = $wpdb->get_blog_prefix(get_current_blog_id());

        $folders_table = $blog_prefix . 'mstv_folders';
        $files_table = $blog_prefix . 'mstv_files';
        $logs_table = $blog_prefix . 'mstv_logs';
        $groups_table = $blog_prefix . 'mstv_groups';
        $group_members_table = $blog_prefix . 'mstv_group_members';
        $folder_permissions_table = $blog_prefix . 'mstv_folder_permissions';

        $sql = [];

        $sql[] = "CREATE TABLE $folders_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id BIGINT(20) UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            relative_path VARCHAR(1000) NOT NULL DEFAULT '',
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_parent_id (parent_id),
            KEY idx_slug (slug(191)),
            KEY idx_created_by (created_by)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $files_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            folder_id BIGINT(20) UNSIGNED NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            relative_path VARCHAR(1000) NOT NULL DEFAULT '',
            extension VARCHAR(20) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT(20) UNSIGNED NOT NULL,
            checksum VARCHAR(64) NOT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_folder_id (folder_id),
            KEY idx_extension (extension),
            KEY idx_created_by (created_by),
            KEY idx_created_at (created_at),
            KEY idx_display_name (display_name(191))
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $logs_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id BIGINT(20) UNSIGNED NULL,
            context TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_action (action),
            KEY idx_target (target_type, target_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $groups_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            description VARCHAR(500) NOT NULL DEFAULT '',
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_slug (slug),
            KEY idx_name (name)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $group_members_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_group_user (group_id, user_id),
            KEY idx_group_id (group_id),
            KEY idx_user_id (user_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $folder_permissions_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            folder_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            principal_type VARCHAR(10) NOT NULL,
            principal_id BIGINT(20) UNSIGNED NOT NULL,
            action VARCHAR(20) NOT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rule (folder_id, principal_type, principal_id, action),
            KEY idx_folder (folder_id),
            KEY idx_principal (principal_type, principal_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        foreach ($sql as $query) {
            dbDelta($query);
        }

        self::normalize_logs_table($logs_table);

        // Unique keys are added by an explicit, option-guarded migration instead of
        // dbDelta: the constraint can only be created after existing duplicate rows
        // have been merged, and dbDelta would attempt the ALTER before that cleanup.
        self::ensure_unique_indexes($folders_table, $files_table, $folder_permissions_table);
    }

    /**
     * Whether a one-time schema migration has been applied.
     *
     * Runtime code (folder inserts, uploads) must not reference the parent_key
     * column before the migration that creates it has run.
     */
    public static function schema_migration_applied(string $key): bool
    {
        $applied = get_option(self::SCHEMA_OPTION, []);

        return is_array($applied) && !empty($applied[$key]);
    }

    private static function mark_schema_migration(string $key): void
    {
        $applied = get_option(self::SCHEMA_OPTION, []);

        if (!is_array($applied)) {
            $applied = [];
        }

        $applied[$key] = 1;
        update_option(self::SCHEMA_OPTION, $applied);
    }

    /**
     * Add the uniqueness constraints that keep one row per folder name and per
     * stored file path.
     *
     * The folders table gets a NULL-safe sibling key: MySQL treats NULL values as
     * distinct in a unique index, so UNIQUE (parent_id, name) would not protect
     * root folders. parent_key stores the parent id or 0 for the root, and
     * UNIQUE (parent_key, name) covers both cases.
     *
     * Every step is recorded in the mstv_schema_migrations option so a retried
     * activation skips work that already succeeded, and a failed step is retried
     * on the next activation/upgrade instead of leaving the schema half-applied.
     */
    private static function ensure_unique_indexes(string $foldersTable, string $filesTable, string $permissionsTable = ''): void
    {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'query')) {
            return;
        }

        if (!self::is_safe_table_name($foldersTable) || !self::is_safe_table_name($filesTable)) {
            self::log_schema_error('unexpected table name');
            return;
        }

        if ($permissionsTable !== '' && !self::is_safe_table_name($permissionsTable)) {
            $permissionsTable = '';
        }

        $parentKeyReady = self::schema_migration_applied('folder_parent_key');
        $folderUniqueReady = self::schema_migration_applied('folder_unique');
        $fileUniqueReady = self::schema_migration_applied('file_unique');

        if ($parentKeyReady && $folderUniqueReady && $fileUniqueReady) {
            return;
        }

        if (!$parentKeyReady) {
            $parentKeyReady = self::add_folder_parent_key_column($wpdb, $foldersTable);

            if ($parentKeyReady) {
                self::mark_schema_migration('folder_parent_key');
            } else {
                self::log_schema_error('folder parent_key column');
            }
        }

        if ($parentKeyReady && !$folderUniqueReady) {
            if (self::deduplicate_folders($wpdb, $foldersTable, $filesTable, $permissionsTable)
                && self::add_unique_index($wpdb, $foldersTable, 'uq_mstv_folder_parent_name', 'parent_key, name(150)')
            ) {
                self::mark_schema_migration('folder_unique');
            } else {
                self::log_schema_error('folder unique index');
            }
        }

        if (!$fileUniqueReady) {
            if (self::deduplicate_files($wpdb, $filesTable)
                && !self::file_paths_share_index_prefix($wpdb, $filesTable)
                && self::add_unique_index($wpdb, $filesTable, 'uq_mstv_file_relative_path', 'relative_path(191)')
            ) {
                self::mark_schema_migration('file_unique');
            } else {
                self::log_schema_error('file unique index');
            }
        }
    }

    private static function add_folder_parent_key_column($wpdb, string $foldersTable): bool
    {
        if (!method_exists($wpdb, 'get_results') || !method_exists($wpdb, 'query')) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated against the blog-specific plugin table before interpolation.
        $columns = $wpdb->get_results('DESCRIBE `' . $foldersTable . '`');

        if (!is_array($columns)) {
            return false;
        }

        foreach ($columns as $column) {
            if (isset($column->Field) && $column->Field === 'parent_key') {
                return true;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; explicit ALTER is required for the unique key.
        $altered = $wpdb->query(
            'ALTER TABLE `' . $foldersTable . '` ADD COLUMN parent_key BIGINT(20) UNSIGNED NOT NULL DEFAULT 0'
        );

        if ($altered === false) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; backfills the new column from the existing parent_id values.
        $wpdb->query('UPDATE `' . $foldersTable . '` SET parent_key = COALESCE(parent_id, 0)');

        return true;
    }

    /**
     * Merge duplicate sibling folders before the unique key is created.
     *
     * The lowest id survives; children and files of the duplicate are re-pointed
     * to it and the stored paths of the duplicate subtree are rewritten onto the
     * surviving path. Duplicates introduced by re-pointing are handled by the
     * next pass, so nested duplicate trees converge.
     */
    public static function deduplicate_folders($wpdb, string $foldersTable, string $filesTable, string $permissionsTable = ''): bool
    {
        if (!is_object($wpdb) || !method_exists($wpdb, 'get_results')) {
            return false;
        }

        for ($pass = 0; $pass < self::MAX_DEDUPE_PASSES; $pass++) {
            $groups = self::find_duplicate_folder_groups($wpdb, $foldersTable);

            if ($groups === null) {
                return false;
            }

            if ($groups === []) {
                return true;
            }

            foreach ($groups as $group) {
                $keepId = (int) $group['keep_id'];
                $keepPath = (string) $group['keep_path'];

                foreach ($group['duplicate_ids'] as $duplicateId) {
                    $duplicateId = (int) $duplicateId;

                    $wpdb->update(
                        $foldersTable,
                        ['parent_id' => $keepId, 'parent_key' => $keepId],
                        ['parent_id' => $duplicateId],
                        ['%d', '%d'],
                        ['%d']
                    );
                    $wpdb->update($filesTable, ['folder_id' => $keepId], ['folder_id' => $duplicateId], ['%d'], ['%d']);

                    if ($permissionsTable !== '') {
                        // Per-folder rules of the duplicate belong to the surviving
                        // folder. UPDATE IGNORE skips rules the survivor already has;
                        // the following DELETE removes exactly those duplicates so no
                        // orphan rule survives the merge.
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; values are prepared.
                        $wpdb->query(
                            $wpdb->prepare(
                                'UPDATE IGNORE `' . $permissionsTable . '` SET folder_id = %d WHERE folder_id = %d',
                                $keepId,
                                $duplicateId
                            )
                        );
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; value is prepared.
                        $wpdb->query(
                            $wpdb->prepare(
                                'DELETE FROM `' . $permissionsTable . '` WHERE folder_id = %d',
                                $duplicateId
                            )
                        );
                    }

                    $duplicatePath = (string) ($group['duplicate_paths'][$duplicateId] ?? '');

                    if ($duplicatePath !== $keepPath) {
                        self::rewrite_subtree_paths($wpdb, $foldersTable, $filesTable, $duplicatePath, $keepPath);
                    }

                    $wpdb->delete($foldersTable, ['id' => $duplicateId], ['%d']);
                }
            }
        }

        return false;
    }

    /**
     * Delete duplicate file rows that point at the same stored path, keeping the
     * lowest id. Files are not re-pointed: the surviving row already owns the
     * path, and both rows describe the same bytes on disk.
     */
    public static function deduplicate_files($wpdb, string $filesTable): bool
    {
        if (!is_object($wpdb) || !method_exists($wpdb, 'get_results')) {
            return false;
        }

        $rows = self::fetch_file_rows($wpdb, $filesTable);

        if ($rows === null) {
            return false;
        }

        foreach (self::duplicate_file_groups($rows) as $group) {
            foreach ($group['duplicate_ids'] as $duplicateId) {
                $wpdb->delete($filesTable, ['id' => (int) $duplicateId], ['%d']);
            }
        }

        return true;
    }

    /**
     * @param object[] $rows Rows with id and relative_path.
     * @return array<int,array{keep_id: int, duplicate_ids: int[]}>
     */
    public static function duplicate_file_groups(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) $row->relative_path][] = (int) $row->id;
        }

        $duplicates = [];

        foreach ($groups as $ids) {
            if (count($ids) < 2) {
                continue;
            }

            sort($ids);
            $keepId = array_shift($ids);

            $duplicates[] = [
                'keep_id' => $keepId,
                'duplicate_ids' => $ids,
            ];
        }

        return $duplicates;
    }

    /**
     * Rewrite a stored path when its folder prefix moved to the surviving path.
     */
    public static function replace_path_prefix(string $path, string $oldPrefix, string $newPrefix): string
    {
        $oldPrefix = trim($oldPrefix, '/');
        $newPrefix = trim($newPrefix, '/');

        if ($oldPrefix === '' || $oldPrefix === $newPrefix) {
            return $path;
        }

        if ($path === $oldPrefix) {
            return $newPrefix;
        }

        if (str_starts_with($path, $oldPrefix . '/')) {
            return $newPrefix . substr($path, strlen($oldPrefix));
        }

        return $path;
    }

    /**
     * Find sibling folder duplicates with the same semantics as the unique key.
     *
     * The GROUP BY runs under the column collation, so case-insensitive and
     * accent-insensitive duplicates are found exactly as the index would treat
     * them; grouping in PHP would risk merging rows the database considers
     * distinct.
     *
     * @return array<int,array{keep_id: int, keep_path: string, duplicate_ids: int[], duplicate_paths: array<int,string>}>|null
     */
    private static function find_duplicate_folder_groups($wpdb, string $foldersTable): ?array
    {
        if (!method_exists($wpdb, 'get_results') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; one-time duplicate detection before the unique key is created.
        $groups = $wpdb->get_results(
            'SELECT parent_key, name, MIN(id) AS keep_id, COUNT(*) AS total FROM `' . $foldersTable . '`'
            . ' GROUP BY parent_key, name HAVING total > 1'
        );

        if (!is_array($groups)) {
            return null;
        }

        $duplicates = [];

        foreach ($groups as $group) {
            $keepId = (int) $group->keep_id;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; reads the surviving row of a duplicate group.
            $keep = $wpdb->get_row(
                $wpdb->prepare('SELECT id, relative_path FROM `' . $foldersTable . '` WHERE id = %d', $keepId)
            );

            if (!is_object($keep)) {
                return null;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; lists the duplicate rows of one group.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, relative_path FROM `' . $foldersTable . '`'
                    . ' WHERE parent_key = %d AND name = %s AND id <> %d ORDER BY id ASC',
                    (int) $group->parent_key,
                    (string) $group->name,
                    $keepId
                )
            );

            if (!is_array($rows) || $rows === []) {
                continue;
            }

            $duplicateIds = [];
            $duplicatePaths = [];

            foreach ($rows as $row) {
                $duplicateIds[] = (int) $row->id;
                $duplicatePaths[(int) $row->id] = (string) $row->relative_path;
            }

            $duplicates[] = [
                'keep_id' => $keepId,
                'keep_path' => (string) $keep->relative_path,
                'duplicate_ids' => $duplicateIds,
                'duplicate_paths' => $duplicatePaths,
            ];
        }

        return $duplicates;
    }

    /**
     * @return object[]|null
     */
    private static function fetch_file_rows($wpdb, string $table): ?array
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; the migration reads every row once to merge duplicates.
        $rows = $wpdb->get_results('SELECT id, relative_path FROM `' . $table . '` ORDER BY id ASC');

        return is_array($rows) ? $rows : null;
    }

    private static function rewrite_subtree_paths($wpdb, string $foldersTable, string $filesTable, string $oldPrefix, string $newPrefix): void
    {
        $oldPrefix = trim($oldPrefix, '/');
        $newPrefix = trim($newPrefix, '/');

        if ($oldPrefix === '' || $oldPrefix === $newPrefix) {
            return;
        }

        foreach ([$foldersTable, $filesTable] as $table) {
            $like = (method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($oldPrefix) : $oldPrefix) . '/%';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; prefix match limits the rewrite to the duplicate subtree.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, relative_path FROM `' . $table . '` WHERE relative_path = %s OR relative_path LIKE %s',
                    $oldPrefix,
                    $like
                )
            );

            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $newPath = self::replace_path_prefix((string) $row->relative_path, $oldPrefix, $newPrefix);

                if ($newPath === (string) $row->relative_path) {
                    continue;
                }

                $wpdb->update($table, ['relative_path' => $newPath], ['id' => (int) $row->id], ['%s'], ['%d']);
            }
        }
    }

    /**
     * A unique index on relative_path uses a 191-character prefix for MySQL
     * compatibility. Distinct paths sharing that prefix cannot coexist under the
     * index, so the index is skipped (and retried on the next upgrade) rather
     * than deleting valid file rows.
     */
    private static function file_paths_share_index_prefix($wpdb, string $filesTable): bool
    {
        if (!method_exists($wpdb, 'get_var')) {
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; detects prefix collisions before creating the unique index.
        $collisions = $wpdb->get_var(
            'SELECT COUNT(*) FROM (SELECT LEFT(relative_path, 191) AS path_prefix, COUNT(DISTINCT relative_path) AS variants'
            . ' FROM `' . $filesTable . '` GROUP BY path_prefix HAVING variants > 1) AS collisions'
        );

        return (int) $collisions > 0;
    }

    private static function add_unique_index($wpdb, string $table, string $indexName, string $columns): bool
    {
        if (!method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'query')) {
            return false;
        }

        $indexName = preg_replace('/[^A-Za-z0-9_]/', '', $indexName);

        if ($indexName === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated plugin table; index presence check.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s',
                $table,
                $indexName
            )
        );

        if ((int) $existing > 0) {
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Explicit ALTER is required for the unique key; dbDelta cannot add it after the dedupe.
        $altered = $wpdb->query('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . $indexName . '` (' . $columns . ')');

        return $altered !== false;
    }

    private static function is_safe_table_name(string $table): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $table);
    }

    private static function log_schema_error(string $context): void
    {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- A failed one-time schema migration needs operator attention; no user data is logged.
        error_log('TeamVault: unable to apply the uniqueness migration (' . $context . ').');
    }

    private static function normalize_logs_table(string $logsTable): void
    {
        global $wpdb;

        $safeLogsTable = self::normalize_logs_table_name_for_upgrade(
            $logsTable,
            $wpdb->get_blog_prefix(get_current_blog_id())
        );

        if ($safeLogsTable === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Safe table name is restricted to the expected blog-specific logs table before interpolation.
        $wpdb->query('ALTER TABLE `' . $safeLogsTable . '` MODIFY target_type VARCHAR(20) NOT NULL');

        self::migrate_legacy_log_target_types($wpdb, $safeLogsTable);
    }

    public static function normalize_logs_table_name_for_upgrade(string $logsTable, string $blogPrefix): string
    {
        $expectedTable = $blogPrefix . 'mstv_logs';

        if (!preg_match('/^[A-Za-z0-9_]+$/', $expectedTable)) {
            return '';
        }

        return hash_equals($expectedTable, $logsTable) ? $expectedTable : '';
    }

    public static function migrate_legacy_log_target_types($wpdb, string $logsTable): bool
    {
        if ($logsTable === '' || !is_object($wpdb) || !method_exists($wpdb, 'update')) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration on activation; caching not applicable.
        $result = $wpdb->update(
            $logsTable,
            ['target_type' => 'file'],
            ['target_type' => 'files']
        );

        return $result !== false;
    }

    private static function create_storage_directory(): void
    {
        $uploadDir = wp_upload_dir();
        $basePath = $uploadDir['basedir'];
        $storagePath = $basePath . '/private-documents';

        if (!file_exists($storagePath)) {
            wp_mkdir_p($storagePath);
        }

        MSTV_Helpers::create_protection_files($storagePath);
    }

    private static function register_capabilities(): void
    {
        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->add_cap('manage_private_documents');
        }
    }

    private static function set_default_options(): void
    {
        $defaults = [
            'mstv_interface_language' => 'auto',
            'mstv_storage_path' => '',
            'mstv_allowed_extensions' => implode(',', [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                'zip', 'rar', '7z',
                'txt', 'csv', 'rtf',
                'mp3', 'wav', 'ogg',
                'mp4', 'avi', 'mov', 'mkv',
            ]),
            'mstv_max_file_size' => 50 * 1024 * 1024,
            'mstv_log_enabled' => true,
            'mstv_pdf_preview_enabled' => true,
            'mstv_remove_data_on_uninstall' => false,
            // Governance suite (Quotas, Notifications).
            'mstv_quotas_enabled' => false,
            'mstv_quotas' => [],
            'mstv_notify_enabled' => false,
            'mstv_notify_events' => '',
            'mstv_notify_recipients' => ['admins' => true, 'users' => [], 'groups' => []],
            'mstv_notify_folder_aware' => true,
            'mstv_folder_notifications' => [],
            self::VERSION_OPTION => MSTV_VERSION,
        ];

        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value);
            }
        }
    }
}
