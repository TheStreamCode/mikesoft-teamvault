<?php

defined('ABSPATH') || exit;

class MSTV_Settings
{
    private const OPTION_GROUP = 'mstv_settings';
    private const OPTION_PAGE = 'mstv-settings';
    private const DISALLOWED_UPLOAD_EXTENSIONS = ['svg'];

    private $defaults = [
        'mstv_interface_language' => 'en',
        'mstv_storage_path' => '',
        'mstv_allowed_extensions' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp,zip,rar,7z,txt,csv,rtf,mp3,wav,ogg,mp4,avi,mov,mkv',
        'mstv_max_file_size' => 52428800,
        'mstv_log_enabled' => true,
        'mstv_pdf_preview_enabled' => true,
        'mstv_remove_data_on_uninstall' => false,
        'mstv_use_user_whitelist' => false,
        'mstv_allowed_users' => [],
    ];

    private const LEGACY_GRANTED_CAPABILITY_META = 'mstv_granted_capability';

    public function init(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings(): void
    {
        register_setting(self::OPTION_GROUP, 'mstv_interface_language', [
            'type' => 'string',
            'sanitize_callback' => ['MSTV_I18n', 'sanitize_language'],
            'default' => 'en',
        ]);

        register_setting(self::OPTION_GROUP, 'mstv_storage_path', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting(self::OPTION_GROUP, 'mstv_allowed_extensions', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_extensions'],
            'default' => $this->defaults['mstv_allowed_extensions'],
        ]);

        register_setting(self::OPTION_GROUP, 'mstv_max_file_size', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => $this->defaults['mstv_max_file_size'],
        ]);

        register_setting(self::OPTION_GROUP, 'mstv_log_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'wp_validate_boolean',
            'default' => true,
        ]);

        register_setting(self::OPTION_GROUP, 'mstv_pdf_preview_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'wp_validate_boolean',
            'default' => true,
        ]);

        register_setting(self::OPTION_GROUP, 'mstv_remove_data_on_uninstall', [
            'type' => 'boolean',
            'sanitize_callback' => 'wp_validate_boolean',
            'default' => false,
        ]);

        register_setting(self::OPTION_GROUP, 'mstv_use_user_whitelist', [
            'type' => 'boolean',
            'sanitize_callback' => 'wp_validate_boolean',
            'default' => false,
        ]);

        register_setting(self::OPTION_GROUP, 'mstv_allowed_users', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_user_ids'],
            'default' => [],
        ]);
    }

    public function sanitize_extensions(?string $value): string
    {
        if (empty($value)) {
            return $this->defaults['mstv_allowed_extensions'];
        }

        $extensions = array_map('trim', explode(',', $value));
        $extensions = array_map('strtolower', $extensions);
        $extensions = array_filter($extensions, fn($ext) => preg_match('/^[a-z0-9]+$/i', $ext));
        $extensions = array_filter($extensions, fn($ext) => !in_array($ext, self::DISALLOWED_UPLOAD_EXTENSIONS, true));
        $extensions = array_values(array_unique($extensions));

        return implode(',', $extensions);
    }

    public function sanitize_user_ids($value): array
    {
        if (!is_array($value)) {
            $value = json_decode(stripslashes($value), true);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_filter(array_map('absint', $value), fn($id) => $id > 0);
    }

    public function get(string $key, $default = null)
    {
        $value = get_option($key, $default ?? ($this->defaults[$key] ?? null));
        return $value;
    }

    public function get_storage_path(): string
    {
        $customPath = $this->get('mstv_storage_path');

        if (!empty($customPath) && $this->is_valid_storage_path($customPath)) {
            $path = rtrim($customPath, '/\\');

            if (class_exists('MSTV_Hooks')) {
                return MSTV_Hooks::filter_storage_path($path);
            }
            return $path;
        }

        $uploadDir = wp_upload_dir();
        $path = $uploadDir['basedir'] . '/private-documents';

        if (class_exists('MSTV_Hooks')) {
            return MSTV_Hooks::filter_storage_path($path);
        }
        return $path;
    }

    public function get_allowed_extensions(): array
    {
        $extensions = $this->get('mstv_allowed_extensions');
        $extensions = array_map('trim', explode(',', $extensions));
        $extensions = array_values(array_filter($extensions, fn($ext) => !in_array(strtolower($ext), self::DISALLOWED_UPLOAD_EXTENSIONS, true)));

        return class_exists('MSTV_Hooks') ? MSTV_Hooks::filter_allowed_extensions($extensions) : $extensions;
    }

    public function get_interface_language(): string
    {
        return (string) $this->get('mstv_interface_language', 'en');
    }

    public function get_max_file_size(): int
    {
        $size = (int) $this->get('mstv_max_file_size');

        return class_exists('MSTV_Hooks') ? MSTV_Hooks::filter_max_file_size($size) : $size;
    }

    public function is_log_enabled(): bool
    {
        return (bool) $this->get('mstv_log_enabled', true);
    }

    public function is_pdf_preview_enabled(): bool
    {
        return (bool) $this->get('mstv_pdf_preview_enabled', true);
    }

    public function should_remove_data_on_uninstall(): bool
    {
        return (bool) $this->get('mstv_remove_data_on_uninstall', false);
    }

    public function use_user_whitelist(): bool
    {
        return (bool) $this->get('mstv_use_user_whitelist', false);
    }

    public function get_allowed_users(): array
    {
        $users = $this->get('mstv_allowed_users', []);
        return is_array($users) ? $users : [];
    }

    public function set_allowed_users(array $userIds): bool
    {
        return $this->update('mstv_allowed_users', $this->sanitize_user_ids($userIds));
    }

    public function is_user_allowed(int $userId): bool
    {
        if (!$this->use_user_whitelist()) {
            return true;
        }

        $allowed = $this->get_allowed_users();
        return in_array($userId, $allowed, true);
    }

    public function sync_capabilities_on_whitelist_change(array $newUserIds, bool $whitelistEnabled): void
    {
        $newUserIds = $this->sanitize_user_ids($newUserIds);
        $oldUserIds = $this->get_allowed_users();

        $this->cleanup_legacy_granted_capabilities();

        if ($whitelistEnabled) {
            $this->grant_capability_to_users($newUserIds, $oldUserIds);
        } else {
            $this->revoke_all_granted_capabilities();
        }

        $this->update('mstv_allowed_users', $newUserIds);
        $this->update('mstv_use_user_whitelist', $whitelistEnabled);
    }

    public function validate_whitelist_selection(array $userIds, int $currentUserId): bool|\WP_Error
    {
        $userIds = $this->sanitize_user_ids($userIds);

        if (empty($userIds)) {
            return new \WP_Error(
                'mstv_invalid_whitelist',
                __('Select at least one authorized user before enabling the whitelist.', 'mikesoft-teamvault')
            );
        }

        if (!in_array($currentUserId, $userIds, true)) {
            return new \WP_Error(
                'mstv_whitelist_lockout',
                __('Add your current account to the whitelist before enabling it, otherwise you will lock yourself out.', 'mikesoft-teamvault')
            );
        }

        return true;
    }

    public function get_granted_capability_meta_key(): string
    {
        return self::LEGACY_GRANTED_CAPABILITY_META . '_' . get_current_blog_id();
    }

    private function grant_capability_to_users(array $newUserIds, array $oldUserIds): void
    {
        $metaKey = $this->get_granted_capability_meta_key();
        $toRemove = array_diff($oldUserIds, $newUserIds);

        foreach ($newUserIds as $userId) {
            $user = get_user_by('id', $userId);
            if ($user) {
                if (!$user->has_cap(MSTV_Capabilities::CAP_MANAGE)) {
                    $user->add_cap(MSTV_Capabilities::CAP_MANAGE);
                }

                if (!get_user_meta($userId, $metaKey, true)) {
                    update_user_meta($userId, $metaKey, true);
                }
            }
        }

        foreach ($toRemove as $userId) {
            if (get_user_meta($userId, $metaKey, true)) {
                $user = get_user_by('id', $userId);
                if ($user) {
                    $user->remove_cap(MSTV_Capabilities::CAP_MANAGE);
                }
                delete_user_meta($userId, $metaKey);
            }
        }
    }

    private function revoke_all_granted_capabilities(): void
    {
        $metaKey = $this->get_granted_capability_meta_key();
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- User capability cleanup runs rarely and targets plugin-managed metadata only.
        $users = get_users(['meta_key' => $metaKey, 'meta_value' => true]);

        foreach ($users as $user) {
            $user->remove_cap(MSTV_Capabilities::CAP_MANAGE);
            delete_user_meta($user->ID, $metaKey);
        }
    }

    public function sync_existing_whitelist(): void
    {
        if (!$this->use_user_whitelist()) {
            return;
        }

        $this->cleanup_legacy_granted_capabilities();

        $userIds = $this->get_allowed_users();
        $metaKey = $this->get_granted_capability_meta_key();

        foreach ($userIds as $userId) {
            $user = get_user_by('id', $userId);
            if ($user && !$user->has_cap(MSTV_Capabilities::CAP_MANAGE)) {
                $user->add_cap(MSTV_Capabilities::CAP_MANAGE);
                update_user_meta($userId, $metaKey, true);
            }
        }
    }

    private function is_valid_storage_path(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        if (!wp_is_writable($path)) {
            return false;
        }

        $realpath = realpath($path);
        if (false === $realpath) {
            return false;
        }

        $normalizedRealpath = wp_normalize_path($realpath);
        $uploadDir = wp_upload_dir();
        $uploadBase = isset($uploadDir['basedir']) ? realpath($uploadDir['basedir']) : false;

        if ($uploadBase !== false) {
            $normalizedUploadBase = trailingslashit(wp_normalize_path($uploadBase));

            if ($normalizedRealpath === untrailingslashit($normalizedUploadBase)
                || strpos(trailingslashit($normalizedRealpath), $normalizedUploadBase) === 0) {
                return true;
            }
        }

        return file_exists($realpath . DIRECTORY_SEPARATOR . '.mstv-storage');
    }

    private function cleanup_legacy_granted_capabilities(): void
    {
        $allowedUsers = $this->get_allowed_users();
        $metaKey = $this->get_granted_capability_meta_key();
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Legacy cleanup runs rarely during settings sync/upgrade.
        $users = get_users(['meta_key' => self::LEGACY_GRANTED_CAPABILITY_META, 'meta_value' => true]);

        foreach ($users as $user) {
            if ($this->use_user_whitelist() && in_array((int) $user->ID, $allowedUsers, true)) {
                update_user_meta($user->ID, $metaKey, true);
            } else {
                $user->remove_cap(MSTV_Capabilities::CAP_MANAGE);
            }

            delete_user_meta($user->ID, self::LEGACY_GRANTED_CAPABILITY_META);
        }
    }

    public function update(string $key, $value): bool
    {
        return update_option($key, $value);
    }
}
