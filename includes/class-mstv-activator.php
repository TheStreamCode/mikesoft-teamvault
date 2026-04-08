<?php

defined('ABSPATH') || exit;

class MSTV_Activator
{
    private const VERSION_OPTION = 'mstv_plugin_version';
    private const STORAGE_MARKER_FILE = '.mstv-storage';

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
        update_option(self::VERSION_OPTION, MSTV_VERSION);
    }

    private static function create_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $blog_prefix = $wpdb->get_blog_prefix(get_current_blog_id());

        $folders_table = $blog_prefix . 'mstv_folders';
        $files_table = $blog_prefix . 'mstv_files';
        $logs_table = $blog_prefix . 'mstv_logs';

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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        foreach ($sql as $query) {
            dbDelta($query);
        }

        self::normalize_logs_table($logs_table);
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

        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('manage_private_documents');
        }
    }

    private static function set_default_options(): void
    {
        $defaults = [
            'mstv_interface_language' => 'en',
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
            self::VERSION_OPTION => MSTV_VERSION,
        ];

        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value);
            }
        }
    }
}
