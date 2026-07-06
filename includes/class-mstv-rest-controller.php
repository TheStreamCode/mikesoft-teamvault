<?php

defined('ABSPATH') || exit;

class MSTV_REST_Controller
{
    private const NAMESPACE = 'mstv/v1';
    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE = 200;
    private const AUTO_REINDEX_TTL = 300;

    private MSTV_Settings $settings;
    private MSTV_Auth $auth;
    private MSTV_Storage $storage;
    private MSTV_Validator $validator;
    private MSTV_Repository_Folders $folderRepo;
    private MSTV_Repository_Files $filesRepo;
    private MSTV_Download $download;
    private MSTV_Preview $preview;
    private MSTV_Logger $logger;
    private ?MSTV_Permissions $permissions;
    private ?MSTV_Quota $quota;

    /** @var array<int,bool> set of folder ids that carry explicit permission rules */
    private array $ruledFolderIds = [];

    public function __construct(
        MSTV_Settings $settings,
        MSTV_Auth $auth,
        MSTV_Storage $storage,
        MSTV_Validator $validator,
        MSTV_Repository_Folders $folderRepo,
        MSTV_Repository_Files $filesRepo,
        MSTV_Download $download,
        MSTV_Preview $preview,
        MSTV_Logger $logger,
        ?MSTV_Permissions $permissions = null,
        ?MSTV_Quota $quota = null
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
        $this->permissions = $permissions;
        $this->quota = $quota;
    }

    /**
     * Per-folder permission guard for handlers. Returns a 403 WP_Error when the current
     * user lacks the action on the folder, or null to proceed. When no permission engine
     * is wired (e.g. unit tests) the check is a no-op, preserving prior behavior.
     */
    private function guard_folder_action(?int $folderId, string $action): ?\WP_Error
    {
        if (!$this->permissions) {
            return null;
        }

        if ($this->permissions->current_user_can($folderId, $action)) {
            return null;
        }

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_access_denied($this->auth->get_current_user_id(), $folderId, $action);
        }

        return new \WP_Error(
            'mstv_forbidden',
            __('You do not have permission to perform this action.', 'mikesoft-teamvault'),
            ['status' => 403]
        );
    }

    private function folder_id_of_file(object $file): ?int
    {
        return $file->folder_id ? (int) $file->folder_id : null;
    }

    /**
     * Remove governance data attached to a deleted folder: permission rules and any
     * per-folder notification override. Quotas are principal-based, not folder-based.
     */
    private function cleanup_folder_governance(int $folderId): void
    {
        if (class_exists('MSTV_Repository_Permissions')) {
            (new MSTV_Repository_Permissions())->delete_for_folder($folderId);
        }

        $folderNotifications = get_option('mstv_folder_notifications', []);
        if (is_array($folderNotifications) && isset($folderNotifications[$folderId])) {
            unset($folderNotifications[$folderId]);
            update_option('mstv_folder_notifications', $folderNotifications);
        }
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/browser', [
            'methods' => 'GET',
            'callback' => [$this, 'get_browser_data'],
            'permission_callback' => [$this->auth, 'verify_read_request'],
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
                'order_by' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'display_name',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'ASC',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/folders', [
            'methods' => 'POST',
            'callback' => [$this, 'create_folder'],
            'permission_callback' => [$this->auth, 'verify_write_request'],
            'args' => [
                'name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
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
            'permission_callback' => [$this->auth, 'verify_write_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
                'name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/folders/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_folder'],
            'permission_callback' => [$this->auth, 'verify_delete_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/folders/(?P<id>\d+)/move', [
            'methods' => 'POST',
            'callback' => [$this, 'move_folder'],
            'permission_callback' => [$this->auth, 'verify_write_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
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

        register_rest_route(self::NAMESPACE, '/files/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_file'],
            'permission_callback' => [$this->auth, 'verify_write_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_file'],
            'permission_callback' => [$this->auth, 'verify_write_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
                'display_name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_file'],
            'permission_callback' => [$this->auth, 'verify_delete_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)/move', [
            'methods' => 'POST',
            'callback' => [$this, 'move_file'],
            'permission_callback' => [$this->auth, 'verify_write_request'],
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
            'permission_callback' => [$this->auth, 'verify_read_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)/preview', [
            'methods' => 'GET',
            'callback' => [$this, 'preview_files'],
            'permission_callback' => [$this->auth, 'verify_read_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => [$this->auth, 'verify_read_request'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order_by' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'display_name',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'ASC',
                    'sanitize_callback' => 'sanitize_text_field',
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

        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this->auth, 'verify_admin_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this->auth, 'verify_admin_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this->auth, 'verify_admin_request'],
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
            'permission_callback' => [$this->auth, 'verify_admin_request'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_all'],
            'permission_callback' => [$this->auth, 'verify_read_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/folders/(?P<id>\d+)/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_folder'],
            'permission_callback' => [$this->auth, 'verify_read_request'],
            'args' => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function get_browser_data(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $this->maybe_auto_restore_storage_index();

        $folderId = $request->get_param('folder_id');
        $folderId = $folderId ? (int) $folderId : null;
        $orderBy = sanitize_text_field($request->get_param('order_by') ?: 'display_name');
        $order = sanitize_text_field($request->get_param('order') ?: 'ASC');
        $page = $this->get_page_param($request);
        $perPage = $this->get_per_page_param($request);

        if ($denied = $this->guard_folder_action($folderId, MSTV_Permissions::ACTION_VIEW)) {
            return $denied;
        }

        $this->ruledFolderIds = $this->permissions
            ? array_fill_keys($this->permissions->ruled_folder_ids(), true)
            : [];

        $folders = $this->filter_folders_by_view($this->folderRepo->find_by_parent($folderId));
        $filesPage = $this->filesRepo->find_by_folder_paginated($folderId, $orderBy, $order, $page, $perPage);
        $allFolders = $this->filter_tree_by_view($this->folderRepo->find_all_with_hierarchy());
        $breadcrumb = $folderId ? $this->folderRepo->get_breadcrumb_data($folderId) : [];

        $formattedFolders = array_map([$this, 'format_folder'], $folders);
        $formattedFiles = array_map([$this, 'format_file'], $filesPage['items']);

        return $this->with_no_store(new \WP_REST_Response([
            'success' => true,
            'data' => [
                'current_folder' => $folderId,
                'folders' => $formattedFolders,
                'files' => $formattedFiles,
                'pagination' => $filesPage['pagination'],
                'folder_tree' => $allFolders,
                'breadcrumb' => $breadcrumb,
                'storage_stats' => $this->storage->get_storage_stats($this->filesRepo),
                'permissions' => $this->permissions_for($folderId),
            ],
        ]));
    }

    /**
     * Effective actions of the current user on a folder, as a JS-friendly map.
     * When no engine is wired (tests), every action is granted (prior behavior).
     */
    private function permissions_for(?int $folderId): array
    {
        if (!$this->permissions) {
            return array_fill_keys(MSTV_Permissions::ACTIONS, true);
        }

        return $this->permissions->effective_actions($this->auth->get_current_user_id(), $folderId);
    }

    /**
     * Folders the current user may VIEW, plus whether root-level items are visible.
     * Returns null when no permission engine is wired (tests / legacy free access),
     * meaning "no restriction" so callers keep their historical behavior.
     *
     * @return array{ids: int[], root: bool}|null
     */
    private function viewable_folder_scope(): ?array
    {
        if (!$this->permissions) {
            return null;
        }

        $userId = $this->auth->get_current_user_id();
        $ids = [];

        foreach ($this->folderRepo->find_all() as $folder) {
            $folderId = (int) $folder->id;

            if ($this->permissions->user_can($userId, $folderId, MSTV_Permissions::ACTION_VIEW)) {
                $ids[] = $folderId;
            }
        }

        return [
            'ids' => $ids,
            'root' => $this->permissions->user_can($userId, null, MSTV_Permissions::ACTION_VIEW),
        ];
    }

    /**
     * @param object[] $folders
     * @return object[]
     */
    private function filter_folders_by_view(array $folders): array
    {
        if (!$this->permissions) {
            return $folders;
        }

        $userId = $this->auth->get_current_user_id();

        return array_values(array_filter($folders, function ($folder) use ($userId) {
            return $this->permissions->user_can($userId, (int) $folder->id, MSTV_Permissions::ACTION_VIEW);
        }));
    }

    /**
     * Recursively drop tree nodes the current user cannot view (and their subtree).
     */
    private function filter_tree_by_view(array $nodes): array
    {
        if (!$this->permissions) {
            return $nodes;
        }

        $userId = $this->auth->get_current_user_id();
        $filtered = [];

        foreach ($nodes as $node) {
            if (!$this->permissions->user_can($userId, (int) $node['id'], MSTV_Permissions::ACTION_VIEW)) {
                continue;
            }

            $node['has_rules'] = isset($this->ruledFolderIds[(int) $node['id']]);

            if (!empty($node['children'])) {
                $node['children'] = $this->filter_tree_by_view($node['children']);
                $node['has_children'] = !empty($node['children']);
            }

            $filtered[] = $node;
        }

        return $filtered;
    }

    public function create_folder(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $this->maybe_auto_restore_storage_index();

        $name = sanitize_text_field($request->get_param('name'));
        $parentId = $request->get_param('parent_id');
        $parentId = $parentId ? (int) $parentId : null;

        if ($denied = $this->guard_folder_action($parentId, MSTV_Permissions::ACTION_MANAGE)) {
            return $denied;
        }

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

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_folder_created($folderId, [
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

        if ($denied = $this->guard_folder_action($id, MSTV_Permissions::ACTION_MANAGE)) {
            return $denied;
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

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_folder_renamed($id, $folder->name, $name);
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

        if ($denied = $this->guard_folder_action($id, MSTV_Permissions::ACTION_DELETE)) {
            return $denied;
        }

        $result = $this->storage->delete_folder($id, $this->folderRepo, $this->filesRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 400]);
        }

        $this->folderRepo->delete($id);
        $this->cleanup_folder_governance($id);
        $this->logger->log_delete('folder', $id, $folder->name);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_folder_deleted($id, $folder->name);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function move_folder(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $targetParentId = $this->resolve_folder_id($request->get_param('parent_id'));

        if ($targetParentId instanceof \WP_Error) {
            return $targetParentId;
        }

        $folder = $this->folderRepo->find($id);
        if (!$folder) {
            return new \WP_Error('not_found', __('Folder not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        if ($denied = $this->guard_folder_action($id, MSTV_Permissions::ACTION_MANAGE)) {
            return $denied;
        }

        if ($denied = $this->guard_folder_action($targetParentId, MSTV_Permissions::ACTION_MANAGE)) {
            return $denied;
        }

        $result = $this->storage->move_folder($id, $targetParentId, $this->folderRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 400]);
        }

        $oldParentId = $folder->parent_id !== null ? (int) $folder->parent_id : null;
        $oldRelativePath = (string) $folder->relative_path;

        $this->folderRepo->update($id, [
            'parent_id' => $targetParentId,
            'relative_path' => $result['new_relative_path'],
        ]);

        $this->filesRepo->update_relative_paths_for_folder_rename($oldRelativePath, $result['new_relative_path']);
        $this->folderRepo->update_relative_paths($id, $result['new_relative_path']);
        $this->logger->log_folder_move($id, $folder->name, $oldParentId, $targetParentId);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_folder_moved($id, $oldParentId, $targetParentId);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->format_folder($this->folderRepo->find($id)),
        ]);
    }

    /**
     * Sanitize uploaded file array
     *
     * @param array $files The $_FILES array element
     * @return array Sanitized file array
     */
    private function sanitize_files_array(array $files): array
    {
        return [
            'name' => isset($files['name']) ? sanitize_file_name($files['name']) : '',
            'type' => isset($files['type']) ? sanitize_mime_type($files['type']) : '',
            'tmp_name' => isset($files['tmp_name']) ? (string) $files['tmp_name'] : '',
            'error' => isset($files['error']) ? absint($files['error']) : UPLOAD_ERR_NO_FILE,
            'size' => isset($files['size']) ? absint($files['size']) : 0,
        ];
    }

    private function get_effective_upload_limit(): int
    {
        $limits = array_filter([
            $this->parse_php_size((string) ini_get('post_max_size')),
            $this->parse_php_size((string) ini_get('upload_max_filesize')),
            (int) $this->settings->get_max_file_size(),
        ]);

        return !empty($limits) ? min($limits) : (int) $this->settings->get_max_file_size();
    }

    private function is_missing_upload_likely_size_limit(): bool
    {
        $contentLength = $this->get_request_content_length();

        if ($contentLength <= 0) {
            return false;
        }

        return $contentLength > $this->get_effective_upload_limit();
    }

    private function get_request_content_length(): int
    {
        if (!isset($_SERVER['CONTENT_LENGTH']) || is_array($_SERVER['CONTENT_LENGTH'])) {
            return 0;
        }

        return absint(wp_unslash($_SERVER['CONTENT_LENGTH']));
    }

    private function parse_php_size(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        switch ($unit) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
                break;
        }

        return max(0, (int) $number);
    }

    public function upload_file(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        ob_start();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST nonce is enforced in the permission callback.
        if (empty($_FILES['file'])) {
            ob_end_clean();
            if ($this->is_missing_upload_likely_size_limit()) {
                $limitFormatted = MSTV_Helpers::format_filesize($this->get_effective_upload_limit());
                return new \WP_Error('upload_too_large', sprintf(
                    /* translators: %s: maximum allowed file size (e.g. "50 MB"). */
                    __('The file exceeds the maximum allowed size (%s). Please upload a smaller file.', 'mikesoft-teamvault'),
                    $limitFormatted
                ), ['status' => 400]);
            }

            return new \WP_Error('no_files', __('No file uploaded.', 'mikesoft-teamvault'), ['status' => 400]);
        }

        if (!$this->storage->ensure_storage_directory()) {
            ob_end_clean();
            return new \WP_Error('storage_error', __('Unable to initialize the storage directory.', 'mikesoft-teamvault'), ['status' => 500]);
        }

        // Sanitize uploaded files array using WordPress sanitization functions.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- REST nonce enforced in permission callback, files sanitized via sanitize_files_array().
        $files = $this->sanitize_files_array($_FILES['file']);
        $folderId = $this->resolve_folder_id($request->get_param('folder_id'));

        if ($folderId instanceof \WP_Error) {
            ob_end_clean();
            return $folderId;
        }

        if ($denied = $this->guard_folder_action($folderId, MSTV_Permissions::ACTION_UPLOAD)) {
            ob_end_clean();
            return $denied;
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

        $displayName = MSTV_Helpers::resolve_file_display_name((string) $rawDisplayName, (string) $files['name']);

        if (class_exists('MSTV_Hooks')) {
            $displayName = MSTV_Helpers::resolve_file_display_name((string) MSTV_Hooks::filter_file_name($displayName, (string) $files['name']), (string) $files['name']);
        }

        if ($displayName === '') {
            ob_end_clean();
            return new \WP_Error('validation_error', __('The file name cannot be empty.', 'mikesoft-teamvault'), ['status' => 400]);
        }

        // Serialize the quota check and the metadata insert so two concurrent uploads
        // cannot both pass the check before either row is committed and jointly exceed
        // the quota. The lock spans check→store→insert and is always released.
        if ($this->quota) {
            $this->quota->acquire_upload_lock();
        }

        try {
            if ($this->quota) {
                $quotaError = $this->quota->check_upload($this->auth->get_current_user_id(), (int) $validation['size']);
                if ($quotaError instanceof \WP_Error) {
                    ob_end_clean();
                    return $quotaError;
                }
            }

            $result = $this->storage->store_uploaded_file($files, $folderId, $this->folderRepo);
            if (!$result['success']) {
                ob_end_clean();
                return new \WP_Error('storage_error', $result['error'], ['status' => 500]);
            }

            $storedFileSize = !empty($result['file_size']) ? (int) $result['file_size'] : (int) $validation['size'];

            $fileId = $this->filesRepo->create([
                'folder_id' => $folderId,
                'original_name' => $files['name'],
                'stored_name' => $result['stored_name'],
                'display_name' => $displayName,
                'relative_path' => $result['relative_path'],
                'extension' => $validation['extension'],
                'mime_type' => $validation['mime_type'],
                'file_size' => $storedFileSize,
                'checksum' => $result['checksum'],
                'created_by' => $this->auth->get_current_user_id(),
            ]);
        } finally {
            if ($this->quota) {
                $this->quota->release_upload_lock();
            }
        }

        $this->logger->log_upload($fileId, $displayName);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_file_uploaded($fileId, [
                'display_name' => $displayName,
                'folder_id' => $folderId,
                'extension' => $validation['extension'],
                'mime_type' => $validation['mime_type'],
                'file_size' => $storedFileSize,
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

        $displayName = MSTV_Helpers::sanitize_file_display_name($rawDisplayName);

        $files = $this->filesRepo->find($id);
        if (!$files) {
            return new \WP_Error('not_found', __('File not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        if ($denied = $this->guard_folder_action($this->folder_id_of_file($files), MSTV_Permissions::ACTION_MANAGE)) {
            return $denied;
        }

        if (empty($displayName)) {
            return new \WP_Error('validation_error', __('The name cannot be empty.', 'mikesoft-teamvault'), ['status' => 400]);
        }

        $oldName = $files->display_name;
        $this->filesRepo->rename($id, $displayName);
        $this->logger->log_rename('file', $id, $oldName, $displayName);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_file_renamed($id, $oldName, $displayName);
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

        if ($denied = $this->guard_folder_action($this->folder_id_of_file($files), MSTV_Permissions::ACTION_DELETE)) {
            return $denied;
        }

        $result = $this->storage->delete_file($id, $this->filesRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 500]);
        }

        $this->filesRepo->delete($id);
        $this->logger->log_delete('file', $id, $files->display_name);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_file_deleted($id, [
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

        if ($denied = $this->guard_folder_action($this->folder_id_of_file($files), MSTV_Permissions::ACTION_MANAGE)) {
            return $denied;
        }

        if ($denied = $this->guard_folder_action($targetFolderId, MSTV_Permissions::ACTION_UPLOAD)) {
            return $denied;
        }

        $result = $this->storage->move_file($id, $targetFolderId, $this->filesRepo, $this->folderRepo);
        if (!$result['success']) {
            return new \WP_Error('storage_error', $result['error'], ['status' => 400]);
        }

        $oldFolderId = $files->folder_id;
        $this->filesRepo->move_to_folder($id, $targetFolderId, $result['new_relative_path']);
        $this->logger->log_move($id, $files->display_name, $oldFolderId, $targetFolderId);

        if (class_exists('MSTV_Hooks')) {
            MSTV_Hooks::do_file_moved($id, $oldFolderId ? (int) $oldFolderId : null, $targetFolderId);
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
            return $this->with_no_store(new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'folders' => [],
                    'files' => [],
                    'pagination' => $this->empty_pagination($perPage),
                ],
            ]));
        }

        $folders = [];
        $scope = $this->viewable_folder_scope();
        $filesPage = $scope === null
            ? $this->filesRepo->search_paginated($query, null, $orderBy, $order, $page, $perPage)
            : $this->filesRepo->search_paginated($query, null, $orderBy, $order, $page, $perPage, $scope['ids'], $scope['root']);

        return $this->with_no_store(new \WP_REST_Response([
            'success' => true,
            'data' => [
                'folders' => $folders,
                'files' => array_map([$this, 'format_file'], $filesPage['items']),
                'pagination' => $filesPage['pagination'],
            ],
        ]));
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

    private function with_no_store(\WP_REST_Response $response): \WP_REST_Response
    {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');

        return $response;
    }

    private function maybe_auto_restore_storage_index(): void
    {
        $transientKey = 'mstv_auto_reindex_' . get_current_blog_id();

        if (get_transient($transientKey)) {
            return;
        }

        set_transient($transientKey, 1, self::AUTO_REINDEX_TTL);

        // Normal create/upload/move/delete operations keep the database in sync, so a full
        // recursive storage scan is only needed to self-heal an empty index (for example after
        // the database was reset or migrated without its records). Once the index is populated
        // we skip the scan, keeping it off the request path during normal use on large vaults.
        if ($this->filesRepo->get_count() > 0 || $this->folderRepo->find_all() !== []) {
            return;
        }

        if (!$this->storage->has_reindexable_content()) {
            return;
        }

        $this->storage->reindex_storage_records(
            $this->folderRepo,
            $this->filesRepo,
            $this->auth->get_current_user_id()
        );
    }

    public function get_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'allowed_extensions' => $this->settings->get_allowed_extensions(),
                'max_file_size' => $this->settings->get_max_file_size(),
                'max_file_size_formatted' => MSTV_Helpers::format_filesize($this->settings->get_max_file_size()),
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
            $this->settings->update('mstv_allowed_extensions', $this->settings->sanitize_extensions((string) $settings['allowed_extensions']));
        }

        if (isset($settings['max_file_size'])) {
            $this->settings->update('mstv_max_file_size', absint($settings['max_file_size']));
        }

        if (isset($settings['log_enabled'])) {
            $this->settings->update('mstv_log_enabled', wp_validate_boolean($settings['log_enabled']));
        }

        if (isset($settings['pdf_preview_enabled'])) {
            $this->settings->update('mstv_pdf_preview_enabled', wp_validate_boolean($settings['pdf_preview_enabled']));
        }

        if (isset($settings['remove_data_on_uninstall'])) {
            $this->settings->update('mstv_remove_data_on_uninstall', wp_validate_boolean($settings['remove_data_on_uninstall']));
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function get_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = new MSTV_Repository_Logs();
        $page = $this->get_page_param($request);
        $perPage = $this->get_per_page_param($request);
        $filters = $this->collect_log_filters($request);

        $logsPage = empty($filters)
            ? $repo->find_recent_paginated($page, $perPage)
            : $repo->find_filtered($filters, $page, $perPage);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'items' => array_map([$this, 'format_log'], $logsPage['items']),
                'pagination' => $logsPage['pagination'],
            ],
        ]);
    }

    private function collect_log_filters(\WP_REST_Request $request): array
    {
        $filters = [];

        foreach (['date_from', 'date_to', 'action', 'file_type'] as $key) {
            $value = $request->get_param($key);
            if (is_string($value) && $value !== '') {
                $filters[$key] = sanitize_text_field($value);
            }
        }

        $userId = $request->get_param('user_id');
        if ($userId !== null && $userId !== '') {
            $filters['user_id'] = absint($userId);
        }

        $folderId = $request->get_param('folder_id');
        if ($folderId !== null && $folderId !== '') {
            $filters['folder_id'] = absint($folderId);
        }

        return $filters;
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
            'search_columns' => ['user_login', 'display_name'],
            'number' => 10,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $formatted = array_map(function ($user) {
            return [
                'id' => $user->ID,
                'login' => $user->user_login,
                'display_name' => $user->display_name,
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
        $export = new MSTV_Export(
            $this->storage,
            $this->filesRepo,
            $this->folderRepo,
            $this->auth,
            $this->permissions
        );

        $export->export_all();
    }

    public function export_folder(\WP_REST_Request $request): void
    {
        $folderId = (int) $request->get_param('id');

        $export = new MSTV_Export(
            $this->storage,
            $this->filesRepo,
            $this->folderRepo,
            $this->auth,
            $this->permissions
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
            'created_at_human' => MSTV_Helpers::human_time_diff_mysql($folder->created_at),
            'has_children' => $this->folderRepo->count_children((int) $folder->id) > 0,
            'has_rules' => isset($this->ruledFolderIds[(int) $folder->id]),
            'permissions' => $this->permissions_for((int) $folder->id),
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
            'display_name' => MSTV_Helpers::resolve_file_display_name((string) $files->display_name, (string) $files->original_name),
            'extension' => $files->extension,
            'mime_type' => $runtime['mime_type'],
            'file_size' => $runtime['file_size'],
            'file_size_formatted' => MSTV_Helpers::format_filesize($runtime['file_size']),
            'icon' => MSTV_Helpers::get_file_icon($files->extension),
            'exists_on_disk' => $runtime['exists_on_disk'],
            'is_previewable' => $previewable,
            'is_image' => $runtime['exists_on_disk'] && strpos($runtime['mime_type'], 'image/') === 0,
            'preview_url' => $previewable ? $this->preview->get_preview_url((int) $files->id) : null,
            'download_url' => $runtime['exists_on_disk'] ? $this->download->get_download_url((int) $files->id) : null,
            'created_at' => $files->created_at,
            'created_at_human' => MSTV_Helpers::human_time_diff_mysql($files->created_at),
            'created_by' => (int) $files->created_by,
        ];
    }

    private function get_file_runtime_state(object $files): array
    {
        // Trust the metadata persisted at upload/reindex time for listing performance.
        // A single existence check keeps the "missing from storage" indicator accurate
        // without a per-file MIME read (finfo) and extra path resolutions, which would
        // otherwise run for every row on a page (up to 200) and become the dominant cost
        // on network-backed or large storage.
        $filesystem = $this->storage->get_filesystem();
        $relativePath = (string) $files->relative_path;

        return [
            'exists_on_disk' => $filesystem->is_file($relativePath),
            'mime_type' => (string) $files->mime_type,
            'file_size' => (int) $files->file_size,
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
            'created_at_human' => MSTV_Helpers::human_time_diff_mysql($log->created_at),
        ];
    }
}
