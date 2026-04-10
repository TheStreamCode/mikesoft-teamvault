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
        add_action('admin_post_mstv_export_selection', [$this, 'handle_export_selection']);    }

    public function add_menu(): void
    {
        if (!$this->current_user_can_manage()) {
            return;
        }

        $menu_icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>'
        );

        add_menu_page(
            __('TeamVault', 'mikesoft-teamvault'),
            __('TeamVault', 'mikesoft-teamvault'),
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
            MSTV_Capabilities::CAP_MANAGE,
            'mikesoft-teamvault-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'mikesoft-teamvault',
            __('Activity Log', 'mikesoft-teamvault'),
            __('Activity Log', 'mikesoft-teamvault'),
            MSTV_Capabilities::CAP_MANAGE,
            'mikesoft-teamvault-logs',
            [$this, 'render_logs_page']
        );
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
        if (!$this->current_user_can_manage()) {
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

    public function render_logs_page(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $mstv_repo = new MSTV_Repository_Logs();
        $mstv_allowed_per_page = [25, 50, 100, 200];
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
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $nonce = isset($_POST['mstv_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['mstv_settings_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mstv_settings_nonce')) {
            wp_die(esc_html__('Invalid security token.', 'mikesoft-teamvault'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_validate_boolean sanitizes the value per WP plugin review requirement.
        $whitelistEnabled = wp_validate_boolean(wp_unslash($_POST['mstv_use_user_whitelist'] ?? false));
        $rawAllowedUsers = [];
        if (isset($_POST['mstv_allowed_users']) && is_array($_POST['mstv_allowed_users'])) {
            $rawAllowedUsers = $_POST['mstv_allowed_users'];
        } elseif (isset($_POST['pdm_allowed_users']) && is_array($_POST['pdm_allowed_users'])) {
            $rawAllowedUsers = $_POST['pdm_allowed_users'];
        }

        $userIds = array_map('absint', wp_unslash($rawAllowedUsers));

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

        set_transient('mstv_settings_saved_' . get_current_user_id(), true, MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=mikesoft-teamvault-settings'));
        exit;
    }

    public function handle_cleanup_orphans(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mikesoft-teamvault'));
        }

        $nonce = isset($_POST['mstv_cleanup_orphans_nonce']) ? sanitize_text_field(wp_unslash($_POST['mstv_cleanup_orphans_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mstv_cleanup_orphans')) {
            wp_die(esc_html__('Invalid security token.', 'mikesoft-teamvault'));
        }

        $deletedCount = $this->cleanup_orphaned_files();

        set_transient('mstv_cleanup_orphans_' . get_current_user_id(), [
            'deleted_count' => $deletedCount,
        ], MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=mikesoft-teamvault-settings'));
        exit;
    }

    public function handle_reindex_storage(): void
    {
        if (!$this->current_user_can_manage()) {
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
        $export = new MSTV_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth']);
        $export->export_all();
    }

    public function handle_export_folder(): void
    {
        $this->guard_stream_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in guard_stream_request().
        $folderId = isset($_REQUEST['folder_id']) ? absint(wp_unslash($_REQUEST['folder_id'])) : 0;
        $services = $this->build_files_services();
        $export = new MSTV_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth']);
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
        $export = new MSTV_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth']);
        $export->export_selection($folderIds);
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

        return [
            'auth' => $auth,
            'storage' => $storage,
            'files_repo' => $filesRepo,
            'folder_repo' => $folderRepo,
            'download' => new MSTV_Download($storage, $filesRepo, $auth, $logger),
            'preview' => new MSTV_Preview($storage, $filesRepo, $auth, $this->settings),
        ];
    }

    private function count_orphaned_files(): int
    {
        $services = $this->build_files_services();
        $filesystem = $services['storage']->get_filesystem();
        $count = 0;

        foreach ($services['files_repo']->find_all() as $file) {
            if (!$filesystem->is_file((string) $file->relative_path)) {
                $count++;
            }
        }

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
}
