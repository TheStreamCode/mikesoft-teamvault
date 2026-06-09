<?php

defined('ABSPATH') || exit;
?>
<div class="wrap pdm-governance-wrap">
    <div class="mstv-quotas-app" id="mstv-quotas-app">
        <div class="mstv-governance-header">
            <div>
                <h1 class="mstv-governance-title"><?php esc_html_e('Storage quotas', 'mikesoft-teamvault'); ?></h1>
                <p class="mstv-governance-subtitle">
                    <?php esc_html_e('Set per-user or per-group upload limits on your own storage. Uploads that would exceed a limit are blocked; existing files stay accessible. Administrators are never limited.', 'mikesoft-teamvault'); ?>
                </p>
            </div>
        </div>

        <label class="pdm-checkbox-label mstv-quotas-enable">
            <input type="checkbox" id="mstv-quotas-enabled">
            <span class="pdm-checkbox-text"><?php esc_html_e('Enable storage quotas', 'mikesoft-teamvault'); ?></span>
        </label>

        <div class="mstv-quotas-body">
            <div class="pdm-loading">
                <div class="pdm-spinner"></div>
                <span><?php esc_html_e('Loading...', 'mikesoft-teamvault'); ?></span>
            </div>
        </div>
    </div>
</div>
