<?php

defined('ABSPATH') || exit;

class MSTV_Admin
{
    private MSTV_Settings $settings;

    public function __construct(MSTV_Settings $settings)
    {
        $this->settings = $settings;
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_mstv_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_mstv_cleanup_orphans', [$this, 'handle_cleanup_orphans']);
        add_action('admin_post_mstv_reindex_storage', [$this, 'handle_reindex_storage']);
        add_action('admin_post_mstv_download_file', [$this, 'handle_download_file']);
        add_action('admin_post_mstv_preview_file', [$this, 'handle_preview_file']);
        add_action('admin_post_mstv_export_all', [$this, 'handle_export_all']);
        add_action('admin_post_mstv_export_folder', [$this, 'handle_export_folder']);
        add_action('admin_post_mstv_export_selection', [$this, 'handle_export_selection']);
        add_action('admin_post_mstv_export_audit_csv', [$this, 'handle_export_audit_csv']);
        add_action('admin_notices', [$this, 'render_storage_security_notice']);
        add_action('wp_ajax_mstv_dismiss_storage_notice', [$this, 'handle_dismiss_storage_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_menu_icon_style']);
        add_filter('plugin_row_meta', [$this, 'add_plugin_row_meta'], 10, 2);
    }

    /**
     * Add a discreet Sponsor link to the plugin row on the Plugins screen.
     *
     * @param array  $links Existing row meta links.
     * @param string $file  Plugin basename for the current row.
     * @return array
     */
    public function add_plugin_row_meta($links, $file): array
    {
        $links = is_array($links) ? $links : [];

        if (defined('MSTV_PLUGIN_BASENAME') && $file === MSTV_PLUGIN_BASENAME) {
            $links[] = '<a href="https://github.com/sponsors/TheStreamCode" target="_blank" rel="noopener noreferrer">'
                . esc_html__('Sponsor', 'mikesoft-teamvault')
                . ' &hearts;</a>';
        }

        return $links;
    }

    public function render_storage_security_notice(): void
    {
        if (!$this->is_teamvault_settings_page() || !$this->current_user_can_admin() || !$this->settings->is_storage_path_inside_uploads()) {
            return;
        }

        if (get_user_meta(get_current_user_id(), 'mstv_notice_storage_dismissed', true)) {
            return;
        }

        echo '<div class="notice notice-warning is-dismissible mstv-storage-security-notice"><p>';
        echo esc_html__(
            'TeamVault stores private files under WordPress uploads. Confirm your web server blocks direct access to the private-documents directory, especially on Nginx.',
            'mikesoft-teamvault'
        );
        echo '</p></div>';
    }

    public function handle_dismiss_storage_notice(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die('', '', ['response' => 403]);
        }

        check_ajax_referer('mstv_dismiss_storage_notice');
        update_user_meta(get_current_user_id(), 'mstv_notice_storage_dismissed', '1');
        wp_die();
    }

    private function is_teamvault_settings_page(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page check used only to decide whether to render a notice.
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return $page === 'mikesoft-teamvault-settings';
    }

    public function add_menu(): void
    {
        if (!$this->current_user_can_manage()) {
            return;
        }

        $menu_icon = 'data:image/svg+xml;base64,' . base64_encode($this->menu_icon_svg());

        $brand_name = $this->settings->get_brand_name();

        add_menu_page(
            $brand_name,
            $brand_name,
            MSTV_Capabilities::CAP_MANAGE,
            'mikesoft-teamvault',
            [$this, 'render_file_manager_page'],
            $menu_icon,
            30
        );

        add_submenu_page(
            'mikesoft-teamvault',
            __('File Manager', 'mikesoft-teamvault'),
            __('File Manager', 'mikesoft-teamvault'),
            MSTV_Capabilities::CAP_MANAGE,
            'mikesoft-teamvault',
            [$this, 'render_file_manager_page']
        );

        add_submenu_page(
            'mikesoft-teamvault',
            __('Settings', 'mikesoft-teamvault'),
            __('Settings', 'mikesoft-teamvault'),
            'manage_options',
            'mikesoft-teamvault-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'mikesoft-teamvault',
            __('Groups', 'mikesoft-teamvault'),
            __('Groups', 'mikesoft-teamvault'),
            'manage_options',
            'mikesoft-teamvault-groups',
            [$this, 'render_groups_page']
        );

        add_submenu_page(
            'mikesoft-teamvault',
            __('Quotas', 'mikesoft-teamvault'),
            __('Quotas', 'mikesoft-teamvault'),
            'manage_options',
            'mikesoft-teamvault-quotas',
            [$this, 'render_quotas_page']
        );

        add_submenu_page(
            'mikesoft-teamvault',
            __('Notifications', 'mikesoft-teamvault'),
            __('Notifications', 'mikesoft-teamvault'),
            'manage_options',
            'mikesoft-teamvault-notifications',
            [$this, 'render_notifications_page']
        );

        add_submenu_page(
            'mikesoft-teamvault',
            __('Reports', 'mikesoft-teamvault'),
            __('Reports', 'mikesoft-teamvault'),
            'manage_options',
            'mikesoft-teamvault-reports',
            [$this, 'render_reports_page']
        );

        add_submenu_page(
            'mikesoft-teamvault',
            __('Activity Log', 'mikesoft-teamvault'),
            __('Activity Log', 'mikesoft-teamvault'),
            'manage_options',
            'mikesoft-teamvault-logs',
            [$this, 'render_logs_page']
        );
    }

    /**
     * Monochrome folder glyph for the admin menu, sized to the 20x20 canvas
     * WordPress uses for native menu icons. Filled (not stroked) with the default
     * admin icon color so it matches core menus even before CSS recoloring applies.
     */
    private function menu_icon_svg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad">'
            . '<path d="M2 5.5A1.5 1.5 0 0 1 3.5 4h3.29a1.5 1.5 0 0 1 1.06.44L9.2 5.6h7.3A1.5 1.5 0 0 1 18 7.1v7.4a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 2 14.5v-9z"/>'
            . '</svg>';
    }

    /**
     * Recolor the custom SVG menu icon like a native Dashicon.
     *
     * WordPress renders a data-URI menu icon as a static background image that never
     * changes color on hover/current. Painting the glyph as a CSS mask filled with
     * `currentColor` instead lets it inherit the per-state color WordPress already
     * assigns to `.wp-menu-image::before`, so it adapts to every admin color scheme.
     */
    public function enqueue_menu_icon_style(): void
    {
        $mask = 'data:image/svg+xml;base64,' . base64_encode($this->menu_icon_svg());

        // Fill the whole 36x34 menu-image box and center the 20x20 mask inside it, so the
        // glyph lines up with the label exactly like a native Dashicon. Zero margin/padding
        // avoids stacking on top of WordPress's own .wp-menu-image::before padding.
        $css = '#adminmenu #toplevel_page_mikesoft-teamvault .wp-menu-image{background-image:none !important;}'
            . '#adminmenu #toplevel_page_mikesoft-teamvault .wp-menu-image::before{'
            . 'content:"";display:block;width:36px;height:34px;margin:0;padding:0;'
            . 'background-color:currentColor;'
            . '-webkit-mask:url("' . $mask . '") no-repeat center;'
            . 'mask:url("' . $mask . '") no-repeat center;'
            . '-webkit-mask-size:20px 20px;mask-size:20px 20px;}';

        wp_add_inline_style('admin-menu', $css);
    }

    public function register_settings(): void
    {
        $this->settings->register_settings();
    }

    public function render_file_manager_page(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $settings = $this->settings;
        include MSTV_PLUGIN_DIR . 'admin/views/file-manager-page.php';
    }

    public function render_settings_page(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $settings = $this->settings;
        $orphaned_files_count = $this->count_orphaned_files();
        $cleanup_result = get_transient('mstv_cleanup_orphans_' . get_current_user_id());
        $reindex_result = get_transient('mstv_reindex_storage_' . get_current_user_id());

        if ($cleanup_result !== false) {
            delete_transient('mstv_cleanup_orphans_' . get_current_user_id());
        }

        if ($reindex_result !== false) {
            delete_transient('mstv_reindex_storage_' . get_current_user_id());
        }

        include MSTV_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_groups_page(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $settings = $this->settings;
        include MSTV_PLUGIN_DIR . 'admin/views/groups-page.php';
    }

    public function render_quotas_page(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $settings = $this->settings;
        include MSTV_PLUGIN_DIR . 'admin/views/quotas-page.php';
    }

    public function render_reports_page(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $settings = $this->settings;
        include MSTV_PLUGIN_DIR . 'admin/views/reports-page.php';
    }

    public function render_notifications_page(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $settings = $this->settings;
        include MSTV_PLUGIN_DIR . 'admin/views/notifications-page.php';
    }

    public function render_logs_page(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $mstv_repo = new MSTV_Repository_Logs();
        $mstv_allowed_per_page = [25, 50, 100, 200];
        $mstv_current_page = filter_input(INPUT_GET, 'paged', FILTER_VALIDATE_INT);
        $mstv_selected_per_page = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);

        $mstv_current_page = $mstv_current_page ? max(1, $mstv_current_page) : 1;
        $mstv_selected_per_page = $mstv_selected_per_page ?: 50;

        if (!in_array($mstv_selected_per_page, $mstv_allowed_per_page, true)) {
            $mstv_selected_per_page = 50;
        }

        $mstv_logs_page = $mstv_repo->find_recent_paginated($mstv_current_page, $mstv_selected_per_page);
        $mstv_logs = $mstv_logs_page['items'];
        $mstv_pagination = $mstv_logs_page['pagination'];

        include MSTV_PLUGIN_DIR . 'admin/views/logs-page.php';
    }

    public function handle_save_settings(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $nonce = isset($_POST['mstv_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['mstv_settings_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mstv_settings_nonce')) {
            wp_die(esc_html__('Invalid security token.', 'mikesoft-teamvault'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_validate_boolean sanitizes the value per WP plugin review requirement.
        $whitelistEnabled = wp_validate_boolean(wp_unslash($_POST['mstv_use_user_whitelist'] ?? false));
        $userIds = [];
        if (isset($_POST['mstv_allowed_users'])) {
            $userIds = array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['mstv_allowed_users']))));
        } elseif (isset($_POST['pdm_allowed_users'])) {
            $userIds = array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['pdm_allowed_users']))));
        }

        $interfaceLanguage = isset($_POST['mstv_interface_language'])
            ? sanitize_text_field(wp_unslash($_POST['mstv_interface_language']))
            : 'en';

        $rawAllowedExtensions = isset($_POST['mstv_allowed_extensions'])
            ? sanitize_text_field(wp_unslash($_POST['mstv_allowed_extensions']))
            : '';

        $allowedExtensions = is_string($rawAllowedExtensions)
            ? $this->settings->sanitize_extensions($rawAllowedExtensions)
            : '';

        $maxFileSize = isset($_POST['mstv_max_file_size'])
            ? absint(wp_unslash($_POST['mstv_max_file_size']))
            : 52428800;

        if ($whitelistEnabled) {
            $whitelistCheck = $this->settings->validate_whitelist_selection($userIds, get_current_user_id());

            if ($whitelistCheck instanceof \WP_Error) {
                set_transient('mstv_settings_error_' . get_current_user_id(), $whitelistCheck->get_error_message(), MINUTE_IN_SECONDS);
                wp_safe_redirect(admin_url('admin.php?page=mikesoft-teamvault-settings'));
                exit;
            }
        }

        $this->settings->sync_capabilities_on_whitelist_change($userIds, $whitelistEnabled);

        update_option('mstv_interface_language', $interfaceLanguage);
        update_option('mstv_allowed_extensions', $allowedExtensions);
        update_option('mstv_max_file_size', $maxFileSize);
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_validate_boolean sanitizes the value per WP plugin review requirement.
        update_option('mstv_pdf_preview_enabled', wp_validate_boolean(wp_unslash($_POST['mstv_pdf_preview_enabled'] ?? false)));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_validate_boolean sanitizes the value per WP plugin review requirement.
        update_option('mstv_log_enabled', wp_validate_boolean(wp_unslash($_POST['mstv_log_enabled'] ?? false)));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_validate_boolean sanitizes the value per WP plugin review requirement.
        update_option('mstv_remove_data_on_uninstall', wp_validate_boolean(wp_unslash($_POST['mstv_remove_data_on_uninstall'] ?? false)));

        // White-label branding.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_validate_boolean sanitizes the value.
        update_option('mstv_white_label_enabled', wp_validate_boolean(wp_unslash($_POST['mstv_white_label_enabled'] ?? false)));
        $brandName = isset($_POST['mstv_brand_name']) ? sanitize_text_field(wp_unslash($_POST['mstv_brand_name'])) : 'TeamVault';
        update_option('mstv_brand_name', $brandName !== '' ? $brandName : 'TeamVault');
        update_option('mstv_brand_logo_url', isset($_POST['mstv_brand_logo_url']) ? esc_url_raw(wp_unslash($_POST['mstv_brand_logo_url'])) : '');
        $accent = isset($_POST['mstv_brand_accent']) ? sanitize_hex_color(wp_unslash($_POST['mstv_brand_accent'])) : '';
        update_option('mstv_brand_accent', $accent ?: '');

        set_transient('mstv_settings_saved_' . get_current_user_id(), true, MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=mikesoft-teamvault-settings'));
        exit;
    }

    public function handle_cleanup_orphans(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $nonce = isset($_POST['mstv_cleanup_orphans_nonce']) ? sanitize_text_field(wp_unslash($_POST['mstv_cleanup_orphans_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mstv_cleanup_orphans')) {
            wp_die(esc_html__('Invalid security token.', 'mikesoft-teamvault'));
        }

        $deletedCount = $this->cleanup_orphaned_files();

        delete_transient('mstv_orphan_count_' . get_current_blog_id());

        set_transient('mstv_cleanup_orphans_' . get_current_user_id(), [
            'deleted_count' => $deletedCount,
        ], MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=mikesoft-teamvault-settings'));
        exit;
    }

    public function handle_reindex_storage(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $nonce = isset($_POST['mstv_reindex_storage_nonce']) ? sanitize_text_field(wp_unslash($_POST['mstv_reindex_storage_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mstv_reindex_storage')) {
            wp_die(esc_html__('Invalid security token.', 'mikesoft-teamvault'));
        }

        $result = $this->reindex_storage_records();

        set_transient('mstv_reindex_storage_' . get_current_user_id(), $result, MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=mikesoft-teamvault-settings'));
        exit;
    }

    public function handle_download_file(): void
    {
        $this->guard_stream_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in guard_stream_request().
        $fileId = isset($_REQUEST['file_id']) ? absint(wp_unslash($_REQUEST['file_id'])) : 0;
        if ($fileId <= 0) {
            wp_die(esc_html__('File not found.', 'mikesoft-teamvault'), esc_html__('Error', 'mikesoft-teamvault'), ['response' => 404]);
        }

        $services = $this->build_files_services();
        $services['download']->serve($fileId);
    }

    public function handle_preview_file(): void
    {
        $this->guard_stream_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in guard_stream_request().
        $fileId = isset($_REQUEST['file_id']) ? absint(wp_unslash($_REQUEST['file_id'])) : 0;
        if ($fileId <= 0) {
            wp_die(esc_html__('File not found.', 'mikesoft-teamvault'), esc_html__('Error', 'mikesoft-teamvault'), ['response' => 404]);
        }

        $services = $this->build_files_services();
        $services['preview']->serve($fileId);
    }

    public function handle_export_all(): void
    {
        $this->guard_stream_request();

        $services = $this->build_files_services();
        $export = new MSTV_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth'], $services['permissions']);
        $export->export_all();
    }

    public function handle_export_folder(): void
    {
        $this->guard_stream_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in guard_stream_request().
        $folderId = isset($_REQUEST['folder_id']) ? absint(wp_unslash($_REQUEST['folder_id'])) : 0;
        $services = $this->build_files_services();
        $export = new MSTV_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth'], $services['permissions']);
        $export->export_folder($folderId > 0 ? $folderId : null);
    }

    public function handle_export_selection(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $nonce = isset($_POST['mstv_export_selection_nonce']) ? sanitize_text_field(wp_unslash($_POST['mstv_export_selection_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mstv_export_selection')) {
            wp_die(esc_html__('Invalid security token.', 'mikesoft-teamvault'));
        }

        $folderIds = [];

        if (isset($_POST['folder_ids']) && is_array($_POST['folder_ids'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each element is sanitized with sanitize_text_field and then cast to int with absint.
            $folderIds = array_map('absint', array_map('sanitize_text_field', wp_unslash($_POST['folder_ids'])));
        }

        $services = $this->build_files_services();
        $export = new MSTV_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth'], $services['permissions']);
        $export->export_selection($folderIds);
    }

    public function handle_export_audit_csv(): void
    {
        if (!$this->current_user_can_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'), esc_html__('Error', 'mikesoft-teamvault'), ['response' => 403]);
        }

        $nonce = isset($_GET['mstv_audit_csv_nonce']) ? sanitize_text_field(wp_unslash($_GET['mstv_audit_csv_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mstv_audit_csv')) {
            wp_die(esc_html__('Invalid security token.', 'mikesoft-teamvault'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified above; reading read-only filter values.
        $filters = [];
        foreach (['date_from', 'date_to', 'action', 'file_type'] as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $filters[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }
        if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
            $filters['user_id'] = absint(wp_unslash($_GET['user_id']));
        }
        if (isset($_GET['folder_id']) && $_GET['folder_id'] !== '') {
            $filters['folder_id'] = absint(wp_unslash($_GET['folder_id']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $repo = new MSTV_Repository_Logs();

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="teamvault-audit-' . gmdate('Ymd-His') . '.csv"');
        header('X-Content-Type-Options: nosniff');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV directly to the client output buffer.
        $out = fopen('php://output', 'w');
        fputcsv($out, ['date', 'user_login', 'user_id', 'action', 'target_type', 'target_id', 'name', 'ip_address']);

        $page = 1;
        do {
            $result = $repo->find_filtered($filters, $page, 200);
            foreach ($result['items'] as $log) {
                fputcsv($out, $this->build_audit_csv_row($log));
            }
            $page++;
        } while ($page <= ($result['pagination']['total_pages'] ?? 0));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the output stream.
        fclose($out);
        exit;
    }

    private function build_audit_csv_row(object $log): array
    {
        $context = json_decode($log->context ?? '{}', true);
        $name = '';
        if (is_array($context)) {
            $name = $context['filename'] ?? $context['name'] ?? $context['display_name'] ?? '';
        }

        return [
            $log->created_at,
            $log->user_login ?? '',
            (int) $log->user_id,
            $log->action,
            $log->target_type,
            $log->target_id !== null ? (int) $log->target_id : '',
            $name,
            $log->ip_address ?? '',
        ];
    }

    private function guard_stream_request(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'), esc_html__('Error', 'mikesoft-teamvault'), ['response' => 403]);
        }

        check_admin_referer('mstv_stream_action', 'mstv_stream_nonce');
    }

    private function build_files_services(): array
    {
        $auth = new MSTV_Auth($this->settings);
        $storage = new MSTV_Storage($this->settings);
        $storage->ensure_storage_directory();
        $filesRepo = new MSTV_Repository_Files();
        $folderRepo = new MSTV_Repository_Folders();
        $logRepo = new MSTV_Repository_Logs();
        $logger = new MSTV_Logger($logRepo, $this->settings);
        $permissions = new MSTV_Permissions(
            $folderRepo,
            new MSTV_Repository_Groups(),
            new MSTV_Repository_Permissions(),
            $this->settings
        );

        return [
            'auth' => $auth,
            'storage' => $storage,
            'files_repo' => $filesRepo,
            'folder_repo' => $folderRepo,
            'permissions' => $permissions,
            'download' => new MSTV_Download($storage, $filesRepo, $auth, $logger, $permissions),
            'preview' => new MSTV_Preview($storage, $filesRepo, $auth, $this->settings, $permissions, $logger),
        ];
    }

    private function count_orphaned_files(): int
    {
        $cacheKey = 'mstv_orphan_count_' . get_current_blog_id();
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return (int) $cached;
        }

        $services = $this->build_files_services();
        $filesystem = $services['storage']->get_filesystem();
        $count = 0;

        foreach ($services['files_repo']->find_all() as $file) {
            if (!$filesystem->is_file((string) $file->relative_path)) {
                $count++;
            }
        }

        set_transient($cacheKey, $count, 5 * MINUTE_IN_SECONDS);

        return $count;
    }

    private function cleanup_orphaned_files(): int
    {
        $services = $this->build_files_services();
        $filesystem = $services['storage']->get_filesystem();
        $deletedCount = 0;

        foreach ($services['files_repo']->find_all() as $file) {
            if ($filesystem->is_file((string) $file->relative_path)) {
                continue;
            }

            if ($services['files_repo']->delete((int) $file->id)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    private function reindex_storage_records(): array
    {
        $services = $this->build_files_services();

        return $services['storage']->reindex_storage_records(
            $services['folder_repo'],
            $services['files_repo'],
            get_current_user_id()
        );
    }

    private function current_user_can_manage(): bool
    {
        $auth = new MSTV_Auth($this->settings);

        return $auth->can_access();
    }

    private function current_user_can_admin(): bool
    {
        $auth = new MSTV_Auth($this->settings);

        return $auth->can_admin();
    }
}
