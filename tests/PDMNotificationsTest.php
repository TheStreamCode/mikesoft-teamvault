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

final class PDMNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_notify_enabled' => true,
            'mstv_notify_events' => 'upload,download',
            'mstv_notify_recipients' => ['admins' => false, 'users' => [9], 'groups' => []],
        ];
        $GLOBALS['pdm_test_users'] = [
            9 => (object) ['ID' => 9, 'user_email' => 'ops@example.test'],
        ];
        $GLOBALS['pdm_test_mails'] = [];
    }

    private function notifications(array $members = []): MSTV_Notifications
    {
        return new MSTV_Notifications(new FakeNotifGroupsRepo($members));
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
        $GLOBALS['pdm_test_users'][11] = (object) ['ID' => 11, 'user_email' => 'team@example.test'];

        $n = $this->notifications([3 => [11]]);
        $n->on_file_uploaded(1, ['display_name' => 'A.pdf']);
        $n->flush();

        self::assertCount(1, $GLOBALS['pdm_test_mails']);
        self::assertSame(['team@example.test'], $GLOBALS['pdm_test_mails'][0]['to']);
    }
}
