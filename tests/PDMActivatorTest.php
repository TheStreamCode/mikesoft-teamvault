<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMActivatorTest extends TestCase
{
    public function test_normalize_logs_table_name_accepts_expected_table_only(): void
    {
        self::assertSame(
            'wp_7_pdm_logs',
            PDM_Activator::normalize_logs_table_name_for_upgrade('wp_7_pdm_logs', 'wp_7_')
        );
    }

    public function test_normalize_logs_table_name_rejects_unexpected_table_name(): void
    {
        self::assertSame(
            '',
            PDM_Activator::normalize_logs_table_name_for_upgrade('wp_7_pdm_logs;DROP TABLE wp_users', 'wp_7_')
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

        $result = PDM_Activator::migrate_legacy_log_target_types($wpdb, 'wp_7_pdm_logs');

        self::assertTrue($result);
        self::assertSame([
            [
                'table' => 'wp_7_pdm_logs',
                'data' => ['target_type' => 'file'],
                'where' => ['target_type' => 'files'],
            ],
        ], $wpdb->updates);
    }
}
