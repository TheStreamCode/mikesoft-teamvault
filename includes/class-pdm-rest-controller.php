<?php

defined('ABSPATH') || exit;

class PDM_REST_Controller
{
    private const NAMESPACE = 'pdm/v1';
    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE = 200;
    private const AUTO_REINDEX_TTL = 300;

    private PDM_Settings $settings;
    private PDM_Auth $auth;
    private PDM_Storage $storage;
    private PDM_Validator $validator;
    private PDM_Repository_Folders $folderRepo;
    private PDM_Repository_Files $filesRepo;
    private PDM_Download $download;
    private PDM_Preview $preview;
    private PDM_Logger $logger;

    public function __construct(
        PDM_Settings $settings,
        PDM_Auth $auth,
        PDM_Storage $storage,
        PDM_Validator $validator,
        PDM_Repository_Folders $folderRepo,
        PDM_Repository_Files $filesRepo,
        PDM_Download $download,
        PDM_Preview $preview,
        PDM_Logger $logger
    ) {
        $this->settings = $settings;
        $this->auth = $auth;
        $this->storage = $storage;
        $this->validator = $validator;
        $this->folderRepo = $folderRepo;
        $this->filesRepo = $filesRepo;
        $this->download = $download;
        $this->preview = $preview;
        $this->logger = $logger;
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/browser', [
            'methods' => 'GET',
            'callback' => [$this, 'get_browser_data'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'folder_id' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return ($value === null || $value === '') ? null : absint($value);
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
                'page' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return max(1, absint($value));
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
                'per_page' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return max(1, min(self::MAX_PER_PAGE, absint($value)));
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/folders', [
            'methods' => 'POST',
            'callback' => [$this, 'create_folder'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'name' => ['required' => true, 'type' => 'string'],
                'parent_id' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return ($value === null || $value === '') ? null : absint($value);
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/folders/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_folder'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
                'name' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/folders/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_folder'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/files/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_file'],
            'permission_callback' => [$this->auth, 'verify_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_file'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_file'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)/move', [
            'methods' => 'POST',
            'callback' => [$this, 'move_file'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
                'folder_id' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return ($value === null || $value === '') ? null : absint($value);
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)/download', [
            'methods' => 'GET',
            'callback' => [$this, 'download_files'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)/preview', [
            'methods' => 'GET',
            'callback' => [$this, 'preview_files'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'q' => ['required' => true, 'type' => 'string'],
                'page' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return max(1, absint($value));
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
                'per_page' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return max(1, min(self::MAX_PER_PAGE, absint($value)));
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this->auth, 'verify_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this->auth, 'verify_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'page' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return max(1, absint($value));
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
                'per_page' => [
                    'required' => false,
                    'sanitize_callback' => static function ($value) {
                        return max(1, min(self::MAX_PER_PAGE, absint($value)));
                    },
                    'validate_callback' => static function ($value) {
                        return $value === null || $value === '' || is_numeric($value);
                    },
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/users/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_users'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'q' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_all'],
            'permission_callback' => [$this->auth, 'verify_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/folders/(?P<id>\d+)/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_folder'],
            'permission_callback' => [$this->auth, 'verify_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function get_browser_data(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->maybe_auto_restore_storage_index();

        $folderId = $request->get_param('folder_id');
        $folderId = $folderId ? (int) $folderId : null;
        $orderBy = sanitize_text_field($request->get_param('order_by') ?: 'display_name');
        $order = sanitize_text_field($request->get_param('order') ?: 'ASC');
        $page = $this->get_page_param($request);
        $perPage = $this->get_per_page_param($request);

        $folders = $this->folderRepo->find_by_parent($folderId);
        $filesPage = $this->filesRepo->find_by_folder_paginated($folderId, $orderBy, $order, $page, $perPage);
        $allFolders = $this->folderRepo->find_all_with_hierarchy();
        $breadcrumb = $folderId ? $this->folderRepo->get_breadcrumb_data($folderId) : [];

        $formattedFolders = array_map([$this, 'format_folder'], $folders);
        $formattedFiles = array_map([$this, 'format_file'], $filesPage['items']);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'current_folder' => $folderId,
                'folders' => $formattedFolders,
                'files' => $formattedFiles,
                'pagination' => $filesPage['pagination'],
                'folder_tree' => $allFolders,
                'breadcrumb' => $breadcrumb,
                'storage_stats' => $this->storage->get_storage_stats($this->filesRepo),
            ],
        ]);
    }

    public function create_folder(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $this->maybe_auto_restore_storage_index();

        $name = sanitize_text_field($request->get_param('name'));
        $parentId = $request->get_param('parent_id');
        $parentId = $parentId ? (int) $parentId : null;

        $validation = $this->validator->validate_folder_name($name);
        if (!$validation['valid']) {
            return new \WP_Error('validation_error', implode(' ', $validation['errors']), ['status' => 400]);
        }

        if ($this->folderRepo->exists_by_name_and_parent($name, $parentId)) {
            return new \WP_Error('duplicate_error', __('Folder already exists.', 'mikesoft-teamvault'), ['status' => 400]);
        }

        $result = $this->storage->create_folder($name, $parentId, $this->folderRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 500]);
        }

        $folderId = $this->folderRepo->create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $result['slug'],
            'relative_path' => $result['relative_path'],
            'created_by' => $this->auth->get_current_user_id(),
        ]);

        $this->logger->log_folder_create($folderId, $name);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_folder_created($folderId, [
                'name' => $name,
                'parent_id' => $parentId,
                'relative_path' => $result['relative_path'],
            ]);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->format_folder($this->folderRepo->find($folderId)),
        ]);
    }

    public function update_folder(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $name = sanitize_text_field($request->get_param('name'));

        $folder = $this->folderRepo->find($id);
        if (!$folder) {
            return new \WP_Error('not_found', __('Folder not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        $validation = $this->validator->validate_folder_name($name);
        if (!$validation['valid']) {
            return new \WP_Error('validation_error', implode(' ', $validation['errors']), ['status' => 400]);
        }

        $result = $this->storage->rename_folder($id, $name, $this->folderRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 500]);
        }

        $this->folderRepo->update($id, [
            'name' => $name,
            'slug' => $result['new_slug'],
            'relative_path' => $result['new_relative_path'],
        ]);

        $this->filesRepo->update_relative_paths_for_folder_rename((string) $folder->relative_path, $result['new_relative_path']);
        $this->folderRepo->update_relative_paths($id, $result['new_relative_path']);
        $this->logger->log_rename('folder', $id, $folder->name, $name);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_folder_renamed($id, $folder->name, $name);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->format_folder($this->folderRepo->find($id)),
        ]);
    }

    public function delete_folder(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');

        $folder = $this->folderRepo->find($id);
        if (!$folder) {
            return new \WP_Error('not_found', __('Folder not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        $result = $this->storage->delete_folder($id, $this->folderRepo, $this->filesRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 400]);
        }

        $this->folderRepo->delete($id);
        $this->logger->log_delete('folder', $id, $folder->name);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_folder_deleted($id, $folder->name);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function upload_file(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        ob_start();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST nonce is enforced in the permission callback.
        if (empty($_FILES['file'])) {
            ob_end_clean();
            return new \WP_Error('no_files', __('No file uploaded.', 'mikesoft-teamvault'), ['status' => 400]);
        }

        if (!$this->storage->ensure_storage_directory()) {
            ob_end_clean();
            return new \WP_Error('storage_error', __('Unable to initialize the storage directory.', 'mikesoft-teamvault'), ['status' => 500]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated by validate_upload_full().
        $files = $_FILES['file'];
        $folderId = $this->resolve_folder_id($request->get_param('folder_id'));

        if ($folderId instanceof \WP_Error) {
            ob_end_clean();
            return $folderId;
        }

        $validation = $this->validator->validate_upload_full($files);
        if (!$validation['valid']) {
            ob_end_clean();
            return new \WP_Error('validation_error', implode(' ', $validation['errors']), ['status' => 400]);
        }

        $rawDisplayName = $request->get_param('display_name');
        $rawDisplayName = is_string($rawDisplayName) && $rawDisplayName !== ''
            ? sanitize_text_field($rawDisplayName)
            : pathinfo((string) $files['name'], PATHINFO_FILENAME);

        $fileNameValidation = $this->validator->validate_file_name($rawDisplayName);
        if (!$fileNameValidation['valid']) {
            ob_end_clean();
            return new \WP_Error('validation_error', implode(' ', $fileNameValidation['errors']), ['status' => 400]);
        }

        $displayName = PDM_Helpers::sanitize_file_display_name($rawDisplayName);

        if (class_exists('PDM_Hooks')) {
            $displayName = PDM_Hooks::filter_file_name($displayName, (string) $files['name']);
        }

        $result = $this->storage->store_uploaded_file($files, $folderId, $this->folderRepo);
        if (!$result['success']) {
            ob_end_clean();
            return new \WP_Error('storage_error', $result['error'], ['status' => 500]);
        }

        $fileId = $this->filesRepo->create([
            'folder_id' => $folderId,
            'original_name' => $files['name'],
            'stored_name' => $result['stored_name'],
            'display_name' => $displayName,
            'relative_path' => $result['relative_path'],
            'extension' => $validation['extension'],
            'mime_type' => $validation['mime_type'],
            'file_size' => $validation['size'],
            'checksum' => $result['checksum'],
            'created_by' => $this->auth->get_current_user_id(),
        ]);

        $this->logger->log_upload($fileId, $displayName);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_file_uploaded($fileId, [
                'display_name' => $displayName,
                'folder_id' => $folderId,
                'extension' => $validation['extension'],
                'mime_type' => $validation['mime_type'],
                'file_size' => $validation['size'],
            ]);
        }

        ob_end_clean();

        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->format_file($this->filesRepo->find($fileId)),
        ]);
    }

    public function update_file(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $rawDisplayName = (string) $request->get_param('display_name');
        $fileNameValidation = $this->validator->validate_file_name($rawDisplayName);

        if (!$fileNameValidation['valid']) {
            return new \WP_Error('validation_error', implode(' ', $fileNameValidation['errors']), ['status' => 400]);
        }

        $displayName = PDM_Helpers::sanitize_file_display_name($rawDisplayName);

        $files = $this->filesRepo->find($id);
        if (!$files) {
            return new \WP_Error('not_found', __('File not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        if (empty($displayName)) {
            return new \WP_Error('validation_error', __('The name cannot be empty.', 'mikesoft-teamvault'), ['status' => 400]);
        }

        $oldName = $files->display_name;
        $this->filesRepo->rename($id, $displayName);
        $this->logger->log_rename('file', $id, $oldName, $displayName);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_file_renamed($id, $oldName, $displayName);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->format_file($this->filesRepo->find($id)),
        ]);
    }

    public function delete_file(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');

        $files = $this->filesRepo->find($id);
        if (!$files) {
            return new \WP_Error('not_found', __('File not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        $result = $this->storage->delete_file($id, $this->filesRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 500]);
        }

        $this->filesRepo->delete($id);
        $this->logger->log_delete('file', $id, $files->display_name);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_file_deleted($id, [
                'display_name' => $files->display_name,
                'folder_id' => $files->folder_id ? (int) $files->folder_id : null,
                'relative_path' => $files->relative_path,
            ]);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function move_file(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $targetFolderId = $this->resolve_folder_id($request->get_param('folder_id'));

        if ($targetFolderId instanceof \WP_Error) {
            return $targetFolderId;
        }

        $files = $this->filesRepo->find($id);
        if (!$files) {
            return new \WP_Error('not_found', __('File not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        $result = $this->storage->move_file($id, $targetFolderId, $this->filesRepo, $this->folderRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 400]);
        }

        $oldFolderId = $files->folder_id;
        $this->filesRepo->move_to_folder($id, $targetFolderId, $result['new_relative_path']);
        $this->logger->log_move($id, $files->display_name, $oldFolderId, $targetFolderId);

        if (class_exists('PDM_Hooks')) {
            PDM_Hooks::do_file_moved($id, $oldFolderId ? (int) $oldFolderId : null, $targetFolderId);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->format_file($this->filesRepo->find($id)),
        ]);
    }

    public function download_files(\WP_REST_Request $request): void
    {
        $id = (int) $request->get_param('id');
        $this->download->serve($id);
    }

    private function resolve_folder_id($rawFolderId): int|\WP_Error|null
    {
        if ($rawFolderId === null || $rawFolderId === '') {
            return null;
        }

        if (!is_string($rawFolderId) && !is_int($rawFolderId)) {
            return new \WP_Error('not_found', __('Folder not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        if (is_string($rawFolderId) && !preg_match('/^\d+$/', $rawFolderId)) {
            return new \WP_Error('not_found', __('Folder not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        $folderId = (int) $rawFolderId;

        if ($folderId <= 0) {
            return new \WP_Error('not_found', __('Folder not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        if (!$this->folderRepo->find($folderId)) {
            return new \WP_Error('not_found', __('Folder not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        return $folderId;
    }

    public function preview_files(\WP_REST_Request $request): void
    {
        $id = (int) $request->get_param('id');
        $this->preview->serve($id);
    }

    public function search(\WP_REST_Request $request): \WP_REST_Response
    {
        $query = sanitize_text_field($request->get_param('q'));
        $page = $this->get_page_param($request);
        $perPage = $this->get_per_page_param($request);
        $orderBy = sanitize_text_field($request->get_param('order_by') ?: 'display_name');
        $order = sanitize_text_field($request->get_param('order') ?: 'ASC');

        if (strlen($query) < 2) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'folders' => [],
                    'files' => [],
                    'pagination' => $this->empty_pagination($perPage),
                ],
            ]);
        }

        $folders = [];
        $filesPage = $this->filesRepo->search_paginated($query, null, $orderBy, $order, $page, $perPage);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'folders' => $folders,
                'files' => array_map([$this, 'format_file'], $filesPage['items']),
                'pagination' => $filesPage['pagination'],
            ],
        ]);
    }

    private function get_page_param(\WP_REST_Request $request): int
    {
        return max(1, (int) ($request->get_param('page') ?: 1));
    }

    private function get_per_page_param(\WP_REST_Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, (int) ($request->get_param('per_page') ?: self::DEFAULT_PER_PAGE)));
    }

    private function empty_pagination(int $perPage): array
    {
        return [
            'page' => 1,
            'per_page' => $perPage,
            'total_items' => 0,
            'total_pages' => 0,
            'has_prev' => false,
            'has_next' => false,
            'from_item' => 0,
            'to_item' => 0,
        ];
    }

    private function maybe_auto_restore_storage_index(): void
    {
        $transientKey = 'pdm_auto_reindex_' . get_current_blog_id();

        if (get_transient($transientKey)) {
            return;
        }

        if (!$this->storage->has_reindexable_content()) {
            set_transient($transientKey, 1, self::AUTO_REINDEX_TTL);
            return;
        }

        $result = $this->storage->reindex_storage_records(
            $this->folderRepo,
            $this->filesRepo,
            $this->auth->get_current_user_id()
        );

        if (!empty($result['success'])) {
            set_transient($transientKey, 1, self::AUTO_REINDEX_TTL);
        }
    }

    public function get_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'allowed_extensions' => $this->settings->get_allowed_extensions(),
                'max_file_size' => $this->settings->get_max_file_size(),
                'max_file_size_formatted' => PDM_Helpers::format_filesize($this->settings->get_max_file_size()),
                'log_enabled' => $this->settings->is_log_enabled(),
                'pdf_preview_enabled' => $this->settings->is_pdf_preview_enabled(),
                'remove_data_on_uninstall' => $this->settings->should_remove_data_on_uninstall(),
            ],
        ]);
    }

    public function update_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = $request->get_json_params();

        if (isset($settings['allowed_extensions'])) {
            $this->settings->update('pdm_allowed_extensions', $this->settings->sanitize_extensions((string) $settings['allowed_extensions']));
        }

        if (isset($settings['max_file_size'])) {
            $this->settings->update('pdm_max_file_size', absint($settings['max_file_size']));
        }

        if (isset($settings['log_enabled'])) {
            $this->settings->update('pdm_log_enabled', (bool) $settings['log_enabled']);
        }

        if (isset($settings['pdf_preview_enabled'])) {
            $this->settings->update('pdm_pdf_preview_enabled', (bool) $settings['pdf_preview_enabled']);
        }

        if (isset($settings['remove_data_on_uninstall'])) {
            $this->settings->update('pdm_remove_data_on_uninstall', (bool) $settings['remove_data_on_uninstall']);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function get_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = new PDM_Repository_Logs();
        $page = $this->get_page_param($request);
        $perPage = $this->get_per_page_param($request);
        $logsPage = $repo->find_recent_paginated($page, $perPage);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'items' => array_map([$this, 'format_log'], $logsPage['items']),
                'pagination' => $logsPage['pagination'],
            ],
        ]);
    }

    public function search_users(\WP_REST_Request $request): \WP_REST_Response
    {
        $query = sanitize_text_field($request->get_param('q'));

        if (strlen($query) < 2) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => [],
            ]);
        }

        $users = get_users([
            'search' => "*{$query}*",
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 10,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $formatted = array_map(function ($user) {
            return [
                'id' => $user->ID,
                'login' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID, ['size' => 32]),
            ];
        }, $users);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    public function export_all(\WP_REST_Request $request): void
    {
        $export = new PDM_Export(
            $this->storage,
            $this->filesRepo,
            $this->folderRepo,
            $this->auth
        );

        $export->export_all();
    }

    public function export_folder(\WP_REST_Request $request): void
    {
        $folderId = (int) $request->get_param('id');

        $export = new PDM_Export(
            $this->storage,
            $this->filesRepo,
            $this->folderRepo,
            $this->auth
        );

        $export->export_folder($folderId);
    }

    private function format_folder(object $folder): array
    {
        return [
            'id' => (int) $folder->id,
            'parent_id' => $folder->parent_id ? (int) $folder->parent_id : null,
            'name' => $folder->name,
            'slug' => $folder->slug,
            'created_at' => $folder->created_at,
            'created_at_human' => PDM_Helpers::human_time_diff_mysql($folder->created_at),
            'has_children' => $this->folderRepo->count_children((int) $folder->id) > 0,
        ];
    }

    private function format_file(object $files): array
    {
        $runtime = $this->get_file_runtime_state($files);

        $previewable = $runtime['exists_on_disk'] && $this->preview->can_preview((object) [
            'extension' => $files->extension,
            'mime_type' => $runtime['mime_type'],
        ]);

        return [
            'id' => (int) $files->id,
            'folder_id' => $files->folder_id ? (int) $files->folder_id : null,
            'original_name' => $files->original_name,
            'display_name' => $files->display_name,
            'extension' => $files->extension,
            'mime_type' => $runtime['mime_type'],
            'file_size' => $runtime['file_size'],
            'file_size_formatted' => PDM_Helpers::format_filesize($runtime['file_size']),
            'icon' => PDM_Helpers::get_file_icon($files->extension),
            'exists_on_disk' => $runtime['exists_on_disk'],
            'is_previewable' => $previewable,
            'is_image' => $runtime['exists_on_disk'] && strpos($runtime['mime_type'], 'image/') === 0,
            'preview_url' => $previewable ? $this->preview->get_preview_url((int) $files->id) : null,
            'download_url' => $runtime['exists_on_disk'] ? $this->download->get_download_url((int) $files->id) : null,
            'created_at' => $files->created_at,
            'created_at_human' => PDM_Helpers::human_time_diff_mysql($files->created_at),
            'created_by' => (int) $files->created_by,
        ];
    }

    private function get_file_runtime_state(object $files): array
    {
        $filesystem = $this->storage->get_filesystem();
        $relativePath = (string) $files->relative_path;
        $existsOnDisk = $filesystem->is_file($relativePath);
        $mimeType = (string) $files->mime_type;
        $fileSize = (int) $files->file_size;

        if ($existsOnDisk) {
            $detectedMime = $filesystem->get_mime_type($relativePath);
            $detectedSize = $filesystem->get_file_size($relativePath);

            if (!empty($detectedMime)) {
                $mimeType = $detectedMime;
            }

            if ($detectedSize > 0) {
                $fileSize = $detectedSize;
            }
        }

        return [
            'exists_on_disk' => $existsOnDisk,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
        ];
    }

    private function format_log(object $log): array
    {
        return [
            'id' => (int) $log->id,
            'user_id' => (int) $log->user_id,
            'user_login' => $log->user_login ?? '',
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id ? (int) $log->target_id : null,
            'context' => json_decode($log->context ?? '{}', true),
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at,
            'created_at_human' => PDM_Helpers::human_time_diff_mysql($log->created_at),
        ];
    }
}
