<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMStorageProtectionTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/mstv-protection-test-' . bin2hex(random_bytes(6));
        mkdir($this->storagePath, 0777, true);
        file_put_contents($this->storagePath . '/.mstv-storage', "marker\n");

        $GLOBALS['pdm_test_options'] = ['mstv_storage_path' => $this->storagePath];
        $GLOBALS['wp_filesystem'] = new class {
            public function is_writable($path): bool
            {
                return is_writable($path);
            }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_filesystem']);
        $this->deleteDirectory($this->storagePath);
        $GLOBALS['pdm_test_options'] = [];
    }

    public function test_failing_writer_reports_every_required_protection_file(): void
    {
        $writer = static function (): bool {
            return false;
        };

        $result = MSTV_Helpers::ensure_protection_files($this->storagePath, $writer);

        self::assertFalse($result['ok']);
        self::assertSame(['.htaccess', 'web.config', 'index.php'], $result['failed']);
        self::assertFalse($result['files']['.htaccess']);
        self::assertFalse($result['files']['web.config']);
        self::assertFalse($result['files']['index.php']);
        self::assertFalse($result['marker']);
    }

    public function test_existing_protection_files_are_not_rewritten(): void
    {
        file_put_contents($this->storagePath . '/.htaccess', str_repeat('x', 10));
        file_put_contents($this->storagePath . '/web.config', str_repeat('y', 10));
        file_put_contents($this->storagePath . '/index.php', '<?php // Silence is golden');
        file_put_contents($this->storagePath . '/.mstv-storage', 'marker');

        $calls = 0;
        $writer = static function () use (&$calls): bool {
            $calls++;

            return false;
        };

        $result = MSTV_Helpers::ensure_protection_files($this->storagePath, $writer);

        self::assertTrue($result['ok']);
        self::assertSame(0, $calls);
    }

    public function test_empty_htaccess_is_rewritten_and_fails_closed(): void
    {
        file_put_contents($this->storagePath . '/.htaccess', '');

        $writer = static function (string $file): bool {
            return !str_ends_with($file, '.htaccess');
        };

        $result = MSTV_Helpers::ensure_protection_files($this->storagePath, $writer);

        self::assertFalse($result['ok']);
        self::assertSame(['.htaccess'], $result['failed']);
    }

    public function test_empty_storage_path_is_reported_as_unprotected(): void
    {
        $result = MSTV_Helpers::ensure_protection_files('', static fn(): bool => true);

        self::assertFalse($result['ok']);
        self::assertSame(['.htaccess', 'web.config', 'index.php'], $result['failed']);
        self::assertFalse($result['marker']);
    }

    public function test_storage_status_reports_an_unprotected_directory(): void
    {
        $storage = new MSTV_Storage(new MSTV_Settings(), static fn(): bool => false);
        $status = $storage->get_storage_protection_status();

        self::assertFalse($status['ok']);
        self::assertTrue($status['directory_writable']);
        self::assertSame(['.htaccess', 'web.config', 'index.php'], $status['failed']);
        self::assertFalse($status['inside_uploads']);
    }

    public function test_upload_is_refused_when_protection_files_cannot_be_written(): void
    {
        $source = $this->storagePath . '/source.pdf';
        file_put_contents($source, '%PDF-1.4');

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $storage = new MSTV_Storage(new MSTV_Settings(), static fn(): bool => false);

        $result = $storage->store_uploaded_file([
            'name' => 'contract.pdf',
            'tmp_name' => $source,
        ], null, $folderRepo);

        self::assertFalse($result['success']);
        self::assertSame('storage_unprotected', $result['error_code']);
        self::assertStringContainsString('Upload refused', $result['error']);
        self::assertStringContainsString('.htaccess', $result['error']);

        $stored = glob($this->storagePath . '/*.pdf') ?: [];
        self::assertSame([$source], $stored);
    }

    public function test_upload_succeeds_when_protection_files_can_be_written(): void
    {
        $source = $this->storagePath . '/source.pdf';
        file_put_contents($source, '%PDF-1.4');

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $storage = new MSTV_Storage(new MSTV_Settings());

        $result = $storage->store_uploaded_file([
            'name' => 'contract.pdf',
            'tmp_name' => $source,
        ], null, $folderRepo);

        self::assertTrue($result['success']);
        self::assertFileExists($this->storagePath . '/.htaccess');
        self::assertFileExists($this->storagePath . '/web.config');
        self::assertFileExists($this->storagePath . '/index.php');
        self::assertFileExists($this->storagePath . '/' . $result['stored_name']);
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
