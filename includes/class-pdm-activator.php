<?php

defined('ABSPATH') || exit;

class PDM_Activator
{
    private const VERSION_OPTION = 'pdm_plugin_version';

    public static function activate(): void
    {
        self::run_setup();

        flush_rewrite_rules();
    }

    public static function maybe_upgrade(): void
    {
        $installedVersion = get_option(self::VERSION_OPTION, '0.0.0');

        if (version_compare((string) $installedVersion, PDM_VERSION, '>=')) {
            return;
        }

        self::run_setup();
        self::sync_user_whitelist_capabilities();
    }

    private static function sync_user_whitelist_capabilities(): void
    {
        $useWhitelist = get_option('pdm_use_user_whitelist', false);
        
        if (!$useWhitelist) {
            return;
        }

        $userIds = get_option('pdm_allowed_users', []);
        
        if (!is_array($userIds) || empty($userIds)) {
            return;
        }

        foreach ($userIds as $userId) {
            $user = get_user_by('id', $userId);
            if ($user && !$user->has_cap(PDM_Capabilities::CAP_MANAGE)) {
                $user->add_cap(PDM_Capabilities::CAP_MANAGE);
                update_user_meta($userId, 'pdm_granted_capability', true);
            }
        }
    }

    private static function run_setup(): void
    {
        self::create_tables();
        self::create_storage_directory();
        self::register_capabilities();
        self::set_default_options();
        update_option(self::VERSION_OPTION, PDM_VERSION);
    }

    private static function create_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $blog_prefix = $wpdb->get_blog_prefix(get_current_blog_id());

        $folders_table = $blog_prefix . 'pdm_folders';
        $files_table = $blog_prefix . 'pdm_files';
        $logs_table = $blog_prefix . 'pdm_logs';

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
            target_type ENUM('folder', 'files') NOT NULL,
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
    }

    private static function create_storage_directory(): void
    {
        $uploadDir = wp_upload_dir();
        $basePath = $uploadDir['basedir'];
        $storagePath = $basePath . '/private-documents';

        if (!file_exists($storagePath)) {
            wp_mkdir_p($storagePath);
        }

        self::create_protection_files($storagePath);
    }

    private static function create_protection_files(string $path): void
    {
        $htaccess = $path . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Private Document Manager - Access Denied\n";
            $content .= "Order deny,allow\n";
            $content .= "Deny from all\n";
            $content .= "<IfModule mod_rewrite.c>\n";
            $content .= "RewriteEngine On\n";
            $content .= "RewriteRule .* - [F]\n";
            $content .= "</IfModule>\n";
            @file_put_contents($htaccess, $content);
        }

        $webconfig = $path . '/web.config';
        if (!file_exists($webconfig)) {
            $content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            $content .= "<configuration>\n";
            $content .= "  <system.webServer>\n";
            $content .= "    <handlers>\n";
            $content .= "      <clear />\n";
            $content .= "    </handlers>\n";
            $content .= "    <httpProtocol>\n";
            $content .= "      <customHeaders>\n";
            $content .= "        <add name=\"X-Content-Type-Options\" value=\"nosniff\" />\n";
            $content .= "      </customHeaders>\n";
            $content .= "    </httpProtocol>\n";
            $content .= "  </system.webServer>\n";
            $content .= "</configuration>";
            @file_put_contents($webconfig, $content);
        }

        $index = $path . '/index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php // Silence is golden");
        }
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
            'pdm_interface_language' => 'en',
            'pdm_storage_path' => '',
            'pdm_allowed_extensions' => implode(',', [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
                'zip', 'rar', '7z',
                'txt', 'csv', 'rtf',
                'mp3', 'wav', 'ogg',
                'mp4', 'avi', 'mov', 'mkv',
            ]),
            'pdm_max_file_size' => 50 * 1024 * 1024,
            'pdm_log_enabled' => true,
            'pdm_pdf_preview_enabled' => true,
            'pdm_remove_data_on_uninstall' => false,
            self::VERSION_OPTION => PDM_VERSION,
        ];

        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value);
            }
        }
    }
}
