<?php

defined('ABSPATH') || exit;

$mstv_repo = new MSTV_Repository_Logs();
$mstv_allowed_per_page = [25, 50, 100, 200];
$mstv_current_page = filter_input(INPUT_GET, 'paged', FILTER_VALIDATE_INT);
$mstv_selected_per_page = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);

$mstv_current_page = $mstv_current_page ? max(1, $mstv_current_page) : 1;
$mstv_selected_per_page = $mstv_selected_per_page ?: 50;

if (!in_array($mstv_selected_per_page, $mstv_allowed_per_page, true)) {
    $mstv_selected_per_page = 50;
}

$mstv_logs_page = $mstv_repo->find_recent_paginated($mstv_current_page, $mstv_selected_per_page);
$mstv_logs = $mstv_logs_page['items'];
$mstv_pagination = $mstv_logs_page['pagination'];

if (!function_exists('mstv_get_action_label')) {
    function mstv_get_action_label(string $action): string
    {
        $labels = [
            'upload' => __('Upload', 'mikesoft-teamvault'),
            'download' => __('Download', 'mikesoft-teamvault'),
            'delete' => __('Delete', 'mikesoft-teamvault'),
            'rename' => __('Rename', 'mikesoft-teamvault'),
            'move' => __('Move', 'mikesoft-teamvault'),
            'create' => __('Create', 'mikesoft-teamvault'),
        ];

        return $labels[$action] ?? $action;
    }
}

if (!function_exists('mstv_get_logs_page_url')) {
    function mstv_get_logs_page_url(int $page, int $perPage): string
    {
        return add_query_arg([
            'page' => 'mikesoft-teamvault-logs',
            'paged' => $page,
            'per_page' => $perPage,
        ], admin_url('admin.php'));
    }
}

if (!function_exists('mstv_get_log_target_label')) {
    function mstv_get_log_target_label(string $targetType): string
    {
        return in_array($targetType, ['file', 'files'], true)
            ? __('File', 'mikesoft-teamvault')
            : __('Folder', 'mikesoft-teamvault');
    }
}
?>
<div class="pdm-wrapper pdm-logs-wrapper">
    <div class="pdm-logs-header">
        <h1 class="pdm-logs-title"><?php esc_html_e('Activity Log', 'mikesoft-teamvault'); ?></h1>
        <p class="pdm-logs-desc"><?php esc_html_e('History of operations performed on private documents.', 'mikesoft-teamvault'); ?></p>
    </div>

    <div class="pdm-logs-toolbar">
        <div class="pdm-pagination-summary">
            <?php if ($mstv_pagination['total_items'] > 0) : ?>
                <?php echo esc_html($mstv_pagination['from_item'] . '-' . $mstv_pagination['to_item'] . ' ' . __('of', 'mikesoft-teamvault') . ' ' . $mstv_pagination['total_items'] . ' ' . __('Entries', 'mikesoft-teamvault')); ?>
            <?php else : ?>
                <?php echo esc_html('0 ' . __('Entries', 'mikesoft-teamvault')); ?>
            <?php endif; ?>
        </div>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="pdm-inline-form">
            <input type="hidden" name="page" value="mikesoft-teamvault-logs">
            <input type="hidden" name="paged" value="1">
            <label class="pdm-toolbar-label" for="pdm-logs-per-page"><?php esc_html_e('Per page', 'mikesoft-teamvault'); ?></label>
            <select class="pdm-select" name="per_page" id="pdm-logs-per-page">
                <?php foreach ($mstv_allowed_per_page as $mstv_per_page_option) : ?>
                    <option value="<?php echo esc_attr((string) $mstv_per_page_option); ?>" <?php selected($mstv_selected_per_page, $mstv_per_page_option); ?>>
                        <?php echo esc_html((string) $mstv_per_page_option); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="pdm-btn pdm-btn-secondary"><?php esc_html_e('Apply', 'mikesoft-teamvault'); ?></button>
        </form>
    </div>

    <div class="pdm-logs-content">
        <?php if (empty($mstv_logs)) : ?>
            <div class="pdm-empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                <h3><?php esc_html_e('No logs available', 'mikesoft-teamvault'); ?></h3>
                <p><?php esc_html_e('Document operations will be recorded here.', 'mikesoft-teamvault'); ?></p>
            </div>
        <?php else : ?>
            <div class="pdm-table-responsive">
                <table class="pdm-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date/Time', 'mikesoft-teamvault'); ?></th>
                            <th><?php esc_html_e('User', 'mikesoft-teamvault'); ?></th>
                            <th><?php esc_html_e('Action', 'mikesoft-teamvault'); ?></th>
                            <th><?php esc_html_e('Type', 'mikesoft-teamvault'); ?></th>
                            <th><?php esc_html_e('Details', 'mikesoft-teamvault'); ?></th>
                            <th><?php esc_html_e('IP', 'mikesoft-teamvault'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mstv_logs as $mstv_log) : ?>
                            <?php $mstv_context = json_decode($mstv_log->context ?? '{}', true); ?>
                            <tr>
                                <td>
                                    <span class="pdm-log-date"><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $mstv_log->created_at)); ?></span>
                                </td>
                                <td>
                                    <span class="pdm-log-user"><?php echo esc_html($mstv_log->user_login ?: 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="pdm-log-action pdm-log-action--<?php echo esc_attr($mstv_log->action); ?>">
                                        <?php echo esc_html(mstv_get_action_label($mstv_log->action)); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="pdm-log-type">
                                        <?php echo esc_html(mstv_get_log_target_label((string) $mstv_log->target_type)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($mstv_context)) : ?>
                                        <span class="pdm-log-context"><?php echo esc_html(wp_json_encode($mstv_context, JSON_UNESCAPED_UNICODE)); ?></span>
                                    <?php else : ?>
                                        <span class="pdm-log-context">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="pdm-log-ip"><?php echo esc_html($mstv_log->ip_address ?: '-'); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($mstv_pagination['total_pages'] > 1) : ?>
                <div class="pdm-pagination">
                    <div class="pdm-pagination-summary">
                        <?php echo esc_html($mstv_pagination['from_item'] . '-' . $mstv_pagination['to_item'] . ' ' . __('of', 'mikesoft-teamvault') . ' ' . $mstv_pagination['total_items'] . ' ' . __('Entries', 'mikesoft-teamvault')); ?>
                    </div>
                    <div class="pdm-pagination-controls">
                        <?php if ($mstv_pagination['has_prev']) : ?>
                            <a class="pdm-btn pdm-btn-ghost pdm-pagination-link" href="<?php echo esc_url(mstv_get_logs_page_url($mstv_pagination['page'] - 1, $mstv_pagination['per_page'])); ?>">
                                <?php esc_html_e('Previous', 'mikesoft-teamvault'); ?>
                            </a>
                        <?php else : ?>
                            <button type="button" class="pdm-btn pdm-btn-ghost" disabled><?php esc_html_e('Previous', 'mikesoft-teamvault'); ?></button>
                        <?php endif; ?>

                        <span class="pdm-pagination-status">
                            <?php echo esc_html(__('Page', 'mikesoft-teamvault') . ' ' . $mstv_pagination['page'] . ' ' . __('of', 'mikesoft-teamvault') . ' ' . $mstv_pagination['total_pages']); ?>
                        </span>

                        <?php if ($mstv_pagination['has_next']) : ?>
                            <a class="pdm-btn pdm-btn-ghost pdm-pagination-link" href="<?php echo esc_url(mstv_get_logs_page_url($mstv_pagination['page'] + 1, $mstv_pagination['per_page'])); ?>">
                                <?php esc_html_e('Next', 'mikesoft-teamvault'); ?>
                            </a>
                        <?php else : ?>
                            <button type="button" class="pdm-btn pdm-btn-ghost" disabled><?php esc_html_e('Next', 'mikesoft-teamvault'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
