<?php

defined('ABSPATH') || exit;

class PDM_Admin
{
    private PDM_Settings $settings;

    public function __construct(PDM_Settings $settings)
    {
        $this->settings = $settings;
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_pdm_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_pdm_cleanup_orphans', [$this, 'handle_cleanup_orphans']);
        add_action('admin_post_pdm_reindex_storage', [$this, 'handle_reindex_storage']);
        add_action('admin_post_pdm_download_file', [$this, 'handle_download_file']);
        add_action('admin_post_pdm_preview_file', [$this, 'handle_preview_file']);
        add_action('admin_post_pdm_export_all', [$this, 'handle_export_all']);
        add_action('admin_post_pdm_export_folder', [$this, 'handle_export_folder']);
        add_action('admin_post_pdm_export_selection', [$this, 'handle_export_selection']);
    }

    public function add_menu(): void
    {
        if (!$this->current_user_can_manage()) {
            return;
        }

        $menu_icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>'
        );

        add_menu_page(
            __('Private Documents', 'private-document-manager'),
            __('Private Documents', 'private-document-manager'),
            PDM_Capabilities::CAP_MANAGE,
            'private-document-manager',
            [$this, 'render_file_manager_page'],
            $menu_icon,
            30
        );

        add_submenu_page(
            'private-document-manager',
            __('File Manager', 'private-document-manager'),
            __('File Manager', 'private-document-manager'),
            PDM_Capabilities::CAP_MANAGE,
            'private-document-manager',
            [$this, 'render_file_manager_page']
        );

        add_submenu_page(
            'private-document-manager',
            __('Settings', 'private-document-manager'),
            __('Settings', 'private-document-manager'),
            PDM_Capabilities::CAP_MANAGE,
            'private-document-manager-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'private-document-manager',
            __('Activity Log', 'private-document-manager'),
            __('Activity Log', 'private-document-manager'),
            PDM_Capabilities::CAP_MANAGE,
            'private-document-manager-logs',
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
            wp_die(esc_html__('You do not have permission to access this page.', 'private-document-manager'));
        }

        include PDM_PLUGIN_DIR . 'admin/views/file-manager-page.php';
    }

    public function render_settings_page(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'private-document-manager'));
        }

        $orphaned_files_count = $this->count_orphaned_files();
        $cleanup_result = get_transient('pdm_cleanup_orphans_' . get_current_user_id());
        $reindex_result = get_transient('pdm_reindex_storage_' . get_current_user_id());

        if ($cleanup_result !== false) {
            delete_transient('pdm_cleanup_orphans_' . get_current_user_id());
        }

        if ($reindex_result !== false) {
            delete_transient('pdm_reindex_storage_' . get_current_user_id());
        }

        include PDM_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_logs_page(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'private-document-manager'));
        }

        include PDM_PLUGIN_DIR . 'admin/views/logs-page.php';
    }

    public function handle_save_settings(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'private-document-manager'));
        }

        $nonce = isset($_POST['pdm_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['pdm_settings_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'pdm_settings_nonce')) {
            wp_die(esc_html__('Invalid security token.', 'private-document-manager'));
        }

        $whitelistEnabled = !empty($_POST['pdm_use_user_whitelist']);
        $userIds = isset($_POST['pdm_allowed_users']) && is_array($_POST['pdm_allowed_users'])
            ? array_map('absint', wp_unslash($_POST['pdm_allowed_users']))
            : [];

        $interfaceLanguage = isset($_POST['pdm_interface_language'])
            ? sanitize_text_field(wp_unslash($_POST['pdm_interface_language']))
            : 'en';

        $rawAllowedExtensions = isset($_POST['pdm_allowed_extensions'])
            ? sanitize_text_field(wp_unslash($_POST['pdm_allowed_extensions']))
            : '';

        $allowedExtensions = is_string($rawAllowedExtensions)
            ? $this->settings->sanitize_extensions($rawAllowedExtensions)
            : '';

        $maxFileSize = isset($_POST['pdm_max_file_size'])
            ? absint(wp_unslash($_POST['pdm_max_file_size']))
            : 52428800;

        if ($whitelistEnabled) {
            $whitelistCheck = $this->settings->validate_whitelist_selection($userIds, get_current_user_id());

            if ($whitelistCheck instanceof \WP_Error) {
                set_transient('pdm_settings_error_' . get_current_user_id(), $whitelistCheck->get_error_message(), MINUTE_IN_SECONDS);
                wp_safe_redirect(admin_url('admin.php?page=private-document-manager-settings'));
                exit;
            }
        }

        $this->settings->sync_capabilities_on_whitelist_change($userIds, $whitelistEnabled);

        update_option('pdm_interface_language', $interfaceLanguage);
        update_option('pdm_allowed_extensions', $allowedExtensions);
        update_option('pdm_max_file_size', $maxFileSize);
        update_option('pdm_pdf_preview_enabled', !empty($_POST['pdm_pdf_preview_enabled']));
        update_option('pdm_log_enabled', !empty($_POST['pdm_log_enabled']));
        update_option('pdm_remove_data_on_uninstall', !empty($_POST['pdm_remove_data_on_uninstall']));

        set_transient('pdm_settings_saved_' . get_current_user_id(), true, MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=private-document-manager-settings'));
        exit;
    }

    public function handle_cleanup_orphans(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'private-document-manager'));
        }

        $nonce = isset($_POST['pdm_cleanup_orphans_nonce']) ? sanitize_text_field(wp_unslash($_POST['pdm_cleanup_orphans_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'pdm_cleanup_orphans')) {
            wp_die(esc_html__('Invalid security token.', 'private-document-manager'));
        }

        $deletedCount = $this->cleanup_orphaned_files();

        set_transient('pdm_cleanup_orphans_' . get_current_user_id(), [
            'deleted_count' => $deletedCount,
        ], MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=private-document-manager-settings'));
        exit;
    }

    public function handle_reindex_storage(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'private-document-manager'));
        }

        $nonce = isset($_POST['pdm_reindex_storage_nonce']) ? sanitize_text_field(wp_unslash($_POST['pdm_reindex_storage_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'pdm_reindex_storage')) {
            wp_die(esc_html__('Invalid security token.', 'private-document-manager'));
        }

        $result = $this->reindex_storage_records();

        set_transient('pdm_reindex_storage_' . get_current_user_id(), $result, MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=private-document-manager-settings'));
        exit;
    }

    public function handle_download_file(): void
    {
        $this->guard_stream_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in guard_stream_request().
        $fileId = isset($_REQUEST['file_id']) ? absint(wp_unslash($_REQUEST['file_id'])) : 0;
        if ($fileId <= 0) {
            wp_die(esc_html__('File not found.', 'private-document-manager'), esc_html__('Error', 'private-document-manager'), ['response' => 404]);
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
            wp_die(esc_html__('File not found.', 'private-document-manager'), esc_html__('Error', 'private-document-manager'), ['response' => 404]);
        }

        $services = $this->build_files_services();
        $services['preview']->serve($fileId);
    }

    public function handle_export_all(): void
    {
        $this->guard_stream_request();

        $services = $this->build_files_services();
        $export = new PDM_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth']);
        $export->export_all();
    }

    public function handle_export_folder(): void
    {
        $this->guard_stream_request();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in guard_stream_request().
        $folderId = isset($_REQUEST['folder_id']) ? absint(wp_unslash($_REQUEST['folder_id'])) : 0;
        $services = $this->build_files_services();
        $export = new PDM_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth']);
        $export->export_folder($folderId > 0 ? $folderId : null);
    }

    public function handle_export_selection(): void
    {
        $this->guard_stream_request();

        $folderIds = [];

        $rawFolderIds = filter_input(INPUT_POST, 'folder_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

        if (is_array($rawFolderIds)) {
            $folderIds = array_map(
                'absint',
                array_map('sanitize_text_field', $rawFolderIds)
            );
        }

        $services = $this->build_files_services();
        $export = new PDM_Export($services['storage'], $services['files_repo'], $services['folder_repo'], $services['auth']);
        $export->export_selection($folderIds);
    }

    private function guard_stream_request(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'private-document-manager'), esc_html__('Error', 'private-document-manager'), ['response' => 403]);
        }

        check_admin_referer('pdm_stream_action', 'pdm_stream_nonce');
    }

    private function build_files_services(): array
    {
        $auth = new PDM_Auth($this->settings);
        $storage = new PDM_Storage($this->settings);
        $storage->ensure_storage_directory();
        $filesRepo = new PDM_Repository_Files();
        $folderRepo = new PDM_Repository_Folders();
        $logRepo = new PDM_Repository_Logs();
        $logger = new PDM_Logger($logRepo);

        return [
            'auth' => $auth,
            'storage' => $storage,
            'files_repo' => $filesRepo,
            'folder_repo' => $folderRepo,
            'download' => new PDM_Download($storage, $filesRepo, $auth, $logger),
            'preview' => new PDM_Preview($storage, $filesRepo, $auth),
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
        $auth = new PDM_Auth($this->settings);

        return $auth->can_access();
    }
}
