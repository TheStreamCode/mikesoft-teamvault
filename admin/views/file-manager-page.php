<?php

defined('ABSPATH') || exit;

$allowed_extensions = array_filter(array_map('trim', explode(',', (string) get_option('pdm_allowed_extensions', ''))));
$accept_attribute = implode(',', array_map(static fn($ext) => '.' . $ext, $allowed_extensions));
?>
<div class="pdm-wrapper">
    <div class="pdm-app" id="pdm-app">
        <div class="pdm-sidebar" id="pdm-sidebar">
            <div class="pdm-sidebar-header">
                <h2 class="pdm-sidebar-title"><?php esc_html_e('Folders', 'private-document-manager'); ?></h2>
                <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost" id="pdm-new-folder-btn" title="<?php echo esc_attr__('New folder', 'private-document-manager'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                </button>
            </div>
            <div class="pdm-sidebar-search">
                <input type="search" class="pdm-input" id="pdm-search-input" placeholder="<?php echo esc_attr__('Search files...', 'private-document-manager'); ?>">
            </div>
            <div class="pdm-folder-tree" id="pdm-folder-tree">
                <div class="pdm-loading">
                    <div class="pdm-spinner"></div>
                    <span><?php esc_html_e('Loading...', 'private-document-manager'); ?></span>
                </div>
            </div>
            <div class="pdm-storage-indicator" id="pdm-storage-indicator">
                <div class="pdm-storage-title"><?php esc_html_e('Disk Space', 'private-document-manager'); ?></div>
                <div class="pdm-storage-bar">
                    <div class="pdm-storage-bar-fill" id="pdm-storage-bar-fill" style="width: 0%;"></div>
                </div>
                <div class="pdm-storage-stats">
                    <div class="pdm-storage-stat">
                        <span class="pdm-storage-stat-value" id="pdm-storage-used">--</span>
                        <span class="pdm-storage-stat-label"><?php esc_html_e('Used', 'private-document-manager'); ?></span>
                    </div>
                    <div class="pdm-storage-stat">
                        <span class="pdm-storage-stat-value" id="pdm-storage-free">--</span>
                        <span class="pdm-storage-stat-label"><?php esc_html_e('Free', 'private-document-manager'); ?></span>
                    </div>
                    <div class="pdm-storage-stat">
                        <span class="pdm-storage-stat-value" id="pdm-storage-total">--</span>
                        <span class="pdm-storage-stat-label"><?php esc_html_e('Total', 'private-document-manager'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pdm-main">
            <div class="pdm-toolbar">
                <div class="pdm-toolbar-left">
                    <button type="button" class="pdm-btn pdm-btn-ghost pdm-sidebar-toggle" id="pdm-sidebar-toggle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 12h18M3 6h18M3 18h18"/>
                        </svg>
                    </button>
                    <nav class="pdm-breadcrumb" id="pdm-breadcrumb">
                        <a href="#" class="pdm-breadcrumb-item" data-folder-id="">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            </svg>
                            <span><?php esc_html_e('Home', 'private-document-manager'); ?></span>
                        </a>
                    </nav>
                </div>
                <div class="pdm-toolbar-right">
                    <div class="pdm-toolbar-filters">
                        <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-filters-toggle" id="pdm-filters-toggle" title="<?php echo esc_attr__('Filter', 'private-document-manager'); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                            </svg>
                        </button>
                        <div class="pdm-toolbar-filters-dropdown" id="pdm-filters-dropdown">
                            <div class="pdm-toolbar-filters-row">
                                <span class="pdm-toolbar-filters-label"><?php esc_html_e('Sort', 'private-document-manager'); ?></span>
                                <select class="pdm-toolbar-filters-select pdm-filters-sort">
                                    <option value="display_name"><?php esc_html_e('Name', 'private-document-manager'); ?></option>
                                    <option value="created_at"><?php esc_html_e('Date', 'private-document-manager'); ?></option>
                                    <option value="file_size"><?php esc_html_e('Size', 'private-document-manager'); ?></option>
                                </select>
                            </div>
                            <div class="pdm-toolbar-filters-row">
                                <span class="pdm-toolbar-filters-label"><?php esc_html_e('Per page', 'private-document-manager'); ?></span>
                                <select class="pdm-toolbar-filters-select pdm-filters-per-page">
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="pdm-sort-dropdown">
                        <select class="pdm-select" id="pdm-sort-select">
                            <option value="display_name"><?php esc_html_e('Name', 'private-document-manager'); ?></option>
                            <option value="created_at"><?php esc_html_e('Date', 'private-document-manager'); ?></option>
                            <option value="file_size"><?php esc_html_e('Size', 'private-document-manager'); ?></option>
                        </select>
                        <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost" id="pdm-sort-order" title="<?php echo esc_attr__('Order', 'private-document-manager'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 5h10M11 9h7M11 13h4M3 17l4 4 4-4M7 3v18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="pdm-page-size-control">
                        <label class="pdm-toolbar-label" for="pdm-per-page-select"><?php esc_html_e('Per page', 'private-document-manager'); ?></label>
                        <select class="pdm-select" id="pdm-per-page-select">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                    </div>
                    <div class="pdm-view-toggle">
                        <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost active" id="pdm-view-grid" title="<?php echo esc_attr__('Grid view', 'private-document-manager'); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"/>
                                <rect x="14" y="3" width="7" height="7"/>
                                <rect x="3" y="14" width="7" height="7"/>
                                <rect x="14" y="14" width="7" height="7"/>
                            </svg>
                        </button>
                        <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost" id="pdm-view-list" title="<?php echo esc_attr__('List view', 'private-document-manager'); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
                            </svg>
                        </button>
                    </div>
                    <button type="button" class="pdm-btn pdm-btn-secondary" id="pdm-export-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span><?php esc_html_e('Export', 'private-document-manager'); ?></span>
                    </button>
                    <button type="button" class="pdm-btn pdm-btn-primary" id="pdm-upload-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                        </svg>
                        <span><?php esc_html_e('Upload', 'private-document-manager'); ?></span>
                    </button>
                </div>
            </div>

            <div class="pdm-content" id="pdm-content">
                <div class="pdm-loading">
                    <div class="pdm-spinner"></div>
                    <span><?php esc_html_e('Loading...', 'private-document-manager'); ?></span>
                </div>
            </div>
        </div>

        <div class="pdm-details" id="pdm-details">
            <div class="pdm-details-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
                <p><?php esc_html_e('Select a file to view details', 'private-document-manager'); ?></p>
            </div>
        </div>
    </div>

    <div class="pdm-upload-overlay" id="pdm-upload-overlay">
        <div class="pdm-upload-dropzone">
            <div class="pdm-upload-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                </svg>
            </div>
            <p class="pdm-upload-text"><?php esc_html_e('Drag files here to upload them', 'private-document-manager'); ?></p>
            <p class="pdm-upload-subtext"><?php esc_html_e('or', 'private-document-manager'); ?></p>
            <button type="button" class="pdm-btn pdm-btn-primary"><?php esc_html_e('Browse files', 'private-document-manager'); ?></button>
            <input type="file" id="pdm-file-input" multiple accept="<?php echo esc_attr($accept_attribute); ?>">
        </div>
    </div>

    <div class="pdm-modal" id="pdm-modal">
        <div class="pdm-modal-backdrop"></div>
        <div class="pdm-modal-content">
            <div class="pdm-modal-header">
                <h3 class="pdm-modal-title" id="pdm-modal-title"></h3>
                <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-modal-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="pdm-modal-body" id="pdm-modal-body"></div>
            <div class="pdm-modal-footer" id="pdm-modal-footer"></div>
        </div>
    </div>

    <div class="pdm-toast-container" id="pdm-toast-container"></div>

    <div class="pdm-preview-modal" id="pdm-preview-modal">
        <div class="pdm-preview-backdrop"></div>
        <div class="pdm-preview-content">
            <button type="button" class="pdm-btn pdm-btn-icon pdm-btn-ghost pdm-preview-close">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
            <div class="pdm-preview-container" id="pdm-preview-container"></div>
            <div class="pdm-preview-toolbar">
                <button type="button" class="pdm-btn pdm-btn-secondary pdm-preview-download" id="pdm-preview-download">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    <span><?php esc_html_e('Download', 'private-document-manager'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>
