<?php

defined('ABSPATH') || exit;

final class PDM_Bootstrap
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
            'class-pdm-helpers',
            'class-pdm-i18n',
            'class-pdm-activator',
            'class-pdm-deactivator',
            'class-pdm-capabilities',
            'class-pdm-settings',
            'class-pdm-filesystem',
            'class-pdm-validator',
            'class-pdm-storage',
            'class-pdm-auth',
            'class-pdm-repository-folders',
            'class-pdm-repository-files',
            'class-pdm-repository-logs',
            'class-pdm-download',
            'class-pdm-preview',
            'class-pdm-export',
            'class-pdm-hooks',
            'class-pdm-rest-controller',
            'class-pdm-admin',
            'class-pdm-assets',
            'class-pdm-logger',
        ];

        foreach ($includes as $files) {
            require_once PDM_PLUGIN_DIR . 'includes/' . $files . '.php';
        }
    }

    private function init_hooks(): void
    {
        register_activation_hook(PDM_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(PDM_PLUGIN_FILE, [$this, 'deactivate']);

        add_action('init', [$this, 'maybe_upgrade'], 1);
        add_action('init', [$this, 'init_services'], 5);
        add_action('rest_api_init', [$this, 'init_rest_api']);
        add_action('wp_initialize_site', [$this, 'initialize_site'], 10, 1);
    }

    public function activate(bool $networkWide = false): void
    {
        PDM_Activator::activate($networkWide);
    }

    public function deactivate(): void
    {
        PDM_Deactivator::deactivate();
    }

    public function init_services(): void
    {
        if (!isset($this->services['settings'])) {
            $this->services['settings'] = new PDM_Settings();
        }

        if (!isset($this->services['i18n'])) {
            $this->services['i18n'] = new PDM_I18n();
            $this->services['i18n']->init();
        }

        if (!isset($this->services['logger'])) {
            $this->services['logger'] = new PDM_Logger();
        }

        if (is_admin()) {
            $this->services['admin'] = new PDM_Admin($this->services['settings']);
            $this->services['assets'] = new PDM_Assets();
        }
    }

    public function maybe_upgrade(): void
    {
        PDM_Activator::maybe_upgrade();
    }

    public function initialize_site(\WP_Site $newSite): void
    {
        if (empty($newSite->blog_id)) {
            return;
        }

        PDM_Activator::initialize_site((int) $newSite->blog_id);
    }

    public function init_rest_api(): void
    {
        $settings = $this->service('settings');

        if (!$settings instanceof PDM_Settings) {
            $settings = new PDM_Settings();
            $this->services['settings'] = $settings;
        }

        $auth = new PDM_Auth($settings);
        $storage = new PDM_Storage($settings);
        $validator = new PDM_Validator($settings);
        $folderRepo = new PDM_Repository_Folders();
        $filesRepo = new PDM_Repository_Files();
        $logRepo = new PDM_Repository_Logs();
        $logger = new PDM_Logger($logRepo);
        $download = new PDM_Download($storage, $filesRepo, $auth, $logger);
        $preview = new PDM_Preview($storage, $filesRepo, $auth, $settings);

        $controller = new PDM_REST_Controller(
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
