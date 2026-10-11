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
        $GLOBALS['pdm_test_current_user_id'] = 0;
        $GLOBALS['pdm_test_user_meta'] = [];
        unset($_GET['page']);
    }

    public function test_storage_security_notice_is_rendered_for_uploads_storage_path_on_settings_page(): void
    {
        $_GET['page'] = 'mikesoft-teamvault-settings';
        $admin = new MSTV_Admin(new MSTV_Settings());

        ob_start();
        $admin->render_storage_security_notice();
        $notice = (string) ob_get_clean();

        self::assertStringContainsString('notice-warning', $notice);
        self::assertStringContainsString('is-dismissible', $notice);
        self::assertStringContainsString('mstv-storage-security-notice', $notice);
        self::assertStringContainsString('private files under WordPress uploads', $notice);
        self::assertStringContainsString('Nginx', $notice);
    }

    public function test_storage_security_notice_is_suppressed_outside_settings_page(): void
    {
        $_GET['page'] = 'mikesoft-teamvault';
        $admin = new MSTV_Admin(new MSTV_Settings());

        ob_start();
        $admin->render_storage_security_notice();
        $notice = (string) ob_get_clean();

        self::assertSame('', $notice);
    }

    public function test_storage_security_notice_is_suppressed_after_dismiss(): void
    {
        $_GET['page'] = 'mikesoft-teamvault-settings';
        $GLOBALS['pdm_test_current_user_id'] = 1;
        $GLOBALS['pdm_test_user_meta'] = [
            1 => ['mstv_notice_storage_dismissed' => '1'],
        ];

        $admin = new MSTV_Admin(new MSTV_Settings());

        ob_start();
        $admin->render_storage_security_notice();
        $notice = (string) ob_get_clean();

        self::assertSame('', $notice);
    }
}
