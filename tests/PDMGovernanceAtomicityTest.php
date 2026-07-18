<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMGovernanceAtomicityTest extends TestCase
{
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
}
