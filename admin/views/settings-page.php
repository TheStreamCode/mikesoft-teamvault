<?php

defined('ABSPATH') || exit;

$mstv_interface_language = (string) get_option('mstv_interface_language', 'en');
$mstv_use_user_whitelist = (bool) get_option('mstv_use_user_whitelist', false);
$mstv_allowed_users = get_option('mstv_allowed_users', []);
$mstv_allowed_extensions = implode(',', (new MSTV_Settings())->get_allowed_extensions());
$mstv_max_file_size = (int) get_option('mstv_max_file_size', 52428800);
$mstv_pdf_preview_enabled = (bool) get_option('mstv_pdf_preview_enabled', true);
$mstv_log_enabled = (bool) get_option('mstv_log_enabled', true);
$mstv_remove_data_on_uninstall = (bool) get_option('mstv_remove_data_on_uninstall', false);
$mstv_settings_saved = (bool) get_transient('mstv_settings_saved_' . get_current_user_id());
$mstv_settings_error = get_transient('mstv_settings_error_' . get_current_user_id());

if ($mstv_settings_saved) {
    delete_transient('mstv_settings_saved_' . get_current_user_id());
}

if ($mstv_settings_error !== false) {
    delete_transient('mstv_settings_error_' . get_current_user_id());
}

$mstv_allowed_users = is_array($mstv_allowed_users) ? $mstv_allowed_users : [];
$mstv_current_storage_path = (new MSTV_Settings())->get_storage_path();
$mstv_roles_with_capability = MSTV_Capabilities::get_roles_with_capability();
$mstv_max_server_upload_size = (int) wp_max_upload_size();
?>
<div class="pdm-wrapper pdm-settings-wrapper">
    <?php if ($mstv_settings_saved) : ?>
        <div class="pdm-notice pdm-notice-success">
            <?php esc_html_e('Settings saved successfully.', 'mikesoft-teamvault'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mstv_settings_error)) : ?>
        <div class="pdm-notice pdm-notice-error">
            <?php echo esc_html((string) $mstv_settings_error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($cleanup_result) && isset($cleanup_result['deleted_count'])) : ?>
        <div class="pdm-notice pdm-notice-success">
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %d: number of deleted orphaned records. */
                    __('Cleanup completed. Removed %d orphaned file records.', 'mikesoft-teamvault'),
                    (int) $cleanup_result['deleted_count']
                )
            );
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($reindex_result) && !empty($reindex_result['success'])) : ?>
        <div class="pdm-notice pdm-notice-success">
            <?php
            echo esc_html(
                sprintf(
                    /* translators: 1: folder count, 2: file count. */
                    __('Reindex completed. Restored %1$d folders and %2$d files from storage.', 'mikesoft-teamvault'),
                    (int) ($reindex_result['folders_created'] ?? 0),
                    (int) ($reindex_result['files_created'] ?? 0)
                )
            );
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($reindex_result) && empty($reindex_result['success']) && !empty($reindex_result['error'])) : ?>
        <div class="pdm-notice pdm-notice-error">
            <?php echo esc_html((string) $reindex_result['error']); ?>
        </div>
    <?php endif; ?>

    <div class="pdm-settings-header">
        <h1 class="pdm-settings-title"><?php esc_html_e('TeamVault Settings', 'mikesoft-teamvault'); ?></h1>
        <p class="pdm-settings-desc"><?php esc_html_e('Configure the plugin options for private document management.', 'mikesoft-teamvault'); ?></p>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pdm-settings-form">
        <input type="hidden" name="action" value="mstv_save_settings">
        <?php wp_nonce_field('mstv_settings_nonce', 'mstv_settings_nonce'); ?>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Interface language', 'mikesoft-teamvault'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-field-label" for="mstv_interface_language">
                    <?php esc_html_e('Interface language', 'mikesoft-teamvault'); ?>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('Choose the plugin interface language. The default language is English.', 'mikesoft-teamvault'); ?></p>
                <select id="mstv_interface_language" name="mstv_interface_language" class="pdm-select">
                    <option value="en" <?php selected($mstv_interface_language, 'en'); ?>><?php esc_html_e('English (default)', 'mikesoft-teamvault'); ?></option>
                    <option value="it" <?php selected($mstv_interface_language, 'it'); ?>><?php esc_html_e('Italian', 'mikesoft-teamvault'); ?></option>
                </select>
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('User Access', 'mikesoft-teamvault'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-checkbox-label">
                    <input
                        type="checkbox"
                        id="mstv_use_user_whitelist"
                        name="mstv_use_user_whitelist"
                        value="1"
                        <?php checked($mstv_use_user_whitelist, true); ?>
                    >
                    <span class="pdm-checkbox-text"><?php esc_html_e('Limit access to specific users', 'mikesoft-teamvault'); ?></span>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('If enabled, users still need the plugin capability and must also be present in this list. Include your current account before saving to avoid locking yourself out.', 'mikesoft-teamvault'); ?></p>
            </div>

            <div class="pdm-field pdm-user-whitelist-field" style="<?php echo esc_attr($mstv_use_user_whitelist ? '' : 'display:none;'); ?>">
                <label class="pdm-field-label"><?php esc_html_e('Authorized users', 'mikesoft-teamvault'); ?></label>

                <div class="pdm-user-search">
                    <input
                        type="text"
                        id="pdm-user-search"
                        class="pdm-input"
                        placeholder="<?php echo esc_attr__('Search users...', 'mikesoft-teamvault'); ?>"
                        autocomplete="off"
                    >
                    <div id="pdm-user-results" class="pdm-user-results"></div>
                </div>

                <div id="pdm-allowed-users" class="pdm-allowed-users">
                    <?php foreach ($mstv_allowed_users as $mstv_user_id) : ?>
                        <?php $mstv_user = get_user_by('id', $mstv_user_id); ?>
                        <?php if ($mstv_user) : ?>
                            <div class="pdm-user-tag" data-user-id="<?php echo esc_attr($mstv_user_id); ?>">
                                <span class="pdm-user-name"><?php echo esc_html($mstv_user->display_name . ' (' . $mstv_user->user_login . ')'); ?></span>
                                <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-remove-user" title="<?php echo esc_attr__('Remove', 'mikesoft-teamvault'); ?>" aria-label="<?php echo esc_attr__('Remove', 'mikesoft-teamvault'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                                <input type="hidden" name="mstv_allowed_users[]" value="<?php echo esc_attr($mstv_user_id); ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <p id="pdm-no-users" class="pdm-field-desc" style="<?php echo esc_attr(empty($mstv_allowed_users) ? '' : 'display:none;'); ?>">
                    <?php esc_html_e('No users selected', 'mikesoft-teamvault'); ?>
                </p>
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Files', 'mikesoft-teamvault'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-field-label" for="mstv_allowed_extensions">
                    <?php esc_html_e('Allowed extensions', 'mikesoft-teamvault'); ?>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('Comma-separated list of allowed file extensions for upload.', 'mikesoft-teamvault'); ?></p>
                <textarea
                    id="mstv_allowed_extensions"
                    name="mstv_allowed_extensions"
                    class="pdm-textarea"
                    rows="3"
                ><?php echo esc_textarea($mstv_allowed_extensions); ?></textarea>
            </div>

            <div class="pdm-field">
                <label class="pdm-field-label" for="mstv_max_file_size">
                    <?php esc_html_e('Maximum file size (bytes)', 'mikesoft-teamvault'); ?>
                </label>
                <p class="pdm-field-desc">
                    <?php esc_html_e('Maximum size allowed for each file.', 'mikesoft-teamvault'); ?>
                    <br>
                    <?php esc_html_e('Current:', 'mikesoft-teamvault'); ?>
                    <strong><?php echo esc_html(MSTV_Helpers::format_filesize($mstv_max_file_size)); ?></strong>
                    -
                    <?php esc_html_e('Server limit:', 'mikesoft-teamvault'); ?>
                    <strong><?php echo esc_html(MSTV_Helpers::format_filesize($mstv_max_server_upload_size)); ?></strong>
                </p>
                <input
                    type="number"
                    id="mstv_max_file_size"
                    name="mstv_max_file_size"
                    class="pdm-input"
                    value="<?php echo esc_attr((string) $mstv_max_file_size); ?>"
                    min="1"
                    max="<?php echo esc_attr((string) $mstv_max_server_upload_size); ?>"
                >
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Preview', 'mikesoft-teamvault'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-checkbox-label">
                    <input
                        type="checkbox"
                        id="mstv_pdf_preview_enabled"
                        name="mstv_pdf_preview_enabled"
                        value="1"
                        <?php checked($mstv_pdf_preview_enabled, true); ?>
                    >
                    <span class="pdm-checkbox-text"><?php esc_html_e('Enable inline PDF preview', 'mikesoft-teamvault'); ?></span>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('If enabled, PDFs will be displayed directly in the browser. Some browsers may not support this feature.', 'mikesoft-teamvault'); ?></p>
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Log', 'mikesoft-teamvault'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-checkbox-label">
                    <input
                        type="checkbox"
                        id="mstv_log_enabled"
                        name="mstv_log_enabled"
                        value="1"
                        <?php checked($mstv_log_enabled, true); ?>
                    >
                    <span class="pdm-checkbox-text"><?php esc_html_e('Enable activity log', 'mikesoft-teamvault'); ?></span>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('Record upload, download, delete and move operations for files.', 'mikesoft-teamvault'); ?></p>
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Maintenance', 'mikesoft-teamvault'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-field-label"><?php esc_html_e('Orphaned file records', 'mikesoft-teamvault'); ?></label>
                <p class="pdm-field-desc">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: number of orphaned records. */
                            __('Found %d database records whose files are missing from the private storage directory.', 'mikesoft-teamvault'),
                            (int) $orphaned_files_count
                        )
                    );
                    ?>
                </p>
                <p class="pdm-field-desc"><?php esc_html_e('Use cleanup after a local migration if the private storage folder was not copied.', 'mikesoft-teamvault'); ?></p>
                <div class="pdm-inline-form pdm-maintenance-form">
                    <button type="submit" form="mstv-cleanup-orphans-form" class="pdm-btn pdm-btn-secondary" <?php disabled((int) $orphaned_files_count, 0); ?>>
                        <?php esc_html_e('Clean orphaned records', 'mikesoft-teamvault'); ?>
                    </button>
                </div>
            </div>

            <div class="pdm-field">
                <label class="pdm-field-label"><?php esc_html_e('Reindex storage', 'mikesoft-teamvault'); ?></label>
                <p class="pdm-field-desc"><?php esc_html_e('Restore folder and file records that still exist on disk but are missing from the database.', 'mikesoft-teamvault'); ?></p>
                <p class="pdm-field-desc"><?php esc_html_e('Use this after an incomplete uninstall or a migration where the storage directory remained available.', 'mikesoft-teamvault'); ?></p>
                <div class="pdm-inline-form pdm-maintenance-form">
                    <button type="submit" form="mstv-reindex-storage-form" class="pdm-btn pdm-btn-secondary">
                        <?php esc_html_e('Reindex storage', 'mikesoft-teamvault'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="pdm-settings-section pdm-settings-section--danger">
            <h2 class="pdm-section-title"><?php esc_html_e('Uninstall', 'mikesoft-teamvault'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-checkbox-label pdm-checkbox-label--danger">
                    <input
                        type="checkbox"
                        id="mstv_remove_data_on_uninstall"
                        name="mstv_remove_data_on_uninstall"
                        value="1"
                        <?php checked($mstv_remove_data_on_uninstall, true); ?>
                    >
                    <span class="pdm-checkbox-text"><?php esc_html_e('Delete all data on uninstall', 'mikesoft-teamvault'); ?></span>
                </label>
                <p class="pdm-field-desc">
                    <strong><?php esc_html_e('Warning:', 'mikesoft-teamvault'); ?></strong>
                    <?php esc_html_e('If enabled, uninstalling the plugin will remove all files, folders, logs and settings. This action is irreversible.', 'mikesoft-teamvault'); ?>
                </p>
            </div>
        </div>

        <div class="pdm-settings-actions">
            <?php submit_button(esc_html__('Save Settings', 'mikesoft-teamvault'), 'primary pdm-btn-primary', 'submit', false); ?>
        </div>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="mstv-cleanup-orphans-form">
        <input type="hidden" name="action" value="mstv_cleanup_orphans">
        <?php wp_nonce_field('mstv_cleanup_orphans', 'mstv_cleanup_orphans_nonce'); ?>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="mstv-reindex-storage-form">
        <input type="hidden" name="action" value="mstv_reindex_storage">
        <?php wp_nonce_field('mstv_reindex_storage', 'mstv_reindex_storage_nonce'); ?>
    </form>

    <div class="pdm-settings-info">
        <h3><?php esc_html_e('Information', 'mikesoft-teamvault'); ?></h3>
        <ul>
            <li><strong><?php esc_html_e('Plugin version:', 'mikesoft-teamvault'); ?></strong> <?php echo esc_html(MSTV_VERSION); ?></li>
            <li><strong><?php esc_html_e('Interface language', 'mikesoft-teamvault'); ?>:</strong> <?php echo esc_html($mstv_interface_language); ?></li>
            <li><strong><?php esc_html_e('Storage directory:', 'mikesoft-teamvault'); ?></strong> <?php echo esc_html($mstv_current_storage_path); ?></li>
            <li><strong><?php esc_html_e('Required capability:', 'mikesoft-teamvault'); ?></strong> <code>manage_private_documents</code></li>
            <li><strong><?php esc_html_e('Authorized roles:', 'mikesoft-teamvault'); ?></strong> <?php echo esc_html(implode(', ', $mstv_roles_with_capability)); ?></li>
        </ul>
    </div>
</div>
