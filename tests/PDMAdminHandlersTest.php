<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAdminHandlersTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_use_user_whitelist' => false,
            'mstv_allowed_users' => [],
        ];
        $GLOBALS['pdm_test_current_user_caps'] = [];
        $GLOBALS['pdm_test_current_user_can'] = false;
        $GLOBALS['pdm_test_is_user_logged_in'] = true;
        $GLOBALS['pdm_test_current_user_id'] = 5;
        $GLOBALS['pdm_test_transients'] = [];
        $GLOBALS['pdm_test_verified_nonce_actions'] = [];
        $GLOBALS['pdm_test_redirects'] = [];
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['pdm_test_current_user_caps'] = [];
        $GLOBALS['pdm_test_is_user_logged_in'] = false;
        $GLOBALS['pdm_test_current_user_id'] = 0;
        $GLOBALS['pdm_test_transients'] = [];
        $GLOBALS['pdm_test_verified_nonce_actions'] = [];
        $GLOBALS['pdm_test_redirects'] = [];
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
    }

    private function admin(): MSTV_Admin
    {
        return new MSTV_Admin(new MSTV_Settings());
    }

    public function test_render_callbacks_die_without_capability(): void
    {
        $admin = $this->admin();

        $callbacks = [
            [$admin, 'render_file_manager_page'],
            [$admin, 'render_settings_page'],
            [$admin, 'render_groups_page'],
            [$admin, 'render_quotas_page'],
            [$admin, 'render_notifications_page'],
            [$admin, 'render_reports_page'],
            [$admin, 'render_logs_page'],
        ];

        foreach ($callbacks as $callback) {
            try {
                $callback();
                self::fail('A render callback ran without the required capability.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'You do not have permission to access this page.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_file_manager_page_renders_for_authorized_users(): void
    {
        $GLOBALS['pdm_test_current_user_caps'] = [
            MSTV_Capabilities::CAP_MANAGE => true,
        ];

        ob_start();
        $this->admin()->render_file_manager_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('pdm-app', $html);
        self::assertStringContainsString('pdm-folder-tree', $html);
    }

    public function test_handle_save_settings_rejects_unsupported_interface_language(): void
    {
        $GLOBALS['pdm_test_current_user_caps'] = ['manage_options' => true];
        $_POST = [
            'mstv_settings_nonce' => 'valid-nonce',
            'mstv_interface_language' => 'xx',
        ];

        try {
            $this->admin()->handle_save_settings();
            self::fail('handle_save_settings did not redirect after saving.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('redirect:', $exception->getMessage());
        }

        self::assertSame('en', get_option('mstv_interface_language', 'auto'));
        self::assertContains('mstv_settings_nonce', $GLOBALS['pdm_test_verified_nonce_actions']);
    }

    public function test_handle_save_settings_stores_sanitized_settings(): void
    {
        $GLOBALS['pdm_test_current_user_caps'] = ['manage_options' => true];
        $_POST = [
            'mstv_settings_nonce' => 'valid-nonce',
            'mstv_interface_language' => 'it',
            'mstv_allowed_extensions' => 'pdf, doc',
            'mstv_max_file_size' => '1024',
        ];

        try {
            $this->admin()->handle_save_settings();
            self::fail('handle_save_settings did not redirect after saving.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('redirect:', $exception->getMessage());
        }

        self::assertSame('it', get_option('mstv_interface_language', 'auto'));
        self::assertSame('pdf,doc', (string) get_option('mstv_allowed_extensions', ''));
        self::assertSame(1024, (int) get_option('mstv_max_file_size', 0));
        self::assertTrue((bool) get_transient('mstv_settings_saved_5'));
    }

    public function test_handle_save_settings_prevents_whitelist_lockout(): void
    {
        $GLOBALS['pdm_test_current_user_caps'] = ['manage_options' => true];
        $_POST = [
            'mstv_settings_nonce' => 'valid-nonce',
            'mstv_use_user_whitelist' => '1',
            'mstv_allowed_users' => ['99'],
        ];

        try {
            $this->admin()->handle_save_settings();
            self::fail('handle_save_settings allowed a whitelist that excludes the current user.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('redirect:', $exception->getMessage());
        }

        $error = get_transient('mstv_settings_error_5');

        self::assertIsString($error);
        self::assertStringContainsString('Add your current account to the whitelist', $error);
        self::assertFalse((bool) get_option('mstv_use_user_whitelist', false));
    }

    public function test_handle_export_audit_csv_dies_for_invalid_nonce(): void
    {
        $GLOBALS['pdm_test_current_user_caps'] = ['manage_options' => true];
        $_GET = ['mstv_audit_csv_nonce' => 'forged-nonce'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid security token.');

        $this->admin()->handle_export_audit_csv();
    }

    public function test_handle_download_file_dies_without_capability(): void
    {
        $_REQUEST = [
            'file_id' => '1',
            'mstv_stream_nonce' => 'valid-nonce',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You do not have permission to access this page.');

        $this->admin()->handle_download_file();
    }

    public function test_handle_download_file_dies_for_missing_nonce(): void
    {
        $GLOBALS['pdm_test_current_user_caps'] = [
            MSTV_Capabilities::CAP_MANAGE => true,
        ];
        $_REQUEST = ['file_id' => '1'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Are you sure you want to do this?');

        $this->admin()->handle_download_file();
    }
}
