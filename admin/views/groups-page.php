<?php

defined('ABSPATH') || exit;
?>
<div class="wrap pdm-governance-wrap">
    <div class="mstv-groups-app" id="mstv-groups-app">
        <div class="mstv-governance-header">
            <div>
                <h1 class="mstv-governance-title"><?php esc_html_e('TeamVault Groups', 'mikesoft-teamvault'); ?></h1>
                <p class="mstv-governance-subtitle">
                    <?php esc_html_e('Organize users into departments or teams, then grant folder access to a whole group at once.', 'mikesoft-teamvault'); ?>
                </p>
            </div>
            <button type="button" class="pdm-btn pdm-btn-primary" id="mstv-groups-new">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                <span><?php esc_html_e('New group', 'mikesoft-teamvault'); ?></span>
            </button>
        </div>

        <div class="mstv-groups-list">
            <div class="pdm-loading">
                <div class="pdm-spinner"></div>
                <span><?php esc_html_e('Loading...', 'mikesoft-teamvault'); ?></span>
            </div>
        </div>
    </div>
</div>
