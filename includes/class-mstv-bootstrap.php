<?php

defined('ABSPATH') || exit;

final class MSTV_Bootstrap
{
    private static $instance = null;

    private $services = [];

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    public function init(): void
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies(): void
    {
        $includes = [
            'class-mstv-helpers',
            'class-mstv-i18n',
            'class-mstv-activator',
            'class-mstv-deactivator',
            'class-mstv-capabilities',
            'class-mstv-settings',
            'class-mstv-filesystem',
            'class-mstv-validator',
            'class-mstv-storage',
            'class-mstv-auth',
            'class-mstv-repository-folders',
            'class-mstv-repository-files',
            'class-mstv-repository-logs',
            'class-mstv-repository-groups',
            'class-mstv-repository-permissions',
            'class-mstv-permissions',
            'class-mstv-quota',
            'class-mstv-notifications',
            'class-mstv-download',
            'class-mstv-preview',
            'class-mstv-export',
            'class-mstv-hooks',
            'class-mstv-rest-controller',
            'class-mstv-rest-governance',
            'class-mstv-admin',
            'class-mstv-assets',
            'class-mstv-logger',
        ];

        foreach ($includes as $files) {
            require_once MSTV_PLUGIN_DIR . 'includes/' . $files . '.php';
        }
    }

    private function init_hooks(): void
    {
        register_activation_hook(MSTV_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(MSTV_PLUGIN_FILE, [$this, 'deactivate']);

        add_action('init', [$this, 'maybe_upgrade'], 1);
        add_action('init', [$this, 'init_services'], 5);
        add_action('rest_api_init', [$this, 'init_rest_api']);
        add_action('wp_initialize_site', [$this, 'initialize_site'], 10, 1);
        add_action('deleted_user', [$this, 'on_deleted_user'], 10, 1);
    }

    /**
     * Remove governance data tied to a WordPress user that has been deleted:
     * group memberships, per-user permission rules and per-user quota.
     */
    public function on_deleted_user($userId): void
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            return;
        }

        (new MSTV_Repository_Groups())->delete_memberships_for_user($userId);
        (new MSTV_Repository_Permissions())->delete_for_principal('user', $userId);

        $quotas = get_option('mstv_quotas', []);
        $key = 'user:' . $userId;

        if (is_array($quotas) && isset($quotas[$key])) {
            unset($quotas[$key]);
            update_option('mstv_quotas', $quotas);
        }
    }

    public function activate(bool $networkWide = false): void
    {
        MSTV_Activator::activate($networkWide);
    }

    public function deactivate(): void
    {
        MSTV_Deactivator::deactivate();
    }

    public function init_services(): void
    {
        if (!isset($this->services['settings'])) {
            $this->services['settings'] = new MSTV_Settings();
        }

        if (!isset($this->services['i18n'])) {
            $this->services['i18n'] = new MSTV_I18n();
            $this->services['i18n']->init();
        }

        if (!isset($this->services['logger'])) {
            $this->services['logger'] = new MSTV_Logger(null, $this->services['settings']);
        }

        // Record completed exports in the activity log (export is bulk download).
        add_action(MSTV_Hooks::EXPORT_COMPLETED, [$this, 'log_export_completed'], 10, 3);

        if (!isset($this->services['notifications'])) {
            $this->services['notifications'] = new MSTV_Notifications(new MSTV_Repository_Groups());
            $this->services['notifications']->register();
        }

        if (is_admin()) {
            $this->services['admin'] = new MSTV_Admin($this->services['settings']);
            $this->services['assets'] = new MSTV_Assets($this->services['settings']);
        }
    }

    public function maybe_upgrade(): void
    {
        MSTV_Activator::maybe_upgrade();
    }

    public function log_export_completed($folderId, $zipPath, $filesCount): void
    {
        $settings = $this->services['settings'] ?? new MSTV_Settings();
        $logger = new MSTV_Logger(new MSTV_Repository_Logs(), $settings);
        $logger->log('export', 'folder', $folderId ? (int) $folderId : null, [
            'files' => (int) $filesCount,
        ]);
    }

    public function initialize_site(\WP_Site $newSite): void
    {
        if (empty($newSite->blog_id)) {
            return;
        }

        MSTV_Activator::initialize_site((int) $newSite->blog_id);
    }

    public function init_rest_api(): void
    {
        $settings = $this->service('settings');

        if (!$settings instanceof MSTV_Settings) {
            $settings = new MSTV_Settings();
            $this->services['settings'] = $settings;
        }

        $auth = new MSTV_Auth($settings);
        $storage = new MSTV_Storage($settings);
        $storage->ensure_storage_directory();
        $validator = new MSTV_Validator($settings);
        $folderRepo = new MSTV_Repository_Folders();
        $filesRepo = new MSTV_Repository_Files();
        $logRepo = new MSTV_Repository_Logs();
        $logger = new MSTV_Logger($logRepo, $settings);
        $groupsRepo = new MSTV_Repository_Groups();
        $permissionsRepo = new MSTV_Repository_Permissions();
        $permissions = new MSTV_Permissions($folderRepo, $groupsRepo, $permissionsRepo, $settings);
        $quota = new MSTV_Quota($settings, $filesRepo, $groupsRepo);
        $download = new MSTV_Download($storage, $filesRepo, $auth, $logger, $permissions);
        $preview = new MSTV_Preview($storage, $filesRepo, $auth, $settings, $permissions, $logger);

        $controller = new MSTV_REST_Controller(
            $settings,
            $auth,
            $storage,
            $validator,
            $folderRepo,
            $filesRepo,
            $download,
            $preview,
            $logger,
            $permissions,
            $quota
        );
        $controller->register_routes();

        $governance = new MSTV_REST_Governance_Controller(
            $auth,
            $groupsRepo,
            $permissionsRepo,
            $folderRepo,
            $permissions,
            $quota
        );
        $governance->register_routes();
    }

    public function service(string $name)
    {
        return $this->services[$name] ?? null;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
