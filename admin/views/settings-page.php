<?php

defined('ABSPATH') || exit;

$interface_language = (string) get_option('pdm_interface_language', 'en');
$use_user_whitelist = (bool) get_option('pdm_use_user_whitelist', false);
$allowed_users = get_option('pdm_allowed_users', []);
$allowed_extensions = (string) get_option('pdm_allowed_extensions', '');
$max_file_size = (int) get_option('pdm_max_file_size', 52428800);
$pdf_preview_enabled = (bool) get_option('pdm_pdf_preview_enabled', true);
$log_enabled = (bool) get_option('pdm_log_enabled', true);
$remove_data_on_uninstall = (bool) get_option('pdm_remove_data_on_uninstall', false);
$settings_saved = (bool) get_transient('pdm_settings_saved_' . get_current_user_id());
$settings_error = get_transient('pdm_settings_error_' . get_current_user_id());

if ($settings_saved) {
    delete_transient('pdm_settings_saved_' . get_current_user_id());
}

if ($settings_error !== false) {
    delete_transient('pdm_settings_error_' . get_current_user_id());
}

$allowed_users = is_array($allowed_users) ? $allowed_users : [];
$current_storage_path = (new PDM_Settings())->get_storage_path();
$roles_with_capability = PDM_Capabilities::get_roles_with_capability();
$max_server_upload_size = (int) wp_max_upload_size();
?>
<div class="pdm-wrapper pdm-settings-wrapper">
    <?php if ($settings_saved) : ?>
        <div class="pdm-notice pdm-notice-success">
            <?php esc_html_e('Settings saved successfully.', 'private-document-manager'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($settings_error)) : ?>
        <div class="pdm-notice pdm-notice-error">
            <?php echo esc_html((string) $settings_error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($cleanup_result) && isset($cleanup_result['deleted_count'])) : ?>
        <div class="pdm-notice pdm-notice-success">
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %d: number of deleted orphaned records. */
                    __('Cleanup completed. Removed %d orphaned file records.', 'private-document-manager'),
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
                    __('Reindex completed. Restored %1$d folders and %2$d files from storage.', 'private-document-manager'),
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
        <h1 class="pdm-settings-title"><?php esc_html_e('Private Document Manager Settings', 'private-document-manager'); ?></h1>
        <p class="pdm-settings-desc"><?php esc_html_e('Configure the plugin options for private document management.', 'private-document-manager'); ?></p>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pdm-settings-form">
        <input type="hidden" name="action" value="pdm_save_settings">
        <?php wp_nonce_field('pdm_settings_nonce', 'pdm_settings_nonce'); ?>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Interface language', 'private-document-manager'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-field-label" for="pdm_interface_language">
                    <?php esc_html_e('Interface language', 'private-document-manager'); ?>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('Choose the plugin interface language. The default language is English.', 'private-document-manager'); ?></p>
                <select id="pdm_interface_language" name="pdm_interface_language" class="pdm-select">
                    <option value="en" <?php selected($interface_language, 'en'); ?>><?php esc_html_e('English (default)', 'private-document-manager'); ?></option>
                    <option value="it" <?php selected($interface_language, 'it'); ?>><?php esc_html_e('Italian', 'private-document-manager'); ?></option>
                </select>
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('User Access', 'private-document-manager'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-checkbox-label">
                    <input
                        type="checkbox"
                        id="pdm_use_user_whitelist"
                        name="pdm_use_user_whitelist"
                        value="1"
                        <?php checked($use_user_whitelist, true); ?>
                    >
                    <span class="pdm-checkbox-text"><?php esc_html_e('Limit access to specific users', 'private-document-manager'); ?></span>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('If enabled, users still need the plugin capability and must also be present in this list. Include your current account before saving to avoid locking yourself out.', 'private-document-manager'); ?></p>
            </div>

            <div class="pdm-field pdm-user-whitelist-field" style="<?php echo esc_attr($use_user_whitelist ? '' : 'display:none;'); ?>">
                <label class="pdm-field-label"><?php esc_html_e('Authorized users', 'private-document-manager'); ?></label>

                <div class="pdm-user-search">
                    <input
                        type="text"
                        id="pdm-user-search"
                        class="pdm-input"
                        placeholder="<?php echo esc_attr__('Search users...', 'private-document-manager'); ?>"
                        autocomplete="off"
                    >
                    <div id="pdm-user-results" class="pdm-user-results"></div>
                </div>

                <div id="pdm-allowed-users" class="pdm-allowed-users">
                    <?php foreach ($allowed_users as $user_id) : ?>
                        <?php $user = get_user_by('id', $user_id); ?>
                        <?php if ($user) : ?>
                            <div class="pdm-user-tag" data-user-id="<?php echo esc_attr($user_id); ?>">
                                <span class="pdm-user-name"><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></span>
                                <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-remove-user" title="<?php echo esc_attr__('Remove', 'private-document-manager'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                                <input type="hidden" name="pdm_allowed_users[]" value="<?php echo esc_attr($user_id); ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <p id="pdm-no-users" class="pdm-field-desc" style="<?php echo esc_attr(empty($allowed_users) ? '' : 'display:none;'); ?>">
                    <?php esc_html_e('No users selected', 'private-document-manager'); ?>
                </p>
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Files', 'private-document-manager'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-field-label" for="pdm_allowed_extensions">
                    <?php esc_html_e('Allowed extensions', 'private-document-manager'); ?>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('Comma-separated list of allowed file extensions for upload.', 'private-document-manager'); ?></p>
                <textarea
                    id="pdm_allowed_extensions"
                    name="pdm_allowed_extensions"
                    class="pdm-textarea"
                    rows="3"
                ><?php echo esc_textarea($allowed_extensions); ?></textarea>
            </div>

            <div class="pdm-field">
                <label class="pdm-field-label" for="pdm_max_file_size">
                    <?php esc_html_e('Maximum file size (bytes)', 'private-document-manager'); ?>
                </label>
                <p class="pdm-field-desc">
                    <?php esc_html_e('Maximum size allowed for each file.', 'private-document-manager'); ?>
                    <br>
                    <?php esc_html_e('Current:', 'private-document-manager'); ?>
                    <strong><?php echo esc_html(PDM_Helpers::format_filesize($max_file_size)); ?></strong>
                    -
                    <?php esc_html_e('Server limit:', 'private-document-manager'); ?>
                    <strong><?php echo esc_html(PDM_Helpers::format_filesize($max_server_upload_size)); ?></strong>
                </p>
                <input
                    type="number"
                    id="pdm_max_file_size"
                    name="pdm_max_file_size"
                    class="pdm-input"
                    value="<?php echo esc_attr((string) $max_file_size); ?>"
                    min="1"
                    max="<?php echo esc_attr((string) $max_server_upload_size); ?>"
                >
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Preview', 'private-document-manager'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-checkbox-label">
                    <input
                        type="checkbox"
                        id="pdm_pdf_preview_enabled"
                        name="pdm_pdf_preview_enabled"
                        value="1"
                        <?php checked($pdf_preview_enabled, true); ?>
                    >
                    <span class="pdm-checkbox-text"><?php esc_html_e('Enable inline PDF preview', 'private-document-manager'); ?></span>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('If enabled, PDFs will be displayed directly in the browser. Some browsers may not support this feature.', 'private-document-manager'); ?></p>
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Log', 'private-document-manager'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-checkbox-label">
                    <input
                        type="checkbox"
                        id="pdm_log_enabled"
                        name="pdm_log_enabled"
                        value="1"
                        <?php checked($log_enabled, true); ?>
                    >
                    <span class="pdm-checkbox-text"><?php esc_html_e('Enable activity log', 'private-document-manager'); ?></span>
                </label>
                <p class="pdm-field-desc"><?php esc_html_e('Record upload, download, delete and move operations for files.', 'private-document-manager'); ?></p>
            </div>
        </div>

        <div class="pdm-settings-section">
            <h2 class="pdm-section-title"><?php esc_html_e('Maintenance', 'private-document-manager'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-field-label"><?php esc_html_e('Orphaned file records', 'private-document-manager'); ?></label>
                <p class="pdm-field-desc">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: number of orphaned records. */
                            __('Found %d database records whose files are missing from the private storage directory.', 'private-document-manager'),
                            (int) $orphaned_files_count
                        )
                    );
                    ?>
                </p>
                <p class="pdm-field-desc"><?php esc_html_e('Use cleanup after a local migration if the private storage folder was not copied.', 'private-document-manager'); ?></p>
                <div class="pdm-inline-form pdm-maintenance-form">
                    <button type="submit" form="pdm-cleanup-orphans-form" class="pdm-btn pdm-btn-secondary" <?php disabled((int) $orphaned_files_count, 0); ?>>
                        <?php esc_html_e('Clean orphaned records', 'private-document-manager'); ?>
                    </button>
                </div>
            </div>

            <div class="pdm-field">
                <label class="pdm-field-label"><?php esc_html_e('Reindex storage', 'private-document-manager'); ?></label>
                <p class="pdm-field-desc"><?php esc_html_e('Restore folder and file records that still exist on disk but are missing from the database.', 'private-document-manager'); ?></p>
                <p class="pdm-field-desc"><?php esc_html_e('Use this after an incomplete uninstall or a migration where the storage directory remained available.', 'private-document-manager'); ?></p>
                <div class="pdm-inline-form pdm-maintenance-form">
                    <button type="submit" form="pdm-reindex-storage-form" class="pdm-btn pdm-btn-secondary">
                        <?php esc_html_e('Reindex storage', 'private-document-manager'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="pdm-settings-section pdm-settings-section--danger">
            <h2 class="pdm-section-title"><?php esc_html_e('Uninstall', 'private-document-manager'); ?></h2>

            <div class="pdm-field">
                <label class="pdm-checkbox-label pdm-checkbox-label--danger">
                    <input
                        type="checkbox"
                        id="pdm_remove_data_on_uninstall"
                        name="pdm_remove_data_on_uninstall"
                        value="1"
                        <?php checked($remove_data_on_uninstall, true); ?>
                    >
                    <span class="pdm-checkbox-text"><?php esc_html_e('Delete all data on uninstall', 'private-document-manager'); ?></span>
                </label>
                <p class="pdm-field-desc">
                    <strong><?php esc_html_e('Warning:', 'private-document-manager'); ?></strong>
                    <?php esc_html_e('If enabled, uninstalling the plugin will remove all files, folders, logs and settings. This action is irreversible.', 'private-document-manager'); ?>
                </p>
            </div>
        </div>

        <div class="pdm-settings-actions">
            <?php submit_button(esc_html__('Save Settings', 'private-document-manager'), 'primary pdm-btn-primary', 'submit', false); ?>
        </div>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pdm-cleanup-orphans-form">
        <input type="hidden" name="action" value="pdm_cleanup_orphans">
        <?php wp_nonce_field('pdm_cleanup_orphans', 'pdm_cleanup_orphans_nonce'); ?>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pdm-reindex-storage-form">
        <input type="hidden" name="action" value="pdm_reindex_storage">
        <?php wp_nonce_field('pdm_reindex_storage', 'pdm_reindex_storage_nonce'); ?>
    </form>

    <div class="pdm-settings-info">
        <h3><?php esc_html_e('Information', 'private-document-manager'); ?></h3>
        <ul>
            <li><strong><?php esc_html_e('Plugin version:', 'private-document-manager'); ?></strong> <?php echo esc_html(PDM_VERSION); ?></li>
            <li><strong><?php esc_html_e('Interface language', 'private-document-manager'); ?>:</strong> <?php echo esc_html($interface_language); ?></li>
            <li><strong><?php esc_html_e('Storage directory:', 'private-document-manager'); ?></strong> <?php echo esc_html($current_storage_path); ?></li>
            <li><strong><?php esc_html_e('Required capability:', 'private-document-manager'); ?></strong> <code>manage_private_documents</code></li>
            <li><strong><?php esc_html_e('Authorized roles:', 'private-document-manager'); ?></strong> <?php echo esc_html(implode(', ', $roles_with_capability)); ?></li>
        </ul>
    </div>
</div>
