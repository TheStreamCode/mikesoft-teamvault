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
            'class-mstv-download',
            'class-mstv-preview',
            'class-mstv-export',
            'class-mstv-hooks',
            'class-mstv-rest-controller',
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

        if (is_admin()) {
            $this->services['admin'] = new MSTV_Admin($this->services['settings']);
            $this->services['assets'] = new MSTV_Assets($this->services['settings']);
        }
    }

    public function maybe_upgrade(): void
    {
        MSTV_Activator::maybe_upgrade();
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
        $download = new MSTV_Download($storage, $filesRepo, $auth, $logger);
        $preview = new MSTV_Preview($storage, $filesRepo, $auth, $settings);

        $controller = new MSTV_REST_Controller(
            $settings,
            $auth,
            $storage,
            $validator,
            $folderRepo,
            $filesRepo,
            $download,
            $preview,
            $logger
        );
        $controller->register_routes();
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
