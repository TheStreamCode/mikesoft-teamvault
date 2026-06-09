<?php

defined('ABSPATH') || exit;

/**
 * Email notifications for document events (upload, download, delete, access-denied).
 *
 * Native and restrained: events are collected during the request and a single email
 * per event type is sent on `shutdown`, so uploads/downloads are never slowed on the
 * request path and bursts within one request are summarized instead of one-per-file.
 * Recipients are resolved to administrators, selected users, and/or TeamVault groups.
 */
class MSTV_Notifications
{
    private MSTV_Repository_Groups $groupsRepo;

    /** @var array<string,string[]> event => human lines */
    private array $queue = [];
    private bool $shutdownHooked = false;

    public function __construct(MSTV_Repository_Groups $groupsRepo)
    {
        $this->groupsRepo = $groupsRepo;
    }

    public function register(): void
    {
        add_action(MSTV_Hooks::FILE_UPLOADED, [$this, 'on_file_uploaded'], 10, 2);
        add_action(MSTV_Hooks::FILE_DOWNLOADED, [$this, 'on_file_downloaded'], 10, 2);
        add_action(MSTV_Hooks::FILE_DELETED, [$this, 'on_file_deleted'], 10, 2);
        add_action(MSTV_Hooks::ACCESS_DENIED, [$this, 'on_access_denied'], 10, 3);
    }

    public function is_enabled(): bool
    {
        return (bool) get_option('mstv_notify_enabled', false);
    }

    public function enabled_events(): array
    {
        $events = get_option('mstv_notify_events', '');
        $events = is_string($events) ? array_filter(array_map('trim', explode(',', $events))) : (array) $events;

        return array_values($events);
    }

    public function on_file_uploaded($fileId, $data): void
    {
        $name = is_array($data) ? ($data['display_name'] ?? ('#' . (int) $fileId)) : ('#' . (int) $fileId);
        $this->enqueue('upload', sprintf(
            /* translators: %s: file name. */
            __('Uploaded: %s', 'mikesoft-teamvault'),
            $name
        ));
    }

    public function on_file_downloaded($fileId, $data): void
    {
        $name = is_array($data) ? ($data['display_name'] ?? ('#' . (int) $fileId)) : ('#' . (int) $fileId);
        $this->enqueue('download', sprintf(
            /* translators: %s: file name. */
            __('Downloaded: %s', 'mikesoft-teamvault'),
            $name
        ));
    }

    public function on_file_deleted($fileId, $data): void
    {
        $name = is_array($data) ? ($data['display_name'] ?? ('#' . (int) $fileId)) : ('#' . (int) $fileId);
        $this->enqueue('delete', sprintf(
            /* translators: %s: file name. */
            __('Deleted: %s', 'mikesoft-teamvault'),
            $name
        ));
    }

    public function on_access_denied($userId, $folderId, $action): void
    {
        $user = get_user_by('id', (int) $userId);
        $who = $user ? $user->user_login : ('#' . (int) $userId);
        $this->enqueue('access_denied', sprintf(
            /* translators: 1: user login, 2: attempted action, 3: folder id. */
            __('Access denied for %1$s (action: %2$s, folder: %3$s)', 'mikesoft-teamvault'),
            $who,
            (string) $action,
            $folderId !== null ? (string) $folderId : 'root'
        ));
    }

    private function enqueue(string $event, string $line): void
    {
        if (!$this->is_enabled() || !in_array($event, $this->enabled_events(), true)) {
            return;
        }

        $this->queue[$event][] = $line;

        if (!$this->shutdownHooked) {
            add_action('shutdown', [$this, 'flush'], 100);
            $this->shutdownHooked = true;
        }
    }

    public function flush(): void
    {
        if (empty($this->queue)) {
            return;
        }

        $recipients = $this->recipient_emails();
        if (empty($recipients)) {
            $this->queue = [];
            return;
        }

        $siteName = (string) get_bloginfo('name');

        foreach ($this->queue as $event => $lines) {
            $subject = sprintf(
                /* translators: 1: site name, 2: number of events, 3: event label. */
                __('[%1$s] TeamVault: %2$d %3$s event(s)', 'mikesoft-teamvault'),
                $siteName,
                count($lines),
                $this->event_label($event)
            );

            $body = implode("\n", $lines);

            // wp_mail failures are non-fatal here; notifications must never break a request.
            wp_mail($recipients, $subject, $body);
        }

        $this->queue = [];
    }

    private function event_label(string $event): string
    {
        switch ($event) {
            case 'upload':
                return __('upload', 'mikesoft-teamvault');
            case 'download':
                return __('download', 'mikesoft-teamvault');
            case 'delete':
                return __('delete', 'mikesoft-teamvault');
            case 'access_denied':
                return __('access denied', 'mikesoft-teamvault');
            default:
                return $event;
        }
    }

    /**
     * @return string[] unique recipient email addresses
     */
    private function recipient_emails(): array
    {
        $config = get_option('mstv_notify_recipients', ['admins' => true, 'users' => [], 'groups' => []]);
        $config = is_array($config) ? $config : [];

        $userIds = [];

        if (!empty($config['admins'])) {
            foreach (get_users(['role' => 'administrator', 'fields' => 'ID']) as $adminId) {
                $userIds[] = (int) $adminId;
            }
        }

        foreach ((array) ($config['users'] ?? []) as $uid) {
            $userIds[] = (int) $uid;
        }

        foreach ((array) ($config['groups'] ?? []) as $gid) {
            foreach ($this->groupsRepo->find_members((int) $gid) as $memberId) {
                $userIds[] = (int) $memberId;
            }
        }

        $emails = [];
        foreach (array_unique(array_filter($userIds)) as $uid) {
            $user = get_userdata($uid);
            if ($user && is_email($user->user_email)) {
                $emails[] = $user->user_email;
            }
        }

        return array_values(array_unique($emails));
    }
}
