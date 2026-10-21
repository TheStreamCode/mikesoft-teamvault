<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'MSTV_VERSION' ) ) {
    define( 'MSTV_VERSION', 'test' );
}
if ( ! defined( 'MSTV_PLUGIN_DIR' ) ) {
    define( 'MSTV_PLUGIN_DIR', __DIR__ . '/../' );
}
if ( ! defined( 'MSTV_PLUGIN_URL' ) ) {
    define( 'MSTV_PLUGIN_URL', 'https://example.test/wp-content/plugins/mikesoft-teamvault/' );
}
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['pdm_test_options'] = [];
$GLOBALS['pdm_test_current_user_can'] = false;
$GLOBALS['pdm_test_current_user_caps'] = [];
$GLOBALS['pdm_test_is_user_logged_in'] = false;
$GLOBALS['pdm_test_current_user_id'] = 0;
$GLOBALS['pdm_test_users'] = [];
$GLOBALS['pdm_test_user_meta'] = [];
$GLOBALS['pdm_test_transients'] = [];
$GLOBALS['pdm_test_roles'] = [];
$GLOBALS['pdm_test_network_active_plugins'] = [];
$GLOBALS['pdm_test_actions'] = [];
$GLOBALS['pdm_test_verified_nonce_actions'] = [];
$GLOBALS['pdm_test_rest_routes'] = [];
$GLOBALS['pdm_test_redirects'] = [];

function __($text, $domain = null)
{
    return $text;
}

function esc_html__($text, $domain = null)
{
    return esc_html(__($text, $domain));
}

function esc_html_e($text, $domain = null)
{
    echo esc_html(__($text, $domain));
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr__($text, $domain = null)
{
    return esc_attr(__($text, $domain));
}

function esc_attr_e($text, $domain = null)
{
    echo esc_attr(__($text, $domain));
}

function esc_url($url)
{
    $url = (string) $url;

    if (preg_match('/^\s*(javascript|data|vbscript)\s*:/i', $url)) {
        return '';
    }

    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

function esc_textarea($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function wp_json_encode($data, $flags = 0)
{
    $encoded = json_encode($data, $flags);

    return $encoded === false ? '' : $encoded;
}

function selected($checked, $current = true)
{
    return (string) $checked === (string) $current ? " selected='selected'" : '';
}

function checked($checked, $current = true)
{
    return (string) $checked === (string) $current ? " checked='checked'" : '';
}

function disabled($checked, $current = true)
{
    return (string) $checked === (string) $current ? " disabled='disabled'" : '';
}

function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true)
{
    $name = (string) $name;
    $html = '<input type="hidden" id="' . esc_attr($name) . '" name="' . esc_attr($name)
        . '" value="' . esc_attr(wp_create_nonce($action)) . '">';

    if ((bool) $referer) {
        $html .= '<input type="hidden" name="_wp_http_referer" value="' . esc_attr('/test/') . '">';
    }

    if ((bool) $echo) {
        echo $html;
    }

    return $html;
}

function submit_button($text = '', $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null)
{
    $text = $text !== '' ? (string) $text : 'Save Changes';

    echo '<input type="submit" name="' . esc_attr((string) $name) . '" class="button" value="' . esc_attr($text) . '">';
}

function mysql2date($format, $value)
{
    return (string) $value;
}

function wp_max_upload_size()
{
    return 52428800;
}

function wp_safe_redirect($location, $status = 302)
{
    $GLOBALS['pdm_test_redirects'][] = $location;

    throw new RuntimeException('redirect: ' . $location);
}

function determine_locale()
{
    return 'en_US';
}

function esc_js($text)
{
    return addslashes((string) $text);
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['pdm_test_actions'][$hook][] = [
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];

    return true;
}

function do_action($hook, ...$args)
{
    return null;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    return add_action($hook, $callback, $priority, $accepted_args);
}

function apply_filters($hook, $value, ...$args)
{
    $filters = $GLOBALS['pdm_test_actions'][$hook] ?? [];

    usort($filters, static function ($a, $b) {
        return $a['priority'] <=> $b['priority'];
    });

    foreach ($filters as $filter) {
        if (!is_callable($filter['callback'])) {
            continue;
        }

        $value = call_user_func_array($filter['callback'], array_merge([$value], $args));
    }

    return $value;
}

function wp_strip_all_tags($text)
{
    return trim(strip_tags((string) $text));
}

function sanitize_text_field($text)
{
    $text = is_scalar($text) ? (string) $text : '';
    $text = preg_replace('/[\r\n\t\0]+/', '', $text);

    return trim(wp_strip_all_tags($text));
}

function sanitize_title($title)
{
    $title = strtolower(sanitize_text_field($title));
    $title = preg_replace('/[^a-z0-9]+/', '-', $title);

    return trim((string) $title, '-');
}

function absint($value)
{
    return abs((int) $value);
}

function sanitize_file_name($name)
{
    $name = sanitize_text_field($name);
    $name = str_replace(['\\', '/'], '-', $name);
    $name = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $name);
    $name = preg_replace('/\s+/', '-', $name);
    $name = preg_replace('/-+/', '-', $name);

    return trim($name, '-. ');
}

function sanitize_mime_type($mime)
{
    return sanitize_text_field($mime);
}

function wp_is_writable($path)
{
    return is_writable($path);
}

function wp_delete_file($path)
{
    return file_exists($path) ? unlink($path) : true;
}

function is_multisite()
{
    return false;
}

function is_plugin_active_for_network($plugin)
{
    return !empty($GLOBALS['pdm_test_network_active_plugins'][(string) $plugin]);
}

function wp_validate_boolean($value)
{
    if (is_string($value) && strtolower($value) === 'false') {
        $value = false;
    }

    return (bool) $value;
}

function get_sites(array $args = [])
{
    return [];
}

function switch_to_blog($blogId)
{
    return true;
}

function restore_current_blog()
{
    return true;
}

function esc_sql($data)
{
    return is_scalar($data) ? addslashes((string) $data) : '';
}

function wp_tempnam($filename = '', $dir = '')
{
    if ($dir === '') {
        $dir = trailingslashit(sys_get_temp_dir());
    }

    $filename = (string) $filename;
    $prefix = pathinfo($filename, PATHINFO_FILENAME);
    $prefix = $prefix !== '' ? $prefix : 'wp-temp';

    // Mirror WordPress core: the caller-provided directory is concatenated
    // as-is, without adding a directory separator. The suffix matches the
    // core naming scheme (random suffix, .tmp extension).
    $path = $dir . $prefix . '-' . bin2hex(random_bytes(3)) . '.tmp';

    return touch($path) ? $path : false;
}

function wp_normalize_path($path)
{
    return str_replace('\\', '/', (string) $path);
}

function trailingslashit($value)
{
    return rtrim((string) $value, '/\\') . '/';
}

function untrailingslashit($value)
{
    return rtrim((string) $value, '/\\');
}

function wp_unslash($value)
{
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }

    return is_string($value) ? stripslashes($value) : $value;
}

function is_user_logged_in()
{
    return (bool) $GLOBALS['pdm_test_is_user_logged_in'];
}

function current_user_can($capability)
{
    if (!empty($GLOBALS['pdm_test_current_user_caps'])) {
        return !empty($GLOBALS['pdm_test_current_user_caps'][$capability]);
    }

    return (bool) $GLOBALS['pdm_test_current_user_can'];
}

function get_current_user_id()
{
    return (int) $GLOBALS['pdm_test_current_user_id'];
}

function get_current_blog_id()
{
    return 1;
}

function get_option($key, $default = false)
{
    return $GLOBALS['pdm_test_options'][$key] ?? $default;
}

function update_option($key, $value)
{
    $GLOBALS['pdm_test_options'][$key] = $value;

    return true;
}

function add_option($key, $value, $deprecated = '', $autoload = 'yes')
{
    if (array_key_exists($key, $GLOBALS['pdm_test_options'])) {
        // WordPress add_option() fails on an existing option; callers rely on that
        // for atomic claims.
        return false;
    }

    $GLOBALS['pdm_test_options'][$key] = $value;

    return true;
}

function delete_option($key)
{
    unset($GLOBALS['pdm_test_options'][$key]);

    return true;
}

function get_transient($key)
{
    return $GLOBALS['pdm_test_transients'][$key] ?? false;
}

function set_transient($key, $value, $expiration = 0)
{
    $GLOBALS['pdm_test_transients'][$key] = $value;

    return true;
}

function delete_transient($key)
{
    unset($GLOBALS['pdm_test_transients'][$key]);

    return true;
}

function get_user_by($field, $value)
{
    if ($field !== 'id') {
        return false;
    }

    return $GLOBALS['pdm_test_users'][(int) $value] ?? false;
}

function get_user_meta($userId, $key, $single = false)
{
    $value = $GLOBALS['pdm_test_user_meta'][(int) $userId][$key] ?? null;

    return $single ? $value : [$value];
}

function update_user_meta($userId, $key, $value)
{
    $GLOBALS['pdm_test_user_meta'][(int) $userId][$key] = $value;

    return true;
}

function delete_user_meta($userId, $key)
{
    unset($GLOBALS['pdm_test_user_meta'][(int) $userId][$key]);

    return true;
}

function get_users(array $args = [])
{
    $users = array_values($GLOBALS['pdm_test_users']);
    $metaKey = $args['meta_key'] ?? null;
    $metaValue = $args['meta_value'] ?? null;

    if ($metaKey === null) {
        return $users;
    }

    return array_values(array_filter($users, static function ($user) use ($metaKey, $metaValue) {
        return (($GLOBALS['pdm_test_user_meta'][$user->ID][$metaKey] ?? null) === $metaValue);
    }));
}

function get_role($roleName)
{
    return $GLOBALS['pdm_test_roles'][(string) $roleName] ?? null;
}

function wp_roles()
{
    $roles = [];

    foreach ($GLOBALS['pdm_test_roles'] as $roleName => $role) {
        $roles[$roleName] = [
            'name' => $role->name,
            'capabilities' => $role->capabilities(),
        ];
    }

    $GLOBALS['wp_roles'] = (object) ['roles' => $roles];

    return $GLOBALS['wp_roles'];
}

function get_avatar_url($userId, array $args = [])
{
    return 'https://example.test/avatar/' . (int) $userId;
}

function get_userdata($userId)
{
    return $GLOBALS['pdm_test_users'][(int) $userId] ?? false;
}

function is_email($email)
{
    return is_string($email) && strpos($email, '@') !== false ? $email : false;
}

function get_bloginfo($key = '')
{
    return 'Test Site';
}

function wp_mail($to, $subject, $message, $headers = '', $attachments = [])
{
    $GLOBALS['pdm_test_mails'][] = [
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
    ];

    return true;
}

function wp_verify_nonce($nonce, $action)
{
    $GLOBALS['pdm_test_verified_nonce_actions'][] = (string) $action;

    return $nonce === 'valid-nonce';
}

function check_ajax_referer($action, $query_arg = false, $die = true)
{
    $nonce = null;

    if (is_string($query_arg) && isset($_REQUEST[$query_arg])) {
        $nonce = $_REQUEST[$query_arg];
    } elseif (isset($_REQUEST['_ajax_nonce'])) {
        $nonce = $_REQUEST['_ajax_nonce'];
    } elseif (isset($_REQUEST['_wpnonce'])) {
        $nonce = $_REQUEST['_wpnonce'];
    }

    $result = wp_verify_nonce($nonce, $action);

    if (!$result && (bool) $die) {
        wp_die('Invalid security token.', '', ['response' => 403]);
    }

    return $result ? 1 : false;
}

function check_admin_referer($action = -1, $query_arg = '_wpnonce')
{
    $nonce = isset($_REQUEST[$query_arg]) ? $_REQUEST[$query_arg] : null;

    $result = wp_verify_nonce($nonce, $action);

    if (!$result) {
        wp_die('Are you sure you want to do this?', '', ['response' => 400]);
    }

    return $result;
}

function register_rest_route($namespace, $route, $args = [], $override = false)
{
    $GLOBALS['pdm_test_rest_routes'][$namespace . $route][] = $args;

    return true;
}

function wp_upload_dir()
{
    return [
        'basedir' => sys_get_temp_dir() . '/mstv-test-uploads',
    ];
}

function admin_url($path = '')
{
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg(array $args, string $url)
{
    return $url . '?' . http_build_query($args);
}

function wp_create_nonce($action)
{
    return 'valid-nonce';
}

function nocache_headers()
{
}

function human_time_diff($from, $to = 0)
{
    return '1 hour';
}

function wp_die($message = '', $title = '', $args = [])
{
    throw new RuntimeException(is_string($message) ? $message : 'wp_die');
}

class WP_Error
{
    public string $code;
    public string $message;
    public array $data;

    public function __construct(string $code, string $message, array $data = [])
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }
}

class WP_REST_Request
{
    private array $headers = [];
    private array $params = [];

    public function __construct(array $params = [], array $headers = [])
    {
        $this->params = $params;
        $this->headers = $headers;
    }

    public function get_header(string $name)
    {
        return $this->headers[$name] ?? '';
    }

    public function get_param(string $name)
    {
        return $this->params[$name] ?? null;
    }
}

class WP_REST_Response
{
    public array $data;
    public int $status;
    private array $headers = [];

    public function __construct(array $data = [], int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function header(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function get_headers(): array
    {
        return $this->headers;
    }
}

class WP_Site
{
    public int $blog_id;

    public function __construct(int $blogId)
    {
        $this->blog_id = $blogId;
    }
}

class FakePDMUser
{
    public int $ID;
    private array $caps = [];

    public function __construct(int $id, array $caps = [])
    {
        $this->ID = $id;
        $this->caps = $caps;
    }

    public function add_cap(string $cap): void
    {
        $this->caps[$cap] = true;
    }

    public function remove_cap(string $cap): void
    {
        unset($this->caps[$cap]);
    }

    public function has_cap(string $cap): bool
    {
        return !empty($this->caps[$cap]);
    }
}

class FakePDMViewUser
{
    public int $ID;
    public string $display_name;
    public string $user_login;

    public function __construct(int $id, string $displayName, string $login)
    {
        $this->ID = $id;
        $this->display_name = $displayName;
        $this->user_login = $login;
    }
}

class FakePDMRole
{
    public string $name;
    private array $caps = [];

    public function __construct(string $name, array $caps = [])
    {
        $this->name = $name;
        $this->caps = $caps;
    }

    public function add_cap(string $cap): void
    {
        $this->caps[$cap] = true;
    }

    public function remove_cap(string $cap): void
    {
        unset($this->caps[$cap]);
    }

    public function has_cap(string $cap): bool
    {
        return !empty($this->caps[$cap]);
    }

    public function capabilities(): array
    {
        return $this->caps;
    }
}

require_once __DIR__ . '/../includes/class-mstv-capabilities.php';
require_once __DIR__ . '/../includes/class-mstv-i18n.php';
require_once __DIR__ . '/../includes/class-mstv-settings.php';
require_once __DIR__ . '/../includes/class-mstv-permissions.php';
require_once __DIR__ . '/../includes/class-mstv-auth.php';
require_once __DIR__ . '/../includes/class-mstv-helpers.php';
require_once __DIR__ . '/../includes/class-mstv-filesystem.php';
require_once __DIR__ . '/../includes/class-mstv-storage.php';
require_once __DIR__ . '/../includes/class-mstv-validator.php';
require_once __DIR__ . '/../includes/class-mstv-repository-files.php';
require_once __DIR__ . '/../includes/class-mstv-repository-folders.php';
require_once __DIR__ . '/../includes/class-mstv-repository-logs.php';
require_once __DIR__ . '/../includes/class-mstv-repository-groups.php';
require_once __DIR__ . '/../includes/class-mstv-repository-permissions.php';
require_once __DIR__ . '/../includes/class-mstv-quota.php';
require_once __DIR__ . '/../includes/class-mstv-reindex-lock.php';
require_once __DIR__ . '/../includes/class-mstv-hooks.php';
require_once __DIR__ . '/../includes/class-mstv-notifications.php';
require_once __DIR__ . '/../includes/trait-mstv-binary-stream.php';
require_once __DIR__ . '/../includes/class-mstv-download.php';
require_once __DIR__ . '/../includes/class-mstv-preview.php';
require_once __DIR__ . '/../includes/class-mstv-export.php';
require_once __DIR__ . '/../includes/class-mstv-rest-controller.php';
require_once __DIR__ . '/../includes/class-mstv-rest-governance.php';
require_once __DIR__ . '/../includes/class-mstv-logger.php';
require_once __DIR__ . '/../includes/class-mstv-activator.php';
require_once __DIR__ . '/../includes/class-mstv-admin.php';
require_once __DIR__ . '/../includes/class-mstv-assets.php';

if (!defined('MSTV_PLUGIN_BASENAME')) {
    define('MSTV_PLUGIN_BASENAME', 'mikesoft-teamvault/mikesoft-teamvault.php');
}

require_once __DIR__ . '/../includes/class-mstv-bootstrap.php';
