<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMStorageTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/mstv-storage-test-' . bin2hex(random_bytes(6));
        mkdir($this->storagePath, 0777, true);

        file_put_contents($this->storagePath . '/contract.pdf', str_repeat('a', 12));
        mkdir($this->storagePath . '/nested', 0777, true);
        file_put_contents($this->storagePath . '/nested/brief.txt', str_repeat('b', 5));

        file_put_contents($this->storagePath . '/.htaccess', str_repeat('x', 50));
        file_put_contents($this->storagePath . '/web.config', str_repeat('y', 50));
        file_put_contents($this->storagePath . '/index.php', str_repeat('z', 50));
        file_put_contents($this->storagePath . '/.mstv-storage', str_repeat('m', 50));

        $GLOBALS['pdm_test_options'] = [
            'mstv_storage_path' => $this->storagePath,
        ];
        $GLOBALS['pdm_test_transients'] = [];
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->storagePath);
        $GLOBALS['pdm_test_options'] = [];
        $GLOBALS['pdm_test_transients'] = [];
    }

    public function test_get_storage_stats_uses_real_teamvault_storage_usage(): void
    {
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('get_total_size')->willReturn(999);

        $storage = new MSTV_Storage(new MSTV_Settings());
        $stats = $storage->get_storage_stats($filesRepo);

        self::assertSame(17, $stats['plugin_used_bytes']);
        self::assertSame(MSTV_Helpers::format_filesize(17), $stats['plugin_used_formatted']);
        self::assertArrayHasKey('other_used_bytes', $stats);
        self::assertSame(
            max(0, $stats['disk']['used_bytes'] - 17),
            $stats['other_used_bytes']
        );
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
