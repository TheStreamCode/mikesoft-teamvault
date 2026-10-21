<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAdminSecurityNoticeTest extends TestCase
{
    private string $uploadBase;
    private string $insideUploadsPath;
    private string $outsideUploadsPath;

    protected function setUp(): void
    {
        $this->uploadBase = sys_get_temp_dir() . '/mstv-test-uploads';
        $this->insideUploadsPath = $this->uploadBase . '/private-documents';
        $this->outsideUploadsPath = sys_get_temp_dir() . '/mstv-notice-test-' . bin2hex(random_bytes(6));

        $GLOBALS['pdm_test_is_user_logged_in'] = true;
        $GLOBALS['pdm_test_current_user_caps'] = ['manage_options' => true];
        $GLOBALS['pdm_test_options'] = [];
        $GLOBALS['pdm_test_user_meta'] = [];
        $_GET['page'] = 'mikesoft-teamvault-settings';
        unset($_SERVER['SERVER_SOFTWARE']);

        $GLOBALS['wp_filesystem'] = new class {
            public function is_writable($path): bool
            {
                return is_writable($path);
            }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_filesystem'], $_GET['page'], $_SERVER['SERVER_SOFTWARE']);
        $GLOBALS['pdm_test_is_user_logged_in'] = false;
        $GLOBALS['pdm_test_current_user_caps'] = [];
        $GLOBALS['pdm_test_options'] = [];
        $GLOBALS['pdm_test_user_meta'] = [];

        $this->deleteDirectory($this->insideUploadsPath);
        $this->deleteDirectory($this->outsideUploadsPath);
    }

    public function test_blocking_notice_is_rendered_when_storage_is_unprotected(): void
    {
        // No storage path configured: the default uploads directory does not exist,
        // so the protection files cannot be created.
        $notice = $this->renderNotice();

        self::assertStringContainsString('notice-error', $notice);
        self::assertStringContainsString('mstv-storage-security-notice', $notice);
        self::assertStringNotContainsString('is-dismissible', $notice);
        self::assertStringContainsString('Uploads are refused', $notice);
    }

    public function test_notice_is_not_dismissible_and_survives_a_previous_dismissal(): void
    {
        $GLOBALS['pdm_test_current_user_id'] = 1;
        $GLOBALS['pdm_test_user_meta'] = [
            1 => ['mstv_notice_storage_dismissed' => '1'],
        ];

        $notice = $this->renderNotice();

        self::assertStringContainsString('mstv-storage-security-notice', $notice);
        self::assertStringNotContainsString('is-dismissible', $notice);
    }

    public function test_nginx_warning_is_rendered_for_storage_inside_uploads(): void
    {
        $this->prepareProtectedStorageInsideUploads();
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.24.0';

        $notice = $this->renderNotice();

        self::assertStringContainsString('notice-warning', $notice);
        self::assertStringContainsString('Nginx', $notice);
        self::assertStringContainsString('outside the public webroot', $notice);
        self::assertStringNotContainsString('is-dismissible', $notice);
    }

    public function test_notice_is_suppressed_for_a_protected_out_of_webroot_storage(): void
    {
        $this->prepareProtectedStorage($this->outsideUploadsPath);
        $GLOBALS['pdm_test_options']['mstv_storage_path'] = $this->outsideUploadsPath;

        self::assertSame('', $this->renderNotice());
    }

    public function test_notice_is_rendered_on_the_file_manager_page(): void
    {
        $_GET['page'] = 'mikesoft-teamvault';

        self::assertStringContainsString('mstv-storage-security-notice', $this->renderNotice());
    }

    public function test_notice_is_suppressed_outside_teamvault_pages(): void
    {
        $_GET['page'] = 'edit.php';

        self::assertSame('', $this->renderNotice());
    }

    private function renderNotice(): string
    {
        $admin = new MSTV_Admin(new MSTV_Settings());

        ob_start();
        $admin->render_storage_security_notice();

        return (string) ob_get_clean();
    }

    private function prepareProtectedStorageInsideUploads(): void
    {
        $this->prepareProtectedStorage($this->insideUploadsPath);
        $GLOBALS['pdm_test_options']['mstv_storage_path'] = $this->insideUploadsPath;
    }

    private function prepareProtectedStorage(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($path . '/.htaccess', str_repeat('x', 10));
        file_put_contents($path . '/web.config', str_repeat('y', 10));
        file_put_contents($path . '/index.php', '<?php // Silence is golden');
        file_put_contents($path . '/.mstv-storage', "marker\n");
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
