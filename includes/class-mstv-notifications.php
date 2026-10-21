<?php

defined('ABSPATH') || exit;

/**
 * Email notifications for document events (upload, download, delete, access-denied).
 *
 * Events are collected during the request and sent on `shutdown`, so document
 * operations are never slowed on the request path and bursts within one request
 * are summarized instead of sent one-per-file.
 *
 * Recipients are resolved to administrators, selected users, and/or TeamVault
 * groups. When folder-aware delivery is enabled, non-administrator recipients
 * must also be able to view the folder that produced the event; otherwise a
 * document name could be disclosed to a group that has no access to it.
 */
class MSTV_Notifications
{
    private MSTV_Repository_Groups $groupsRepo;
    private ?MSTV_Permissions $permissions;

    /** @var array<int,array{event:string,line:string,folder_id:?int}> */
    private array $queue = [];
    private bool $shutdownHooked = false;

    public function __construct(MSTV_Repository_Groups $groupsRepo, ?MSTV_Permissions $permissions = null)
    {
        $this->groupsRepo = $groupsRepo;
        $this->permissions = $permissions;
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
        ), $this->folder_id_from_data($data));
    }

    public function on_file_downloaded($fileId, $data): void
    {
        $name = is_array($data) ? ($data['display_name'] ?? ('#' . (int) $fileId)) : ('#' . (int) $fileId);
        $this->enqueue('download', sprintf(
            /* translators: %s: file name. */
            __('Downloaded: %s', 'mikesoft-teamvault'),
            $name
        ), $this->folder_id_from_data($data));
    }

    public function on_file_deleted($fileId, $data): void
    {
        $name = is_array($data) ? ($data['display_name'] ?? ('#' . (int) $fileId)) : ('#' . (int) $fileId);
        $this->enqueue('delete', sprintf(
            /* translators: %s: file name. */
            __('Deleted: %s', 'mikesoft-teamvault'),
            $name
        ), $this->folder_id_from_data($data));
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
        ), $folderId !== null ? (int) $folderId : null);
    }

    private function enqueue(string $event, string $line, ?int $folderId): void
    {
        if (!$this->is_enabled() || !in_array($event, $this->enabled_events(), true)) {
            return;
        }

        $this->queue[] = [
            'event' => $event,
            'line' => $line,
            'folder_id' => $folderId,
        ];

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

        // Group by event and recipient set: a single email must never mix files
        // from folders with different access rules.
        $batches = [];
        foreach ($this->queue as $item) {
            $recipients = $this->recipient_emails($item['event'], $item['folder_id']);
            if (empty($recipients)) {
                continue;
            }

            $key = $item['event'] . ':' . implode('|', $recipients);
            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'event' => $item['event'],
                    'recipients' => $recipients,
                    'lines' => [],
                ];
            }

            $batches[$key]['lines'][] = $item['line'];
        }

        $this->queue = [];

        if (empty($batches)) {
            return;
        }

        $siteName = (string) get_bloginfo('name');

        foreach ($batches as $batch) {
            $subject = sprintf(
                /* translators: 1: site name, 2: number of events, 3: event label. */
                __('[%1$s] TeamVault: %2$d %3$s event(s)', 'mikesoft-teamvault'),
                $siteName,
                count($batch['lines']),
                $this->event_label($batch['event'])
            );

            // wp_mail failures are non-fatal here; notifications must never break a request.
            wp_mail($batch['recipients'], $subject, implode("\n", $batch['lines']));
        }
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
     * Resolve recipient emails for one event.
     *
     * Administrators are always kept when the relevant option is enabled. Other
     * recipients are filtered against the folder that produced the event when
     * folder-aware delivery is on, so the notification cannot reveal a document
     * name to someone who cannot view that folder.
     *
     * @return string[] unique recipient email addresses
     */
    private function recipient_emails(string $event, ?int $folderId): array
    {
        $config = get_option('mstv_notify_recipients', ['admins' => true, 'users' => [], 'groups' => []]);
        $config = is_array($config) ? $config : [];

        $userIds = [];
        $adminIds = [];

        if (!empty($config['admins'])) {
            foreach (get_users(['role' => 'administrator', 'fields' => 'ID']) as $admin) {
                $adminId = is_object($admin) ? (int) ($admin->ID ?? 0) : (int) $admin;
                if ($adminId > 0) {
                    $userIds[] = $adminId;
                    $adminIds[] = $adminId;
                }
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

        $enforceFolderAccess = $this->is_folder_aware() && $this->permissions !== null;
        $emails = [];

        foreach (array_unique(array_filter($userIds)) as $uid) {
            $user = get_userdata($uid);
            if (!$user || !is_email($user->user_email)) {
                continue;
            }

            if ($enforceFolderAccess
                && !in_array($uid, $adminIds, true)
                && !$this->permissions->user_can($uid, $folderId, MSTV_Permissions::ACTION_VIEW)) {
                continue;
            }

            $emails[] = $user->user_email;
        }

        $emails = array_values(array_unique($emails));

        if (class_exists('MSTV_Hooks')) {
            $filtered = MSTV_Hooks::filter_notification_recipients($emails, $event, $folderId);
            if (is_array($filtered)) {
                $emails = $filtered;
            }
        }

        $emails = array_values(array_unique(array_filter(
            array_map('strval', $emails),
            static fn($email): bool => is_string($email) && is_email($email) !== false
        )));

        return $emails;
    }

    private function is_folder_aware(): bool
    {
        return (bool) get_option('mstv_notify_folder_aware', true);
    }

    private function folder_id_from_data(mixed $data): ?int
    {
        if (!is_array($data) || empty($data['folder_id'])) {
            return null;
        }

        return (int) $data['folder_id'];
    }
}
