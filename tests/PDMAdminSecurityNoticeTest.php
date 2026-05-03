<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAdminSecurityNoticeTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_is_user_logged_in'] = true;
        $GLOBALS['pdm_test_current_user_caps'] = [
            'manage_options' => true,
        ];
        $GLOBALS['pdm_test_options'] = [
            'mstv_storage_path' => sys_get_temp_dir() . '/private-documents',
        ];
    }

    protected function tearDown(): void
    {
        $GLOBALS['pdm_test_is_user_logged_in'] = false;
        $GLOBALS['pdm_test_current_user_caps'] = [];
        $GLOBALS['pdm_test_options'] = [];
    }

    public function test_storage_security_notice_is_rendered_for_uploads_storage_path(): void
    {
        $admin = new MSTV_Admin(new MSTV_Settings());

        ob_start();
        $admin->render_storage_security_notice();
        $notice = (string) ob_get_clean();

        self::assertStringContainsString('notice-warning', $notice);
        self::assertStringContainsString('private files under WordPress uploads', $notice);
        self::assertStringContainsString('Nginx', $notice);
    }
}
