<?php

defined('ABSPATH') || exit;

/**
 * REST endpoints for the governance suite: TeamVault groups and per-folder permissions.
 * Kept separate from the large file/browser controller to keep both bounded.
 */
class MSTV_REST_Governance_Controller
{
    private const NAMESPACE = 'mstv/v1';

    private MSTV_Auth $auth;
    private MSTV_Repository_Groups $groupsRepo;
    private MSTV_Repository_Permissions $permissionsRepo;
    private MSTV_Repository_Folders $folderRepo;
    private MSTV_Permissions $permissions;
    private ?MSTV_Quota $quota;

    public function __construct(
        MSTV_Auth $auth,
        MSTV_Repository_Groups $groupsRepo,
        MSTV_Repository_Permissions $permissionsRepo,
        MSTV_Repository_Folders $folderRepo,
        MSTV_Permissions $permissions,
        ?MSTV_Quota $quota = null
    ) {
        $this->auth = $auth;
        $this->groupsRepo = $groupsRepo;
        $this->permissionsRepo = $permissionsRepo;
        $this->folderRepo = $folderRepo;
        $this->permissions = $permissions;
        $this->quota = $quota;
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/groups', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list_groups'],
                'permission_callback' => [$this->auth, 'verify_admin_request'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_group'],
                'permission_callback' => [$this->auth, 'verify_admin_request'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/groups/(?P<id>\d+)', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'update_group'],
                'permission_callback' => [$this->auth, 'verify_admin_request'],
                'args' => ['id' => ['required' => true, 'type' => 'integer']],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_group'],
                'permission_callback' => [$this->auth, 'verify_admin_request'],
                'args' => ['id' => ['required' => true, 'type' => 'integer']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/groups/(?P<id>\d+)/members', [
            'methods' => 'POST',
            'callback' => [$this, 'set_members'],
            'permission_callback' => [$this->auth, 'verify_admin_request'],
            'args' => ['id' => ['required' => true, 'type' => 'integer']],
        ]);

        register_rest_route(self::NAMESPACE, '/settings/notifications', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_notifications'],
                'permission_callback' => [$this->auth, 'verify_admin_request'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_notifications'],
                'permission_callback' => [$this->auth, 'verify_admin_request'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/reports/access', [
            'methods' => 'GET',
            'callback' => [$this, 'get_access_report'],
            'permission_callback' => [$this->auth, 'verify_admin_request'],
        ]);

        register_rest_route(self::NAMESPACE, '/quotas', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_quotas'],
                'permission_callback' => [$this->auth, 'verify_admin_request'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_quotas'],
                'permission_callback' => [$this->auth, 'verify_admin_request'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/folders/(?P<id>\d+)/permissions', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_folder_permissions'],
                'permission_callback' => [$this->auth, 'verify_write_request'],
                'args' => ['id' => ['required' => true, 'type' => 'integer']],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'set_folder_permissions'],
                'permission_callback' => [$this->auth, 'verify_write_request'],
                'args' => ['id' => ['required' => true, 'type' => 'integer']],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'reset_folder_permissions'],
                'permission_callback' => [$this->auth, 'verify_write_request'],
                'args' => ['id' => ['required' => true, 'type' => 'integer']],
            ],
        ]);
    }

    // ----- Groups ---------------------------------------------------------

    public function list_groups(\WP_REST_Request $request): \WP_REST_Response
    {
        $groups = array_map(function ($group) {
            $members = $this->groupsRepo->find_members((int) $group->id);

            return [
                'id' => (int) $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'member_count' => count($members),
                'members' => $this->format_members($members),
            ];
        }, $this->groupsRepo->find_all_groups());

        return new \WP_REST_Response(['success' => true, 'data' => $groups]);
    }

    public function create_group(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $name = sanitize_text_field((string) $request->get_param('name'));
        $description = sanitize_text_field((string) $request->get_param('description'));

        if ($name === '') {
            return new \WP_Error('validation_error', __('The group name cannot be empty.', 'mikesoft-teamvault'), ['status' => 400]);
        }

        $slug = $this->unique_slug(sanitize_title($name));

        $groupId = $this->groupsRepo->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'created_by' => $this->auth->get_current_user_id(),
        ]);

        $members = $this->sanitize_ids($request->get_param('members'));
        if (!empty($members)) {
            $this->groupsRepo->set_members($groupId, $members);
        }

        return new \WP_REST_Response(['success' => true, 'data' => ['id' => $groupId]]);
    }

    public function update_group(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $group = $this->groupsRepo->find($id);

        if (!$group) {
            return new \WP_Error('not_found', __('Group not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        $data = [];

        $name = $request->get_param('name');
        if (is_string($name) && $name !== '') {
            $data['name'] = sanitize_text_field($name);
            $data['slug'] = $this->unique_slug(sanitize_title($name), $id);
        }

        $description = $request->get_param('description');
        if ($description !== null) {
            $data['description'] = sanitize_text_field((string) $description);
        }

        if (!empty($data)) {
            $this->groupsRepo->update($id, $data);
        }

        $members = $request->get_param('members');
        if (is_array($members)) {
            $this->groupsRepo->set_members($id, $this->sanitize_ids($members));
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function delete_group(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');

        if (!$this->groupsRepo->find($id)) {
            return new \WP_Error('not_found', __('Group not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        $this->groupsRepo->delete($id);
        $this->permissionsRepo->delete_for_principal('group', $id);
        $this->remove_group_quota($id);

        return new \WP_REST_Response(['success' => true]);
    }

    public function set_members(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');

        if (!$this->groupsRepo->find($id)) {
            return new \WP_Error('not_found', __('Group not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        $this->groupsRepo->set_members($id, $this->sanitize_ids($request->get_param('members')));

        return new \WP_REST_Response(['success' => true]);
    }

    // ----- Notifications --------------------------------------------------

    public function get_notifications(\WP_REST_Request $request): \WP_REST_Response
    {
        $events = get_option('mstv_notify_events', '');
        $events = is_string($events) ? array_filter(array_map('trim', explode(',', $events))) : (array) $events;

        $recipients = get_option('mstv_notify_recipients', ['admins' => true, 'users' => [], 'groups' => []]);
        $recipients = is_array($recipients) ? $recipients : [];

        $users = [];
        foreach ((array) ($recipients['users'] ?? []) as $uid) {
            $users[] = [
                'id' => (int) $uid,
                'display_name' => $this->principal_label('user', (int) $uid),
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'enabled' => (bool) get_option('mstv_notify_enabled', false),
                'events' => array_values($events),
                'available_events' => ['upload', 'download', 'delete', 'access_denied'],
                'recipients' => [
                    'admins' => !empty($recipients['admins']),
                    'users' => $users,
                    'groups' => array_map('intval', (array) ($recipients['groups'] ?? [])),
                ],
                'groups' => array_map(static function ($group) {
                    return ['id' => (int) $group->id, 'name' => $group->name];
                }, $this->groupsRepo->find_all_groups()),
            ],
        ]);
    }

    public function update_notifications(\WP_REST_Request $request): \WP_REST_Response
    {
        update_option('mstv_notify_enabled', (bool) $request->get_param('enabled'));

        $allowedEvents = ['upload', 'download', 'delete', 'access_denied'];
        $events = array_values(array_intersect($allowedEvents, (array) $request->get_param('events')));
        update_option('mstv_notify_events', implode(',', $events));

        $recipients = $request->get_param('recipients');
        $recipients = is_array($recipients) ? $recipients : [];

        update_option('mstv_notify_recipients', [
            'admins' => !empty($recipients['admins']),
            'users' => array_values(array_unique(array_filter(array_map('absint', (array) ($recipients['users'] ?? []))))),
            'groups' => array_values(array_unique(array_filter(array_map('absint', (array) ($recipients['groups'] ?? []))))),
        ]);

        return new \WP_REST_Response(['success' => true]);
    }

    // ----- Access report --------------------------------------------------

    public function get_access_report(\WP_REST_Request $request): \WP_REST_Response
    {
        $groupBy = (string) $request->get_param('group_by');
        $groupBy = in_array($groupBy, ['user', 'file', 'folder'], true) ? $groupBy : 'user';

        $filters = [];
        foreach (['date_from', 'date_to', 'action', 'file_type'] as $k) {
            $v = $request->get_param($k);
            if (is_string($v) && $v !== '') {
                $filters[$k] = sanitize_text_field($v);
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

        $repo = new MSTV_Repository_Logs();
        $rows = $repo->aggregate_access($groupBy, $filters);

        $items = array_map(function ($row) use ($groupBy) {
            return [
                'group_id' => $row->group_id !== null ? (int) $row->group_id : null,
                'label' => $this->report_label($groupBy, $row),
                'events' => (int) $row->events,
                'last_access' => $row->last_access,
            ];
        }, $rows);

        return new \WP_REST_Response([
            'success' => true,
            'data' => ['group_by' => $groupBy, 'items' => $items],
        ]);
    }

    private function report_label(string $groupBy, object $row): string
    {
        if ($groupBy === 'folder') {
            if (empty($row->group_id)) {
                return __('Home (root)', 'mikesoft-teamvault');
            }
            $folder = $this->folderRepo->find((int) $row->group_id);

            return $folder ? $folder->name : ('#' . (int) $row->group_id);
        }

        if ($groupBy === 'user') {
            return $row->label !== null && $row->label !== '' ? (string) $row->label : ('#' . (int) $row->group_id);
        }

        // file
        return $row->label !== null && $row->label !== '' ? (string) $row->label : ('#' . (int) $row->group_id);
    }

    // ----- Quotas ---------------------------------------------------------

    public function get_quotas(\WP_REST_Request $request): \WP_REST_Response
    {
        $quotas = $this->quota ? $this->quota->get_quotas() : [];
        $items = [];

        foreach ($quotas as $key => $bytes) {
            if (!preg_match('/^(user|group):([0-9]+)$/', (string) $key, $m)) {
                continue;
            }

            $type = $m[1];
            $id = (int) $m[2];

            $items[] = [
                'principal_type' => $type,
                'principal_id' => $id,
                'label' => $this->principal_label($type, $id),
                'max_bytes' => (int) $bytes,
                'used_bytes' => $this->quota ? $this->quota_usage($type, $id) : 0,
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'enabled' => $this->quota ? $this->quota->is_enabled() : false,
                'items' => $items,
                'groups' => array_map(static function ($group) {
                    return ['id' => (int) $group->id, 'name' => $group->name];
                }, $this->groupsRepo->find_all_groups()),
            ],
        ]);
    }

    public function update_quotas(\WP_REST_Request $request): \WP_REST_Response
    {
        update_option('mstv_quotas_enabled', (bool) $request->get_param('enabled'));

        $rawItems = $request->get_param('items');
        $map = [];

        if (is_array($rawItems)) {
            foreach ($rawItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $type = ($item['principal_type'] ?? '') === 'group' ? 'group' : 'user';
                $id = absint($item['principal_id'] ?? 0);
                $bytes = (int) ($item['max_bytes'] ?? 0);

                if ($id > 0 && $bytes > 0) {
                    $map[$type . ':' . $id] = $bytes;
                }
            }
        }

        if ($this->quota) {
            $this->quota->set_quotas($map);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    private function quota_usage(string $type, int $id): int
    {
        if (!$this->quota) {
            return 0;
        }

        if ($type === 'group') {
            return $this->quota->group_usage($id);
        }

        return $this->quota->user_usage($id);
    }

    // ----- Folder permissions --------------------------------------------

    public function get_folder_permissions(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $folderId = (int) $request->get_param('id');

        if ($denied = $this->require_manage($folderId)) {
            return $denied;
        }

        $rules = $this->permissionsRepo->find_rules_for_folder($folderId);
        $byPrincipal = [];

        foreach ($rules as $rule) {
            $key = $rule->principal_type . ':' . (int) $rule->principal_id;

            if (!isset($byPrincipal[$key])) {
                $byPrincipal[$key] = [
                    'principal_type' => $rule->principal_type,
                    'principal_id' => (int) $rule->principal_id,
                    'principal_label' => $this->principal_label($rule->principal_type, (int) $rule->principal_id),
                    'actions' => [],
                ];
            }

            $byPrincipal[$key]['actions'][] = $rule->action;
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'folder_id' => $folderId,
                'rules' => array_values($byPrincipal),
                'groups' => array_map(static function ($group) {
                    return ['id' => (int) $group->id, 'name' => $group->name];
                }, $this->groupsRepo->find_all_groups()),
                'available_actions' => MSTV_Permissions::ACTIONS,
                // Warn admins: with rules configured but none on the root, any folder
                // whose ancestry has no rule falls back to full access for all users.
                'default_access_open' => $this->permissionsRepo->has_any_rules()
                    && !$this->permissionsRepo->has_any_rule(0),
            ],
        ]);
    }

    public function set_folder_permissions(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $folderId = (int) $request->get_param('id');

        if ($denied = $this->require_manage($folderId)) {
            return $denied;
        }

        $rawRules = $request->get_param('rules');
        $rules = [];

        if (is_array($rawRules)) {
            foreach ($rawRules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $rules[] = [
                    'principal_type' => ($rule['principal_type'] ?? '') === 'group' ? 'group' : 'user',
                    'principal_id' => absint($rule['principal_id'] ?? 0),
                    'actions' => array_values(array_intersect(
                        MSTV_Permissions::ACTIONS,
                        is_array($rule['actions'] ?? null) ? $rule['actions'] : []
                    )),
                ];
            }
        }

        $this->permissionsRepo->set_rules($folderId, $rules, $this->auth->get_current_user_id());

        return new \WP_REST_Response(['success' => true]);
    }

    public function reset_folder_permissions(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $folderId = (int) $request->get_param('id');

        if ($denied = $this->require_manage($folderId)) {
            return $denied;
        }

        $this->permissionsRepo->delete_for_folder($folderId);

        return new \WP_REST_Response(['success' => true]);
    }

    // ----- Helpers --------------------------------------------------------

    private function require_manage(int $folderId): ?\WP_Error
    {
        // folderId 0 means the virtual root (items with no folder).
        $target = $folderId > 0 ? $folderId : null;

        if ($folderId > 0 && !$this->folderRepo->find($folderId)) {
            return new \WP_Error('not_found', __('Folder not found.', 'mikesoft-teamvault'), ['status' => 404]);
        }

        if (!$this->permissions->current_user_can($target, MSTV_Permissions::ACTION_MANAGE)) {
            return new \WP_Error(
                'mstv_forbidden',
                __('You do not have permission to manage this folder.', 'mikesoft-teamvault'),
                ['status' => 403]
            );
        }

        return null;
    }

    private function unique_slug(string $base, int $excludeId = 0): string
    {
        $base = $base !== '' ? $base : 'group';
        $slug = $base;
        $suffix = 2;

        while ($this->groupsRepo->exists_by_slug($slug, $excludeId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function sanitize_ids($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }

    private function format_members(array $userIds): array
    {
        $members = [];

        foreach ($userIds as $userId) {
            $user = get_user_by('id', $userId);
            $members[] = [
                'id' => (int) $userId,
                'display_name' => $user ? $user->display_name : ('#' . (int) $userId),
            ];
        }

        return $members;
    }

    private function principal_label(string $type, int $id): string
    {
        if ($type === 'group') {
            $group = $this->groupsRepo->find($id);

            return $group ? $group->name : ('#' . $id);
        }

        $user = get_user_by('id', $id);

        return $user ? $user->display_name : ('#' . $id);
    }

    private function remove_group_quota(int $groupId): void
    {
        $quotas = get_option('mstv_quotas', []);
        $key = 'group:' . $groupId;

        if (is_array($quotas) && isset($quotas[$key])) {
            unset($quotas[$key]);
            update_option('mstv_quotas', $quotas);
        }
    }
}
