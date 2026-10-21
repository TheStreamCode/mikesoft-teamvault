<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FakeNotifGroupsRepo extends MSTV_Repository_Groups
{
    public array $membersByGroup = [];

    public function __construct(array $membersByGroup = [])
    {
        $this->membersByGroup = $membersByGroup;
    }

    public function find_members(int $groupId): array
    {
        return $this->membersByGroup[$groupId] ?? [];
    }
}

/**
 * Minimal permissions double: the notification code only needs user_can() for
 * the folder attached to each event. The real resolver is covered by
 * PDMPermissionsTest.
 */
final class FakeNotifPermissions extends MSTV_Permissions
{
    /** @var array<int,int[]> userId => folder IDs the user may view */
    public array $viewableFolders = [];

    public function __construct(array $viewableFolders = [])
    {
        $this->viewableFolders = $viewableFolders;
    }

    public function user_can(int $userId, ?int $folderId, string $action): bool
    {
        if ($action !== self::ACTION_VIEW) {
            return false;
        }

        return in_array($folderId, $this->viewableFolders[$userId] ?? [], true);
    }
}

final class PDMNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_notify_enabled' => true,
            'mstv_notify_events' => 'upload,download',
            'mstv_notify_recipients' => ['admins' => false, 'users' => [9], 'groups' => []],
            'mstv_notify_folder_aware' => true,
        ];
        $GLOBALS['pdm_test_users'] = [
            9 => (object) ['ID' => 9, 'user_email' => 'ops@example.test'],
        ];
        $GLOBALS['pdm_test_mails'] = [];
    }

    private function notifications(array $members = [], ?MSTV_Permissions $permissions = null): MSTV_Notifications
    {
        return new MSTV_Notifications(new FakeNotifGroupsRepo($members), $permissions);
    }

    private function addUser(int $id, string $email): void
    {
        $GLOBALS['pdm_test_users'][$id] = (object) ['ID' => $id, 'user_email' => $email];
    }

    public function test_enabled_event_sends_one_summary_email_per_event_type(): void
    {
        $n = $this->notifications();
        $n->on_file_uploaded(1, ['display_name' => 'A.pdf']);
        $n->on_file_uploaded(2, ['display_name' => 'B.pdf']);
        $n->on_file_downloaded(3, ['display_name' => 'C.pdf']);
        $n->flush();

        // One email for "upload" (2 items) + one for "download" (1 item).
        self::assertCount(2, $GLOBALS['pdm_test_mails']);

        $uploadMail = $GLOBALS['pdm_test_mails'][0];
        self::assertSame(['ops@example.test'], $uploadMail['to']);
        self::assertStringContainsString('A.pdf', $uploadMail['message']);
        self::assertStringContainsString('B.pdf', $uploadMail['message']);
    }

    public function test_disabled_notifications_send_nothing(): void
    {
        $GLOBALS['pdm_test_options']['mstv_notify_enabled'] = false;

        $n = $this->notifications();
        $n->on_file_uploaded(1, ['display_name' => 'A.pdf']);
        $n->flush();

        self::assertCount(0, $GLOBALS['pdm_test_mails']);
    }

    public function test_event_not_in_enabled_list_is_ignored(): void
    {
        // 'delete' is not in the enabled events list.
        $n = $this->notifications();
        $n->on_file_deleted(1, ['display_name' => 'A.pdf']);
        $n->flush();

        self::assertCount(0, $GLOBALS['pdm_test_mails']);
    }

    public function test_group_members_are_resolved_as_recipients(): void
    {
        $GLOBALS['pdm_test_options']['mstv_notify_recipients'] = ['admins' => false, 'users' => [], 'groups' => [3]];
        $this->addUser(11, 'team@example.test');

        $n = $this->notifications([3 => [11]]);
        $n->on_file_uploaded(1, ['display_name' => 'A.pdf']);
        $n->flush();

        self::assertCount(1, $GLOBALS['pdm_test_mails']);
        self::assertSame(['team@example.test'], $GLOBALS['pdm_test_mails'][0]['to']);
    }

    public function test_folder_aware_delivery_filters_group_members_by_folder_access(): void
    {
        $GLOBALS['pdm_test_options']['mstv_notify_recipients'] = [
            'admins' => false,
            'users' => [],
            'groups' => [3, 4],
        ];
        $this->addUser(11, 'consiglio@example.test');
        $this->addUser(12, 'notai@example.test');

        $permissions = new FakeNotifPermissions([
            11 => [10],
            12 => [20],
        ]);

        $n = $this->notifications([3 => [11], 4 => [12]], $permissions);
        $n->on_file_uploaded(1, ['display_name' => 'Verbale.pdf', 'folder_id' => 10]);
        $n->flush();

        self::assertCount(1, $GLOBALS['pdm_test_mails']);
        self::assertSame(['consiglio@example.test'], $GLOBALS['pdm_test_mails'][0]['to']);
    }

    public function test_folder_aware_delivery_keeps_administrators(): void
    {
        $GLOBALS['pdm_test_users'] = [
            1 => (object) ['ID' => 1, 'user_email' => 'admin@example.test'],
        ];
        $GLOBALS['pdm_test_options']['mstv_notify_recipients'] = [
            'admins' => true,
            'users' => [],
            'groups' => [],
        ];

        $permissions = new FakeNotifPermissions();

        $n = $this->notifications([], $permissions);
        $n->on_file_uploaded(1, ['display_name' => 'Verbale.pdf', 'folder_id' => 10]);
        $n->flush();

        self::assertCount(1, $GLOBALS['pdm_test_mails']);
        self::assertSame(['admin@example.test'], $GLOBALS['pdm_test_mails'][0]['to']);
    }

    public function test_folder_aware_delivery_can_be_disabled(): void
    {
        $GLOBALS['pdm_test_options']['mstv_notify_folder_aware'] = false;
        $GLOBALS['pdm_test_options']['mstv_notify_recipients'] = [
            'admins' => false,
            'users' => [],
            'groups' => [3, 4],
        ];
        $this->addUser(11, 'consiglio@example.test');
        $this->addUser(12, 'notai@example.test');

        $permissions = new FakeNotifPermissions([
            11 => [10],
        ]);

        $n = $this->notifications([3 => [11], 4 => [12]], $permissions);
        $n->on_file_uploaded(1, ['display_name' => 'Verbale.pdf', 'folder_id' => 10]);
        $n->flush();

        self::assertCount(1, $GLOBALS['pdm_test_mails']);
        self::assertSame(
            ['consiglio@example.test', 'notai@example.test'],
            $GLOBALS['pdm_test_mails'][0]['to']
        );
    }

    public function test_direct_recipients_are_filtered_by_folder_access(): void
    {
        $GLOBALS['pdm_test_options']['mstv_notify_recipients'] = [
            'admins' => false,
            'users' => [11, 12],
            'groups' => [],
        ];
        $this->addUser(11, 'consiglio@example.test');
        $this->addUser(12, 'notai@example.test');

        $permissions = new FakeNotifPermissions([
            11 => [10],
            12 => [20],
        ]);

        $n = $this->notifications([], $permissions);
        $n->on_file_uploaded(1, ['display_name' => 'Verbale.pdf', 'folder_id' => 20]);
        $n->flush();

        self::assertCount(1, $GLOBALS['pdm_test_mails']);
        self::assertSame(['notai@example.test'], $GLOBALS['pdm_test_mails'][0]['to']);
    }

    public function test_download_event_uses_its_folder_context(): void
    {
        $GLOBALS['pdm_test_options']['mstv_notify_recipients'] = [
            'admins' => false,
            'users' => [],
            'groups' => [3, 4],
        ];
        $this->addUser(11, 'consiglio@example.test');
        $this->addUser(12, 'notai@example.test');

        $permissions = new FakeNotifPermissions([
            11 => [10],
            12 => [20],
        ]);

        $n = $this->notifications([3 => [11], 4 => [12]], $permissions);
        $n->on_file_downloaded(1, ['display_name' => 'Verbale.pdf', 'folder_id' => 20]);
        $n->flush();

        self::assertCount(1, $GLOBALS['pdm_test_mails']);
        self::assertSame(['notai@example.test'], $GLOBALS['pdm_test_mails'][0]['to']);
    }

    public function test_different_folders_never_share_an_email(): void
    {
        $GLOBALS['pdm_test_options']['mstv_notify_recipients'] = [
            'admins' => false,
            'users' => [],
            'groups' => [3, 4],
        ];
        $this->addUser(11, 'consiglio@example.test');
        $this->addUser(12, 'notai@example.test');

        $permissions = new FakeNotifPermissions([
            11 => [10],
            12 => [20],
        ]);

        $n = $this->notifications([3 => [11], 4 => [12]], $permissions);
        $n->on_file_uploaded(1, ['display_name' => 'Verbale Consiglio.pdf', 'folder_id' => 10]);
        $n->on_file_uploaded(2, ['display_name' => 'Verbale Notai.pdf', 'folder_id' => 20]);
        $n->flush();

        self::assertCount(2, $GLOBALS['pdm_test_mails']);

        $byRecipient = [];
        foreach ($GLOBALS['pdm_test_mails'] as $mail) {
            $byRecipient[$mail['to'][0]] = $mail['message'];
        }

        self::assertStringContainsString('Verbale Consiglio.pdf', $byRecipient['consiglio@example.test']);
        self::assertStringNotContainsString('Verbale Notai.pdf', $byRecipient['consiglio@example.test']);
        self::assertStringContainsString('Verbale Notai.pdf', $byRecipient['notai@example.test']);
        self::assertStringNotContainsString('Verbale Consiglio.pdf', $byRecipient['notai@example.test']);
    }
}
