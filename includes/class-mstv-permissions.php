<?php

defined('ABSPATH') || exit;

/**
 * Central per-folder permission resolver.
 *
 * Model: grant-only rules. A rule row means "this principal is granted this action
 * on this folder". Effective permissions for a user are the UNION of the rules that
 * apply to the user and to the groups the user belongs to, evaluated on the nearest
 * ancestor folder that has any explicit rule (child rules override inherited ones by
 * virtue of being closer). When no ancestor has any rule, access falls back to the
 * historical free behavior (capability + optional whitelist), so existing vaults with
 * no rules keep working exactly as before. Administrators always retain full access.
 */
class MSTV_Permissions
{
    public const ACTION_VIEW = 'view';
    public const ACTION_UPLOAD = 'upload';
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_DELETE = 'delete';
    public const ACTION_MANAGE = 'manage';

    public const ACTIONS = [
        self::ACTION_VIEW,
        self::ACTION_UPLOAD,
        self::ACTION_DOWNLOAD,
        self::ACTION_DELETE,
        self::ACTION_MANAGE,
    ];

    private MSTV_Repository_Folders $folderRepo;
    private MSTV_Repository_Groups $groupsRepo;
    private MSTV_Repository_Permissions $permissionsRepo;
    private MSTV_Settings $settings;

    /** @var array<string,array<string,bool>> request-scoped cache keyed by "userId:folderId" */
    private array $cache = [];

    public function __construct(
        MSTV_Repository_Folders $folderRepo,
        MSTV_Repository_Groups $groupsRepo,
        MSTV_Repository_Permissions $permissionsRepo,
        MSTV_Settings $settings
    ) {
        $this->folderRepo = $folderRepo;
        $this->groupsRepo = $groupsRepo;
        $this->permissionsRepo = $permissionsRepo;
        $this->settings = $settings;
    }

    public function user_can(int $userId, ?int $folderId, string $action): bool
    {
        $actions = $this->effective_actions($userId, $folderId);

        return !empty($actions[$action]);
    }

    public function current_user_can(?int $folderId, string $action): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return $this->user_can(get_current_user_id(), $folderId, $action);
    }

    /**
     * @return array<string,bool> map action => granted
     */
    public function effective_actions(int $userId, ?int $folderId): array
    {
        $cacheKey = $userId . ':' . ($folderId === null ? 0 : $folderId);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = $this->resolve($userId, $folderId);
        $this->cache[$cacheKey] = $result;

        return $result;
    }

    private function resolve(int $userId, ?int $folderId): array
    {
        if ($userId <= 0) {
            return $this->none();
        }

        $user = get_user_by('id', $userId);

        if (!$user) {
            return $this->none();
        }

        // Free access floor: must hold the manage capability and pass the optional whitelist.
        if (!$user->has_cap(MSTV_Capabilities::CAP_MANAGE)) {
            return $this->none();
        }

        if ($this->settings->use_user_whitelist() && !$this->settings->is_user_allowed($userId)) {
            return $this->none();
        }

        // Administrators always retain emergency access.
        if ($user->has_cap('manage_options')) {
            return $this->all();
        }

        $rules = $this->nearest_rules($folderId);

        // No explicit rule anywhere in the ancestry: fall back to free behavior (full access).
        if ($rules === null) {
            return $this->all();
        }

        $groupIds = $this->groupsRepo->find_groups_for_user($userId);
        $granted = $this->none();

        foreach ($rules as $rule) {
            $action = (string) $rule->action;

            if (!isset($granted[$action])) {
                continue;
            }

            if ($rule->principal_type === 'user' && (int) $rule->principal_id === $userId) {
                $granted[$action] = true;
                continue;
            }

            if ($rule->principal_type === 'group' && in_array((int) $rule->principal_id, $groupIds, true)) {
                $granted[$action] = true;
            }
        }

        return $granted;
    }

    /**
     * Walk from the folder up to the virtual root (0) and return the rules of the
     * nearest ancestor (inclusive) that has any explicit rule, or null if none does.
     *
     * @return object[]|null
     */
    private function nearest_rules(?int $folderId): ?array
    {
        foreach ($this->ancestry_chain($folderId) as $fid) {
            $rules = $this->permissionsRepo->find_rules_for_folder($fid);

            if (!empty($rules)) {
                return $rules;
            }
        }

        return null;
    }

    /**
     * @return int[] folder ids from the target up to and including the virtual root (0)
     */
    private function ancestry_chain(?int $folderId): array
    {
        $chain = [];
        $current = ($folderId !== null && $folderId > 0) ? $folderId : null;
        $guard = 0;

        while ($current !== null && $current > 0 && $guard++ < 1000) {
            $chain[] = $current;
            $folder = $this->folderRepo->find($current);
            $current = ($folder && $folder->parent_id !== null) ? (int) $folder->parent_id : null;
        }

        $chain[] = 0; // virtual root: rules placed on folder 0 apply to root-level items.

        return $chain;
    }

    private function none(): array
    {
        return array_fill_keys(self::ACTIONS, false);
    }

    private function all(): array
    {
        return array_fill_keys(self::ACTIONS, true);
    }
}
