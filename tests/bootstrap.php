<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../');
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['pdm_test_options'] = [];
$GLOBALS['pdm_test_current_user_can'] = false;
$GLOBALS['pdm_test_is_user_logged_in'] = false;
$GLOBALS['pdm_test_current_user_id'] = 0;
$GLOBALS['pdm_test_users'] = [];
$GLOBALS['pdm_test_user_meta'] = [];

function __($text, $domain = null)
{
    return $text;
}

function esc_html__($text, $domain = null)
{
    return $text;
}

function sanitize_text_field($text)
{
    $text = is_scalar($text) ? (string) $text : '';
    $text = preg_replace('/[\r\n\t\0]+/', '', $text);

    return trim(strip_tags($text));
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

require_once __DIR__ . '/../includes/class-pdm-capabilities.php';
require_once __DIR__ . '/../includes/class-pdm-settings.php';
require_once __DIR__ . '/../includes/class-pdm-auth.php';
require_once __DIR__ . '/../includes/class-pdm-helpers.php';
require_once __DIR__ . '/../includes/class-pdm-logger.php';
require_once __DIR__ . '/../includes/class-pdm-activator.php';
