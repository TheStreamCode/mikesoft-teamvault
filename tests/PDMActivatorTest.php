<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMActivatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['pdm_test_roles'] = [];
    }

    public function test_register_capabilities_grants_document_access_only_to_administrators_by_default(): void
    {
        $GLOBALS['pdm_test_roles'] = [
            'administrator' => new FakePDMRole('Administrator'),
            'editor' => new FakePDMRole('Editor'),
        ];

        $method = new ReflectionMethod(MSTV_Activator::class, 'register_capabilities');
        $method->invoke(null);

        self::assertTrue($GLOBALS['pdm_test_roles']['administrator']->has_cap('manage_private_documents'));
        self::assertFalse($GLOBALS['pdm_test_roles']['editor']->has_cap('manage_private_documents'));
    }

    public function test_normalize_logs_table_name_accepts_expected_table_only(): void
    {
        self::assertSame(
            'wp_7_mstv_logs',
            MSTV_Activator::normalize_logs_table_name_for_upgrade('wp_7_mstv_logs', 'wp_7_')
        );
    }

    public function test_normalize_logs_table_name_rejects_unexpected_table_name(): void
    {
        self::assertSame(
            '',
            MSTV_Activator::normalize_logs_table_name_for_upgrade('wp_7_mstv_logs;DROP TABLE wp_users', 'wp_7_')
        );
    }

    public function test_migrate_legacy_log_target_types_uses_wpdb_update(): void
    {
        $wpdb = new class {
            public array $updates = [];

            public function update($table, $data, $where): int
            {
                $this->updates[] = [
                    'table' => $table,
                    'data' => $data,
                    'where' => $where,
                ];

                return 1;
            }
        };

        $result = MSTV_Activator::migrate_legacy_log_target_types($wpdb, 'wp_7_mstv_logs');

        self::assertTrue($result);
        self::assertSame([
            [
                'table' => 'wp_7_mstv_logs',
                'data' => ['target_type' => 'file'],
                'where' => ['target_type' => 'files'],
            ],
        ], $wpdb->updates);
    }
}
