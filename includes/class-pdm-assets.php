<?php

defined('ABSPATH') || exit;

class PDM_Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void
    {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, 'private-document-manager') === false) {
            return;
        }

        wp_enqueue_style(
            'pdm-admin',
            PDM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PDM_VERSION
        );

        wp_enqueue_script(
            'pdm-admin',
            PDM_PLUGIN_URL . 'assets/js/admin-app.js',
            [],
            PDM_VERSION,
            true
        );

        wp_localize_script('pdm-admin', 'pdmConfig', [
            'restUrl' => trailingslashit(rest_url('pdm/v1')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url('admin.php'),
            'actionUrl' => admin_url('admin-post.php'),
            'streamNonce' => wp_create_nonce('pdm_stream_action'),
            'browserPerPage' => 50,
            'maxFileSize' => (int) get_option('pdm_max_file_size', 52428800),
            'allowedExtensions' => explode(',', get_option('pdm_allowed_extensions', '')),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'private-document-manager'),
                'confirmDeleteFolder' => __('Are you sure you want to delete this folder? It must be empty.', 'private-document-manager'),
                'uploadSuccess' => __('File uploaded successfully.', 'private-document-manager'),
                'uploadError' => __('Error while uploading the file.', 'private-document-manager'),
                'deleteSuccess' => __('Item deleted successfully.', 'private-document-manager'),
                'deleteError' => __('Error while deleting.', 'private-document-manager'),
                'renameSuccess' => __('Renamed successfully.', 'private-document-manager'),
                'renameError' => __('Error while renaming.', 'private-document-manager'),
                'moveSuccess' => __('File moved successfully.', 'private-document-manager'),
                'moveError' => __('Error while moving the file.', 'private-document-manager'),
                'folderCreateSuccess' => __('Folder created successfully.', 'private-document-manager'),
                'folderCreateError' => __('Error while creating the folder.', 'private-document-manager'),
                'dragDropHere' => __('Drag files here to upload them', 'private-document-manager'),
                'or' => __('or', 'private-document-manager'),
                'browseFiles' => __('Browse files', 'private-document-manager'),
                'emptyState' => __('This folder is empty', 'private-document-manager'),
                'emptyStateDesc' => __('Upload files or create a new folder to get started', 'private-document-manager'),
                'searchPlaceholder' => __('Search files...', 'private-document-manager'),
                'newFolder' => __('New folder', 'private-document-manager'),
                'rename' => __('Rename', 'private-document-manager'),
                'delete' => __('Delete', 'private-document-manager'),
                'download' => __('Download', 'private-document-manager'),
                'preview' => __('Preview', 'private-document-manager'),
                'move' => __('Move', 'private-document-manager'),
                'cancel' => __('Cancel', 'private-document-manager'),
                'confirm' => __('Confirm', 'private-document-manager'),
                'close' => __('Close', 'private-document-manager'),
                'name' => __('Name', 'private-document-manager'),
                'size' => __('Size', 'private-document-manager'),
                'type' => __('Type', 'private-document-manager'),
                'availability' => __('Availability', 'private-document-manager'),
                'created' => __('Created', 'private-document-manager'),
                'folder' => __('Folder', 'private-document-manager'),
                'file' => __('File', 'private-document-manager'),
                'files' => __('files', 'private-document-manager'),
                'folders' => __('folders', 'private-document-manager'),
                'available' => __('Available', 'private-document-manager'),
                'missing' => __('Missing', 'private-document-manager'),
                'selected' => __('selected', 'private-document-manager'),
                'gridView' => __('Grid view', 'private-document-manager'),
                'listView' => __('List view', 'private-document-manager'),
                'sortBy' => __('Sort by', 'private-document-manager'),
                'sortName' => __('Name', 'private-document-manager'),
                'sortDate' => __('Date', 'private-document-manager'),
                'sortSize' => __('Size', 'private-document-manager'),
                'sortAsc' => __('Ascending', 'private-document-manager'),
                'sortDesc' => __('Descending', 'private-document-manager'),
                'previous' => __('Previous', 'private-document-manager'),
                'next' => __('Next', 'private-document-manager'),
                'page' => __('Page', 'private-document-manager'),
                'of' => __('of', 'private-document-manager'),
                'results' => __('results', 'private-document-manager'),
                'rootFolder' => __('Root folder', 'private-document-manager'),
                'open' => __('Open', 'private-document-manager'),
                'status' => __('Status', 'private-document-manager'),
                'folderHasChildren' => __('Contains subfolders', 'private-document-manager'),
                'folderEmpty' => __('No subfolders', 'private-document-manager'),
                'selectItem' => __('Select a file or folder to view details', 'private-document-manager'),
                'untitledFolder' => __('New folder', 'private-document-manager'),
                'moveTo' => __('Move to...', 'private-document-manager'),
                'selectDestination' => __('Select destination', 'private-document-manager'),
                'loading' => __('Loading...', 'private-document-manager'),
                'errorGeneric' => __('An error occurred. Please try again.', 'private-document-manager'),
                'noResults' => __('No results found', 'private-document-manager'),
                'searchNoResultsDesc' => __('Try a different keyword or clear the search.', 'private-document-manager'),
                'fileMissing' => __('File missing from storage', 'private-document-manager'),
                'fileMissingDesc' => __('This record exists in the database, but the binary file is missing from the private storage directory.', 'private-document-manager'),
                'fileMissingShort' => __('Missing from storage', 'private-document-manager'),
                'diskSpace' => __('Disk Space', 'private-document-manager'),
                'used' => __('Used', 'private-document-manager'),
                'free' => __('Free', 'private-document-manager'),
                'total' => __('Total', 'private-document-manager'),
                'searchUsers' => __('Search users...', 'private-document-manager'),
                'noUsersSelected' => __('No users selected', 'private-document-manager'),
                'userNotFound' => __('User not found', 'private-document-manager'),
                'userAlreadyInList' => __('User is already in the list', 'private-document-manager'),
                'remove' => __('Remove', 'private-document-manager'),
                'export' => __('Export', 'private-document-manager'),
                'exportAll' => __('Export All', 'private-document-manager'),
                'exportFolder' => __('Export Folder', 'private-document-manager'),
                'exportAllDesc' => __('Create a ZIP archive with the full private document library.', 'private-document-manager'),
                'exportSelectedFolders' => __('Export Selected Folders', 'private-document-manager'),
                'exportSelectedFoldersDesc' => __('Choose one or more folders to include in the ZIP archive.', 'private-document-manager'),
                'exportNoFoldersSelected' => __('Select at least one folder to export.', 'private-document-manager'),
                'exporting' => __('Exporting...', 'private-document-manager'),
                'exportSuccess' => __('Export completed', 'private-document-manager'),
                'noFoldersAvailable' => __('No folders available for selective export.', 'private-document-manager'),
                'exportError' => __('Error during export', 'private-document-manager'),
            ],
        ]);
    }
}
