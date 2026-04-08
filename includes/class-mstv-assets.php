<?php

defined('ABSPATH') || exit;

class MSTV_Assets
{
    private MSTV_Settings $settings;

    public function __construct(MSTV_Settings $settings)
    {
        $this->settings = $settings;
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void
    {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, 'mikesoft-teamvault') === false) {
            return;
        }

        wp_enqueue_style(
            'mstv-admin',
            MSTV_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MSTV_VERSION
        );

        wp_enqueue_script(
            'mstv-admin',
            MSTV_PLUGIN_URL . 'assets/js/admin-app.js',
            [],
            MSTV_VERSION,
            true
        );

        wp_localize_script('mstv-admin', 'mstvConfig', [
            'restUrl' => trailingslashit(rest_url('mstv/v1')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url('admin.php'),
            'actionUrl' => admin_url('admin-post.php'),
            'streamNonce' => wp_create_nonce('mstv_stream_action'),
            'exportSelectionNonce' => wp_create_nonce('mstv_export_selection'),
            'browserPerPage' => 50,
            'maxFileSize' => (int) get_option('mstv_max_file_size', 52428800),
            'allowedExtensions' => $this->settings->get_allowed_extensions(),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'mikesoft-teamvault'),
                'confirmDeleteFolder' => __('Are you sure you want to delete this folder? It must be empty.', 'mikesoft-teamvault'),
                'uploadSuccess' => __('File uploaded successfully.', 'mikesoft-teamvault'),
                'uploadError' => __('Error while uploading the file.', 'mikesoft-teamvault'),
                'deleteSuccess' => __('Item deleted successfully.', 'mikesoft-teamvault'),
                'deleteError' => __('Error while deleting.', 'mikesoft-teamvault'),
                'renameSuccess' => __('Renamed successfully.', 'mikesoft-teamvault'),
                'renameError' => __('Error while renaming.', 'mikesoft-teamvault'),
                'moveSuccess' => __('File moved successfully.', 'mikesoft-teamvault'),
                'moveError' => __('Error while moving the file.', 'mikesoft-teamvault'),
                'folderCreateSuccess' => __('Folder created successfully.', 'mikesoft-teamvault'),
                'folderCreateError' => __('Error while creating the folder.', 'mikesoft-teamvault'),
                'dragDropHere' => __('Drag files here to upload them', 'mikesoft-teamvault'),
                'or' => __('or', 'mikesoft-teamvault'),
                'browseFiles' => __('Browse files', 'mikesoft-teamvault'),
                'emptyState' => __('This folder is empty', 'mikesoft-teamvault'),
                'emptyStateDesc' => __('Upload files or create a new folder to get started', 'mikesoft-teamvault'),
                'searchPlaceholder' => __('Search files...', 'mikesoft-teamvault'),
                'newFolder' => __('New folder', 'mikesoft-teamvault'),
                'rename' => __('Rename', 'mikesoft-teamvault'),
                'delete' => __('Delete', 'mikesoft-teamvault'),
                'download' => __('Download', 'mikesoft-teamvault'),
                'preview' => __('Preview', 'mikesoft-teamvault'),
                'move' => __('Move', 'mikesoft-teamvault'),
                'cancel' => __('Cancel', 'mikesoft-teamvault'),
                'confirm' => __('Confirm', 'mikesoft-teamvault'),
                'close' => __('Close', 'mikesoft-teamvault'),
                'name' => __('Name', 'mikesoft-teamvault'),
                'size' => __('Size', 'mikesoft-teamvault'),
                'type' => __('Type', 'mikesoft-teamvault'),
                'availability' => __('Availability', 'mikesoft-teamvault'),
                'created' => __('Created', 'mikesoft-teamvault'),
                'folder' => __('Folder', 'mikesoft-teamvault'),
                'file' => __('File', 'mikesoft-teamvault'),
                'files' => __('files', 'mikesoft-teamvault'),
                'folders' => __('folders', 'mikesoft-teamvault'),
                'available' => __('Available', 'mikesoft-teamvault'),
                'missing' => __('Missing', 'mikesoft-teamvault'),
                'selected' => __('selected', 'mikesoft-teamvault'),
                'gridView' => __('Grid view', 'mikesoft-teamvault'),
                'listView' => __('List view', 'mikesoft-teamvault'),
                'sortBy' => __('Sort by', 'mikesoft-teamvault'),
                'sortName' => __('Name', 'mikesoft-teamvault'),
                'sortDate' => __('Date', 'mikesoft-teamvault'),
                'sortSize' => __('Size', 'mikesoft-teamvault'),
                'sortAsc' => __('Ascending', 'mikesoft-teamvault'),
                'sortDesc' => __('Descending', 'mikesoft-teamvault'),
                'previous' => __('Previous', 'mikesoft-teamvault'),
                'next' => __('Next', 'mikesoft-teamvault'),
                'page' => __('Page', 'mikesoft-teamvault'),
                'of' => __('of', 'mikesoft-teamvault'),
                'results' => __('results', 'mikesoft-teamvault'),
                'rootFolder' => __('Home', 'mikesoft-teamvault'),
                'open' => __('Open', 'mikesoft-teamvault'),
                'status' => __('Status', 'mikesoft-teamvault'),
                'folderHasChildren' => __('Contains subfolders', 'mikesoft-teamvault'),
                'folderEmpty' => __('No subfolders', 'mikesoft-teamvault'),
                'expand' => __('Expand', 'mikesoft-teamvault'),
                'collapse' => __('Collapse', 'mikesoft-teamvault'),
                'selectItem' => __('Select a file or folder to view details', 'mikesoft-teamvault'),
                'untitledFolder' => __('New folder', 'mikesoft-teamvault'),
                'moveTo' => __('Move to...', 'mikesoft-teamvault'),
                'selectDestination' => __('Select destination', 'mikesoft-teamvault'),
                'loading' => __('Loading...', 'mikesoft-teamvault'),
                'errorGeneric' => __('An error occurred. Please try again.', 'mikesoft-teamvault'),
                'noResults' => __('No results found', 'mikesoft-teamvault'),
                'searchNoResultsDesc' => __('Try a different keyword or clear the search.', 'mikesoft-teamvault'),
                'fileMissing' => __('File missing from storage', 'mikesoft-teamvault'),
                'fileMissingDesc' => __('This record exists in the database, but the binary file is missing from the private storage directory.', 'mikesoft-teamvault'),
                'fileMissingShort' => __('Missing from storage', 'mikesoft-teamvault'),
                'diskSpace' => __('Disk Space', 'mikesoft-teamvault'),
                'used' => __('Used', 'mikesoft-teamvault'),
                'free' => __('Free', 'mikesoft-teamvault'),
                'total' => __('Total', 'mikesoft-teamvault'),
                'searchUsers' => __('Search users...', 'mikesoft-teamvault'),
                'noUsersSelected' => __('No users selected', 'mikesoft-teamvault'),
                'userNotFound' => __('User not found', 'mikesoft-teamvault'),
                'userAlreadyInList' => __('User is already in the list', 'mikesoft-teamvault'),
                'remove' => __('Remove', 'mikesoft-teamvault'),
                'export' => __('Export', 'mikesoft-teamvault'),
                'exportAll' => __('Export All', 'mikesoft-teamvault'),
                'exportFolder' => __('Export Folder', 'mikesoft-teamvault'),
                'exportAllDesc' => __('Create a ZIP archive with the full private document library.', 'mikesoft-teamvault'),
                'exportSelectedFolders' => __('Export Selected Folders', 'mikesoft-teamvault'),
                'exportSelectedFoldersDesc' => __('Choose one or more folders to include in the ZIP archive.', 'mikesoft-teamvault'),
                'exportNoFoldersSelected' => __('Select at least one folder to export.', 'mikesoft-teamvault'),
                'exporting' => __('Exporting...', 'mikesoft-teamvault'),
                'exportSuccess' => __('Export completed', 'mikesoft-teamvault'),
                'noFoldersAvailable' => __('No folders available for selective export.', 'mikesoft-teamvault'),
                'exportError' => __('Error during export', 'mikesoft-teamvault'),
            ],
        ]);
    }
}
