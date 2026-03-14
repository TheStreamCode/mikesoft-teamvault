<?php

defined('ABSPATH') || exit;

$repo = new PDM_Repository_Logs();
$logs = $repo->find_recent(100);

function pdm_get_action_label(string $action): string
{
    $labels = [
        'upload' => __('Upload', 'private-document-manager'),
        'download' => __('Download', 'private-document-manager'),
        'delete' => __('Deletion', 'private-document-manager'),
        'rename' => __('Rename', 'private-document-manager'),
        'move' => __('Move', 'private-document-manager'),
        'create' => __('Creation', 'private-document-manager'),
    ];

    return $labels[$action] ?? $action;
}
?>
<div class="pdm-wrapper pdm-logs-wrapper">
    <div class="pdm-logs-header">
        <h1 class="pdm-logs-title"><?php esc_html_e('Activity Log', 'private-document-manager'); ?></h1>
        <p class="pdm-logs-desc"><?php esc_html_e('History of operations performed on private documents.', 'private-document-manager'); ?></p>
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
                                    <?php echo esc_html($log->target_type === 'file' ? __('File', 'private-document-manager') : __('Folder', 'private-document-manager')); ?>
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
        <?php endif; ?>
    </div>
</div>
