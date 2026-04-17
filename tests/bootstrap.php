<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['pdm_test_options'] = [];
$GLOBALS['pdm_test_current_user_can'] = false;
$GLOBALS['pdm_test_is_user_logged_in'] = false;
$GLOBALS['pdm_test_current_user_id'] = 0;
$GLOBALS['pdm_test_users'] = [];
$GLOBALS['pdm_test_user_meta'] = [];
$GLOBALS['pdm_test_transients'] = [];

function __($text, $domain = null)
{
    return $text;
}

function esc_html__($text, $domain = null)
{
    return $text;
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

function add_option($key, $value)
{
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

function wp_verify_nonce($nonce, $action)
{
    return $nonce === 'valid-nonce';
}

function wp_upload_dir()
{
    return [
        'basedir' => sys_get_temp_dir(),
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

    public function __construct(array $data = [], int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
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

require_once __DIR__ . '/../includes/class-mstv-capabilities.php';
require_once __DIR__ . '/../includes/class-mstv-settings.php';
require_once __DIR__ . '/../includes/class-mstv-auth.php';
require_once __DIR__ . '/../includes/class-mstv-helpers.php';
require_once __DIR__ . '/../includes/class-mstv-filesystem.php';
require_once __DIR__ . '/../includes/class-mstv-storage.php';
require_once __DIR__ . '/../includes/class-mstv-validator.php';
require_once __DIR__ . '/../includes/class-mstv-repository-files.php';
require_once __DIR__ . '/../includes/class-mstv-repository-folders.php';
require_once __DIR__ . '/../includes/class-mstv-download.php';
require_once __DIR__ . '/../includes/class-mstv-preview.php';
require_once __DIR__ . '/../includes/class-mstv-rest-controller.php';
require_once __DIR__ . '/../includes/class-mstv-logger.php';
require_once __DIR__ . '/../includes/class-mstv-activator.php';
