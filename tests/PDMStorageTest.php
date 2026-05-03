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
        $GLOBALS['pdm_test_transients'] = [];
    }

    public function test_get_storage_stats_uses_real_teamvault_storage_usage(): void
    {
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find_all')->willReturn([
            (object) ['relative_path' => 'contract.pdf', 'file_size' => 999],
            (object) ['relative_path' => 'nested/brief.txt', 'file_size' => 999],
        ]);

        $storage = new MSTV_Storage(new MSTV_Settings());
        $stats = $storage->get_storage_stats($filesRepo);

        self::assertSame(17, $stats['plugin_used_bytes']);
        self::assertSame(MSTV_Helpers::format_filesize(17), $stats['plugin_used_formatted']);
        self::assertArrayNotHasKey('disk', $stats);
        self::assertArrayNotHasKey('other_used_bytes', $stats);
    }

    public function test_get_storage_stats_uses_registered_files_when_directory_listing_is_unavailable(): void
    {
        $filesystem = $this->createMock(MSTV_Filesystem::class);
        $filesystem->method('get_base_path')->willReturn('/home/example/private-documents');
        $filesystem->method('get_disk_stats')->willReturn([
            'available' => false,
            'total_bytes' => 0,
            'free_bytes' => 0,
            'used_bytes' => 0,
            'free_percentage' => 0,
        ]);
        $filesystem->expects(self::never())->method('list_directory');
        $filesystem->method('is_file')->willReturnMap([
            ['contracts/quote.pdf', true],
            ['contracts/spec.txt', true],
            ['contracts/missing.pdf', false],
        ]);
        $filesystem->method('get_file_size')->willReturnMap([
            ['contracts/quote.pdf', 12],
            ['contracts/spec.txt', 5],
        ]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find_all')->willReturn([
            (object) ['relative_path' => 'contracts/quote.pdf', 'file_size' => 999],
            (object) ['relative_path' => 'contracts/spec.txt', 'file_size' => 999],
            (object) ['relative_path' => 'contracts/missing.pdf', 'file_size' => 999],
        ]);

        $storage = new MSTV_Storage(new MSTV_Settings());
        $filesystemProperty = new ReflectionProperty(MSTV_Storage::class, 'filesystem');
        $filesystemProperty->setAccessible(true);
        $filesystemProperty->setValue($storage, $filesystem);

        $stats = $storage->get_storage_stats($filesRepo);

        self::assertSame(17, $stats['plugin_used_bytes']);
        self::assertSame(MSTV_Helpers::format_filesize(17), $stats['plugin_used_formatted']);
    }

    public function test_get_storage_stats_deduplicates_same_relative_path(): void
    {
        $filesystem = $this->createMock(MSTV_Filesystem::class);
        $filesystem->method('get_base_path')->willReturn('/home/example/private-documents');
        $filesystem->method('is_file')->willReturnMap([
            ['contracts/quote.pdf', true],
        ]);
        $filesystem->expects(self::once())->method('get_file_size')->with('contracts/quote.pdf')->willReturn(12);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find_all')->willReturn([
            (object) ['relative_path' => 'contracts/quote.pdf'],
            (object) ['relative_path' => 'contracts/quote.pdf'],
        ]);

        $storage = new MSTV_Storage(new MSTV_Settings());
        $filesystemProperty = new ReflectionProperty(MSTV_Storage::class, 'filesystem');
        $filesystemProperty->setAccessible(true);
        $filesystemProperty->setValue($storage, $filesystem);

        $stats = $storage->get_storage_stats($filesRepo);

        self::assertSame(12, $stats['plugin_used_bytes']);
        self::assertSame(MSTV_Helpers::format_filesize(12), $stats['plugin_used_formatted']);
    }

    public function test_reindex_skips_files_that_violate_upload_policy(): void
    {
        file_put_contents($this->storagePath . '/shell.php', '<?php echo "bad";');

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_all')->willReturn([]);
        $folderRepo->method('create')->willReturn(1);

        $createdFiles = [];
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find_all')->willReturn([]);
        $filesRepo->method('create')->willReturnCallback(static function (array $data) use (&$createdFiles): int {
            $createdFiles[] = $data;

            return count($createdFiles);
        });

        $storage = new MSTV_Storage(new MSTV_Settings());
        $result = $storage->reindex_storage_records($folderRepo, $filesRepo, 7);

        self::assertTrue($result['success']);
        self::assertSame(2, $result['files_created']);
        self::assertSame(1, $result['files_skipped']);
        self::assertSame(['contract.pdf', 'nested/brief.txt'], array_column($createdFiles, 'relative_path'));
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
