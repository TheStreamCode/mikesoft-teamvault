<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMGovernanceAtomicityTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [];
    }

    public function test_group_update_rolls_back_metadata_when_member_replacement_fails(): void
    {
        $wpdb = new class {
            public array $queries = [];

            public function query(string $query): int
            {
                $this->queries[] = $query;
                return 0;
            }
        };
        $GLOBALS['wpdb'] = $wpdb;

        $groups = $this->getMockBuilder(MSTV_Repository_Groups::class)
            ->disableOriginalConstructor()
            ->getMock();
        $groups->method('find')->willReturn((object) ['id' => 5]);
        $groups->method('exists_by_slug')->willReturn(false);
        $groups->expects(self::once())->method('update')->willReturn(true);
        $groups->expects(self::once())
            ->method('set_members')
            ->with(5, [7], false)
            ->willReturn(false);

        $controller = new MSTV_REST_Governance_Controller(
            $this->createMock(MSTV_Auth::class),
            $groups,
            $this->getMockBuilder(MSTV_Repository_Permissions::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Permissions::class)->disableOriginalConstructor()->getMock()
        );

        $result = $controller->update_group(new WP_REST_Request([
            'id' => 5,
            'name' => 'Editors',
            'members' => [7],
        ]));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('database_error', $result->get_error_code());
        self::assertContains('START TRANSACTION', $wpdb->queries);
        self::assertContains('ROLLBACK', $wpdb->queries);
        self::assertNotContains('COMMIT', $wpdb->queries);
    }

    public function test_group_update_rejects_a_name_that_is_empty_after_sanitization(): void
    {
        $groups = $this->getMockBuilder(MSTV_Repository_Groups::class)
            ->disableOriginalConstructor()
            ->getMock();
        $groups->method('find')->willReturn((object) ['id' => 5]);
        $groups->expects(self::never())->method('update');

        $result = $this->buildController($groups)->update_group(new WP_REST_Request([
            'id' => 5,
            'name' => '<script></script>',
        ]));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('validation_error', $result->get_error_code());
    }

    public function test_notification_boolean_strings_are_normalized_before_storage(): void
    {
        $controller = $this->buildController();

        $controller->update_notifications(new WP_REST_Request([
            'enabled' => 'false',
            'events' => [],
            'recipients' => ['admins' => 'false'],
        ]));

        self::assertFalse(get_option('mstv_notify_enabled'));
        self::assertFalse(get_option('mstv_notify_recipients')['admins']);
    }

    public function test_quota_boolean_strings_are_normalized_before_storage(): void
    {
        $groups = $this->getMockBuilder(MSTV_Repository_Groups::class)->disableOriginalConstructor()->getMock();
        $quota = new MSTV_Quota(
            new MSTV_Settings(),
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            $groups
        );
        $controller = $this->buildController($groups, $quota);

        $controller->update_quotas(new WP_REST_Request([
            'enabled' => 'false',
            'items' => [],
        ]));

        self::assertFalse(get_option('mstv_quotas_enabled'));
    }

    private function buildController(?MSTV_Repository_Groups $groups = null, ?MSTV_Quota $quota = null): MSTV_REST_Governance_Controller
    {
        $groups ??= $this->getMockBuilder(MSTV_Repository_Groups::class)->disableOriginalConstructor()->getMock();

        return new MSTV_REST_Governance_Controller(
            $this->createMock(MSTV_Auth::class),
            $groups,
            $this->getMockBuilder(MSTV_Repository_Permissions::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Permissions::class)->disableOriginalConstructor()->getMock(),
            $quota
        );
    }
}
