<?php

defined('ABSPATH') || exit;

$repo = new PDM_Repository_Logs();
$allowed_per_page = [25, 50, 100, 200];
$current_page = filter_input(INPUT_GET, 'paged', FILTER_VALIDATE_INT);
$selected_per_page = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);

$current_page = $current_page ? max(1, $current_page) : 1;
$selected_per_page = $selected_per_page ?: 50;

if (!in_array($selected_per_page, $allowed_per_page, true)) {
    $selected_per_page = 50;
}

$logs_page = $repo->find_recent_paginated($current_page, $selected_per_page);
$logs = $logs_page['items'];
$pagination = $logs_page['pagination'];

if (!function_exists('pdm_get_action_label')) {
    function pdm_get_action_label(string $action): string
    {
        $labels = [
            'upload' => __('Upload action', 'private-document-manager'),
            'download' => __('Download action', 'private-document-manager'),
            'delete' => __('Deletion', 'private-document-manager'),
            'rename' => __('Rename action', 'private-document-manager'),
            'move' => __('Move action', 'private-document-manager'),
            'create' => __('Creation', 'private-document-manager'),
        ];

        return $labels[$action] ?? $action;
    }
}

if (!function_exists('pdm_get_logs_page_url')) {
    function pdm_get_logs_page_url(int $page, int $perPage): string
    {
        return add_query_arg([
            'page' => 'private-document-manager-logs',
            'paged' => $page,
            'per_page' => $perPage,
        ], admin_url('admin.php'));
    }
}

if (!function_exists('pdm_get_log_target_label')) {
    function pdm_get_log_target_label(string $targetType): string
    {
        return in_array($targetType, ['file', 'files'], true)
            ? __('File', 'private-document-manager')
            : __('Folder', 'private-document-manager');
    }
}
?>
<div class="pdm-wrapper pdm-logs-wrapper">
    <div class="pdm-logs-header">
        <h1 class="pdm-logs-title"><?php esc_html_e('Activity Log', 'private-document-manager'); ?></h1>
        <p class="pdm-logs-desc"><?php esc_html_e('History of operations performed on private documents.', 'private-document-manager'); ?></p>
    </div>

    <div class="pdm-logs-toolbar">
        <div class="pdm-pagination-summary">
            <?php if ($pagination['total_items'] > 0) : ?>
                <?php echo esc_html($pagination['from_item'] . '-' . $pagination['to_item'] . ' ' . __('of', 'private-document-manager') . ' ' . $pagination['total_items'] . ' ' . __('Entries', 'private-document-manager')); ?>
            <?php else : ?>
                <?php echo esc_html('0 ' . __('Entries', 'private-document-manager')); ?>
            <?php endif; ?>
        </div>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="pdm-inline-form">
            <input type="hidden" name="page" value="private-document-manager-logs">
            <input type="hidden" name="paged" value="1">
            <label class="pdm-toolbar-label" for="pdm-logs-per-page"><?php esc_html_e('Per page', 'private-document-manager'); ?></label>
            <select class="pdm-select" name="per_page" id="pdm-logs-per-page">
                <?php foreach ($allowed_per_page as $per_page_option) : ?>
                    <option value="<?php echo esc_attr((string) $per_page_option); ?>" <?php selected($selected_per_page, $per_page_option); ?>>
                        <?php echo esc_html((string) $per_page_option); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="pdm-btn pdm-btn-secondary"><?php esc_html_e('Apply', 'private-document-manager'); ?></button>
        </form>
    </div>

    <div class="pdm-logs-content">
        <?php if (empty($logs)) : ?>
            <div class="pdm-empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                <h3><?php esc_html_e('No logs available', 'private-document-manager'); ?></h3>
                <p><?php esc_html_e('Document operations will be recorded here.', 'private-document-manager'); ?></p>
            </div>
        <?php else : ?>
            <div class="pdm-table-responsive">
                <table class="pdm-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date/Time', 'private-document-manager'); ?></th>
                            <th><?php esc_html_e('User', 'private-document-manager'); ?></th>
                            <th><?php esc_html_e('Action', 'private-document-manager'); ?></th>
                            <th><?php esc_html_e('Type', 'private-document-manager'); ?></th>
                            <th><?php esc_html_e('Details', 'private-document-manager'); ?></th>
                            <th><?php esc_html_e('IP', 'private-document-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <?php $context = json_decode($log->context ?? '{}', true); ?>
                            <tr>
                                <td>
                                    <span class="pdm-log-date"><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log->created_at)); ?></span>
                                </td>
                                <td>
                                    <span class="pdm-log-user"><?php echo esc_html($log->user_login ?: 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="pdm-log-action pdm-log-action--<?php echo esc_attr($log->action); ?>">
                                        <?php echo esc_html(pdm_get_action_label($log->action)); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="pdm-log-type">
                                        <?php echo esc_html(pdm_get_log_target_label((string) $log->target_type)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($context)) : ?>
                                        <span class="pdm-log-context"><?php echo esc_html(wp_json_encode($context, JSON_UNESCAPED_UNICODE)); ?></span>
                                    <?php else : ?>
                                        <span class="pdm-log-context">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="pdm-log-ip"><?php echo esc_html($log->ip_address ?: '-'); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pagination['total_pages'] > 1) : ?>
                <div class="pdm-pagination">
                    <div class="pdm-pagination-summary">
                        <?php echo esc_html($pagination['from_item'] . '-' . $pagination['to_item'] . ' ' . __('of', 'private-document-manager') . ' ' . $pagination['total_items'] . ' ' . __('Entries', 'private-document-manager')); ?>
                    </div>
                    <div class="pdm-pagination-controls">
                        <?php if ($pagination['has_prev']) : ?>
                            <a class="pdm-btn pdm-btn-ghost pdm-pagination-link" href="<?php echo esc_url(pdm_get_logs_page_url($pagination['page'] - 1, $pagination['per_page'])); ?>">
                                <?php esc_html_e('Previous', 'private-document-manager'); ?>
                            </a>
                        <?php else : ?>
                            <button type="button" class="pdm-btn pdm-btn-ghost" disabled><?php esc_html_e('Previous', 'private-document-manager'); ?></button>
                        <?php endif; ?>

                        <span class="pdm-pagination-status">
                            <?php echo esc_html(__('Page', 'private-document-manager') . ' ' . $pagination['page'] . ' ' . __('of', 'private-document-manager') . ' ' . $pagination['total_pages']); ?>
                        </span>

                        <?php if ($pagination['has_next']) : ?>
                            <a class="pdm-btn pdm-btn-ghost pdm-pagination-link" href="<?php echo esc_url(pdm_get_logs_page_url($pagination['page'] + 1, $pagination['per_page'])); ?>">
                                <?php esc_html_e('Next', 'private-document-manager'); ?>
                            </a>
                        <?php else : ?>
                            <button type="button" class="pdm-btn pdm-btn-ghost" disabled><?php esc_html_e('Next', 'private-document-manager'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
