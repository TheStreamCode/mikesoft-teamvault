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
}
