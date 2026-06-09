<?php

defined('ABSPATH') || exit;
?>
<div class="wrap pdm-governance-wrap">
    <div class="mstv-notifications-app" id="mstv-notifications-app">
        <div class="mstv-governance-header">
            <div>
                <h1 class="mstv-governance-title"><?php esc_html_e('Email notifications', 'mikesoft-teamvault'); ?></h1>
                <p class="mstv-governance-subtitle">
                    <?php esc_html_e('Get an email when documents are uploaded, downloaded, or deleted, or when access is denied. Emails are sent to the recipients you choose.', 'mikesoft-teamvault'); ?>
                </p>
            </div>
        </div>

        <div class="mstv-notifications-body">
            <div class="pdm-loading">
                <div class="pdm-spinner"></div>
                <span><?php esc_html_e('Loading...', 'mikesoft-teamvault'); ?></span>
            </div>
        </div>
    </div>
</div>
