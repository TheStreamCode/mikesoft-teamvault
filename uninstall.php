<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$mstv_storage_path_usage = is_multisite() ? mstv_collect_storage_path_usage() : [];

if (is_multisite()) {
    $mstv_site_ids = get_sites(['fields' => 'ids']);

    foreach ($mstv_site_ids as $mstv_site_id) {
        switch_to_blog((int) $mstv_site_id);
        mstv_uninstall_site($mstv_storage_path_usage);
        restore_current_blog();
    }
} else {
    mstv_uninstall_site($mstv_storage_path_usage);
}

function mstv_uninstall_site(array $storage_path_usage = []) {
    global $wpdb;

    $remove_data = get_option('mstv_remove_data_on_uninstall', false);

    if (!$remove_data) {
        delete_option('mstv_remove_data_on_uninstall');
        return;
    }

    $prefix = $wpdb->get_blog_prefix(get_current_blog_id());
    $folders_table = $prefix . 'mstv_folders';
    $files_table = $prefix . 'mstv_files';
    $logs_table = $prefix . 'mstv_logs';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Expected cleanup during uninstall.
    $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($folders_table) . '`');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Expected cleanup during uninstall.
    $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($files_table) . '`');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Expected cleanup during uninstall.
    $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($logs_table) . '`');

    $custom_storage_path = (string) get_option('mstv_storage_path', '');
    $upload_dir = wp_upload_dir();
    $default_storage_path = isset($upload_dir['basedir']) ? $upload_dir['basedir'] . '/private-documents' : '';

    delete_option('mstv_storage_path');
    delete_option('mstv_interface_language');
    delete_option('mstv_allowed_extensions');
    delete_option('mstv_max_file_size');
    delete_option('mstv_log_enabled');
    delete_option('mstv_pdf_preview_enabled');
    delete_option('mstv_remove_data_on_uninstall');
    delete_option('mstv_plugin_version');
    delete_option('mstv_use_user_whitelist');
    delete_option('mstv_allowed_users');

    $storage_paths = array_filter(array_unique([
        mstv_normalize_uninstall_path($custom_storage_path),
        mstv_normalize_uninstall_path($default_storage_path),
    ]));

    foreach ($storage_paths as $storage_path) {
        if (mstv_is_safe_storage_path($storage_path, $default_storage_path, $storage_path_usage) && is_dir($storage_path)) {
            mstv_recursive_delete($storage_path);
        }
    }

    mstv_remove_granted_capabilities_for_site(get_current_blog_id());

    if (function_exists('wp_roles')) {
        $roles = wp_roles();

        if ($roles && !empty($roles->roles) && is_array($roles->roles)) {
            foreach ($roles->roles as $role_name => $role_data) {
                $role = get_role($role_name);
                if ($role && $role->has_cap('manage_private_documents')) {
                    $role->remove_cap('manage_private_documents');
                }
            }
        }
    }
}

function mstv_collect_storage_path_usage() {
    $usage = [];
    $mstv_site_ids = get_sites(['fields' => 'ids']);

    foreach ($mstv_site_ids as $mstv_site_id) {
        switch_to_blog((int) $mstv_site_id);
        $upload_dir = wp_upload_dir();
        $default_storage_path = isset($upload_dir['basedir']) ? $upload_dir['basedir'] . '/private-documents' : '';
        $custom_storage_path = (string) get_option('mstv_storage_path', '');

        foreach ([mstv_normalize_uninstall_path($custom_storage_path), mstv_normalize_uninstall_path($default_storage_path)] as $path) {
            if ($path === '') {
                continue;
            }

            $real_path = realpath($path);
            $usage_key = $real_path !== false ? wp_normalize_path($real_path) : $path;

            $usage[$usage_key] = ($usage[$usage_key] ?? 0) + 1;
        }

        restore_current_blog();
    }

    return $usage;
}

function mstv_is_safe_storage_path($path, $default_path, array $storage_path_usage = []) {
    $path = mstv_normalize_uninstall_path($path);

    $usage_path = $path;
    $real_path = realpath($path);
    if ($real_path !== false) {
        $usage_path = wp_normalize_path($real_path);
    }

    if ($path === '' || (($storage_path_usage[$usage_path] ?? 0) > 1)) {
        return false;
    }

    if ($real_path === false) {
        return false;
    }

    $real_default_path = $default_path !== '' ? realpath($default_path) : false;
    if ($real_default_path !== false && wp_normalize_path($real_path) === wp_normalize_path($real_default_path)) {
        return true;
    }

    return file_exists($real_path . DIRECTORY_SEPARATOR . '.mstv-storage');
}

function mstv_remove_granted_capabilities_for_site($blog_id) {
    $meta_keys = [
        'mstv_granted_capability_' . (int) $blog_id,
        'mstv_granted_capability',
    ];

    foreach ($meta_keys as $meta_key) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Uninstall-only cleanup for plugin-managed user markers.
        $users = get_users(['meta_key' => $meta_key, 'meta_value' => true]);

        foreach ($users as $mstv_user) {
            $mstv_user->remove_cap('manage_private_documents');
            delete_user_meta($mstv_user->ID, $meta_key);
        }
    }
}

function mstv_recursive_delete($path) {
    if (!is_dir($path)) {
        wp_delete_file($path);
        return;
    }

    $items = @scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $item_path = $path . DIRECTORY_SEPARATOR . $item;

        if (is_dir($item_path)) {
            mstv_recursive_delete($item_path);
        } else {
            wp_delete_file($item_path);
        }
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    global $wp_filesystem;

    if (!$wp_filesystem) {
        WP_Filesystem();
    }

    if ($wp_filesystem) {
        $wp_filesystem->rmdir($path, false);
    }
}

function mstv_normalize_uninstall_path($path) {
    $path = is_string($path) ? trim($path) : '';

    if ($path === '') {
        return '';
    }

    return rtrim($path, '/\\');
}
