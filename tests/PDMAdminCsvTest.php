<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAdminCsvTest extends TestCase
{
    public function test_audit_csv_neutralizes_spreadsheet_formula_cells(): void
    {
        $admin = new MSTV_Admin(new MSTV_Settings());
        $method = new ReflectionMethod(MSTV_Admin::class, 'build_audit_csv_row');
        $method->setAccessible(true);
        $log = (object) [
            'created_at' => '2026-07-18 12:00:00',
            'user_login' => 'editor',
            'user_id' => 7,
            'action' => 'upload',
            'target_type' => 'file',
            'target_id' => 9,
            'context' => json_encode(['filename' => '=external-reference']),
            'ip_address' => '127.0.0.1',
        ];

        $row = $method->invoke($admin, $log);

        self::assertSame("'=external-reference", $row[6]);
        self::assertSame('editor', $row[1]);
    }

    public function test_audit_csv_handler_reads_the_action_filter_under_log_action(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../includes/class-mstv-admin.php');

        self::assertStringContainsString(
            "['date_from', 'date_to', 'log_action', 'file_type']",
            $source,
            'The audit CSV handler must read the action filter under log_action so it cannot collide with the admin-post routing parameter.'
        );
        self::assertStringNotContainsString(
            "foreach (['date_from', 'date_to', 'action', 'file_type'] as \$key)",
            $source
        );
    }
}
