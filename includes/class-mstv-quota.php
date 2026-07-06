<?php

defined('ABSPATH') || exit;

/**
 * Per-user and per-group upload/storage quotas on the customer-owned local storage.
 *
 * Limits are stored in the `mstv_quotas` option as a map of principal => max bytes,
 * e.g. ['user:5' => 104857600, 'group:3' => 524288000]. A value of 0 (or a missing
 * entry) means unlimited. The most restrictive applicable limit governs an upload.
 * Administrators are never blocked (governance, not a hard cap). Existing files stay
 * accessible even when a quota is exceeded — only new uploads are blocked.
 */
class MSTV_Quota
{
    private MSTV_Settings $settings;
    private MSTV_Repository_Files $filesRepo;
    private MSTV_Repository_Groups $groupsRepo;

    public function __construct(
        MSTV_Settings $settings,
        MSTV_Repository_Files $filesRepo,
        MSTV_Repository_Groups $groupsRepo
    ) {
        $this->settings = $settings;
        $this->filesRepo = $filesRepo;
        $this->groupsRepo = $groupsRepo;
    }

    public function is_enabled(): bool
    {
        return (bool) get_option('mstv_quotas_enabled', false);
    }

    /**
     * @return array<string,int> principal key => max bytes
     */
    public function get_quotas(): array
    {
        $quotas = get_option('mstv_quotas', []);

        return is_array($quotas) ? $quotas : [];
    }

    public function set_quotas(array $quotas): void
    {
        $clean = [];

        foreach ($quotas as $key => $bytes) {
            if (!preg_match('/^(user|group):[0-9]+$/', (string) $key)) {
                continue;
            }

            $bytes = (int) $bytes;
            if ($bytes > 0) {
                $clean[$key] = $bytes;
            }
        }

        update_option('mstv_quotas', $clean);
    }

    /**
     * Validate an upload against the applicable quotas before any disk write or
     * metadata insert. Returns a WP_Error (HTTP 413) when blocked, or null to allow.
     */
    public function check_upload(int $userId, int $incomingBytes): ?\WP_Error
    {
        if (!$this->is_enabled() || $userId <= 0) {
            return null;
        }

        $user = get_user_by('id', $userId);
        if ($user && $user->has_cap('manage_options')) {
            return null;
        }

        $quotas = $this->get_quotas();

        // User quota: usage = bytes uploaded by this user.
        $userLimit = (int) ($quotas['user:' . $userId] ?? 0);
        if ($userLimit > 0) {
            $usage = $this->filesRepo->get_total_size_by_user($userId);
            if ($usage + $incomingBytes > $userLimit) {
                return $this->error($userLimit, $usage, $incomingBytes);
            }
        }

        // Group quotas: usage = bytes uploaded by all members of the group (shared pool).
        foreach ($this->groupsRepo->find_groups_for_user($userId) as $groupId) {
            $groupLimit = (int) ($quotas['group:' . $groupId] ?? 0);
            if ($groupLimit <= 0) {
                continue;
            }

            $members = $this->groupsRepo->find_members($groupId);
            $usage = $this->filesRepo->get_total_size_by_users($members);
            if ($usage + $incomingBytes > $groupLimit) {
                return $this->error($groupLimit, $usage, $incomingBytes);
            }
        }

        return null;
    }

    /**
     * Serialize the quota check + metadata insert against concurrent uploads.
     *
     * check_upload() reads committed usage (SUM of file_size) and the caller inserts
     * the new row afterwards. Without a lock, two simultaneous uploads can both pass
     * the check before either row is committed and jointly exceed the quota (TOCTOU).
     * A named advisory lock held across the whole check→insert window closes that gap.
     *
     * Best-effort: waits up to 10s, then proceeds regardless, so a contended or stuck
     * lock degrades to the prior behavior instead of failing the upload outright.
     * No-op when quotas are disabled, to avoid lock overhead on the common path.
     */
    public function acquire_upload_lock(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory MySQL lock; a lock acquisition is inherently uncacheable.
        $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $this->lock_name(), 10));
    }

    public function release_upload_lock(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory MySQL lock; a lock release is inherently uncacheable.
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $this->lock_name()));
    }

    private function lock_name(): string
    {
        global $wpdb;

        // Namespace by table prefix: MySQL advisory locks are server-global, so sites
        // sharing a database server must not contend on the same lock name.
        return substr((string) $wpdb->prefix, 0, 40) . 'mstv_quota';
    }

    public function user_usage(int $userId): int
    {
        return $this->filesRepo->get_total_size_by_user($userId);
    }

    public function group_usage(int $groupId): int
    {
        return $this->filesRepo->get_total_size_by_users($this->groupsRepo->find_members($groupId));
    }

    private function error(int $limit, int $usage, int $incoming): \WP_Error
    {
        return new \WP_Error(
            'quota_exceeded',
            sprintf(
                /* translators: 1: storage limit, 2: bytes already used, 3: size of the file being uploaded. */
                __('Upload blocked: this would exceed the storage quota (%1$s). Already used: %2$s; this file: %3$s.', 'mikesoft-teamvault'),
                MSTV_Helpers::format_filesize($limit),
                MSTV_Helpers::format_filesize($usage),
                MSTV_Helpers::format_filesize($incoming)
            ),
            ['status' => 413]
        );
    }
}
