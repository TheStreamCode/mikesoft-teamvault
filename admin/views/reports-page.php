<?php

defined('ABSPATH') || exit;
?>
<div class="wrap pdm-governance-wrap">
    <div class="mstv-reports-app" id="mstv-reports-app">
        <div class="mstv-governance-header">
            <div>
                <h1 class="mstv-governance-title"><?php esc_html_e('Access reports', 'mikesoft-teamvault'); ?></h1>
                <p class="mstv-governance-subtitle">
                    <?php esc_html_e('See who viewed or downloaded documents, grouped by user, file, or folder. Export the full activity log to CSV for compliance.', 'mikesoft-teamvault'); ?>
                </p>
            </div>
        </div>

        <div class="mstv-reports-filters">
            <label class="mstv-filter">
                <span><?php esc_html_e('Group by', 'mikesoft-teamvault'); ?></span>
                <select class="pdm-select" id="mstv-report-group">
                    <option value="user"><?php esc_html_e('User', 'mikesoft-teamvault'); ?></option>
                    <option value="file"><?php esc_html_e('File', 'mikesoft-teamvault'); ?></option>
                    <option value="folder"><?php esc_html_e('Folder', 'mikesoft-teamvault'); ?></option>
                </select>
            </label>
            <label class="mstv-filter">
                <span><?php esc_html_e('From', 'mikesoft-teamvault'); ?></span>
                <input type="date" class="pdm-input" id="mstv-report-from">
            </label>
            <label class="mstv-filter">
                <span><?php esc_html_e('To', 'mikesoft-teamvault'); ?></span>
                <input type="date" class="pdm-input" id="mstv-report-to">
            </label>
            <label class="mstv-filter">
                <span><?php esc_html_e('Action', 'mikesoft-teamvault'); ?></span>
                <select class="pdm-select" id="mstv-report-action">
                    <option value=""><?php esc_html_e('Preview and download', 'mikesoft-teamvault'); ?></option>
                    <option value="preview"><?php esc_html_e('Preview', 'mikesoft-teamvault'); ?></option>
                    <option value="download"><?php esc_html_e('Download', 'mikesoft-teamvault'); ?></option>
                </select>
            </label>
            <button type="button" class="pdm-btn pdm-btn-primary" id="mstv-report-apply"><?php esc_html_e('Apply', 'mikesoft-teamvault'); ?></button>
            <button type="button" class="pdm-btn pdm-btn-secondary" id="mstv-report-csv"><?php esc_html_e('Export CSV', 'mikesoft-teamvault'); ?></button>
        </div>

        <div class="mstv-reports-body">
            <p class="pdm-perm-empty"><?php esc_html_e('Apply a filter to see the report.', 'mikesoft-teamvault'); ?></p>
        </div>
    </div>
</div>
