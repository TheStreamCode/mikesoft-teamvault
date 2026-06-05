<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMFolderMoveTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/mstv-folder-move-' . bin2hex(random_bytes(6));
        mkdir($this->storagePath, 0777, true);
        // Marker that lets MSTV_Settings accept a custom storage path outside wp-uploads.
        file_put_contents($this->storagePath . '/.mstv-storage', '1');

        $GLOBALS['pdm_test_options'] = [
            'mstv_storage_path' => $this->storagePath,
        ];
        $GLOBALS['pdm_test_transients'] = [];
        $GLOBALS['wp_filesystem'] = new class {
            public function is_writable($path): bool
            {
                return is_writable($path);
            }

            // Force the filesystem layer onto its native rename() fallback so the
            // temp-directory move is exercised deterministically in tests.
            public function move($from, $to, $overwrite = false): bool
            {
                return false;
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

    public function test_move_folder_relocates_directory_into_destination(): void
    {
        mkdir($this->storagePath . '/docs', 0777, true);
        mkdir($this->storagePath . '/archive', 0777, true);

        $folderRepo = $this->folderRepoReturning([
            10 => (object) ['id' => 10, 'parent_id' => null, 'slug' => 'docs', 'relative_path' => 'docs'],
            20 => (object) ['id' => 20, 'parent_id' => null, 'slug' => 'archive', 'relative_path' => 'archive'],
        ]);

        $storage = new MSTV_Storage(new MSTV_Settings());
        $result = $storage->move_folder(10, 20, $folderRepo);

        self::assertTrue($result['success']);
        self::assertSame('archive/docs', $result['new_relative_path']);
        self::assertDirectoryExists($this->storagePath . '/archive/docs');
        self::assertDirectoryDoesNotExist($this->storagePath . '/docs');
    }

    public function test_move_folder_to_root(): void
    {
        mkdir($this->storagePath . '/box/item', 0777, true);

        $folderRepo = $this->folderRepoReturning([
            30 => (object) ['id' => 30, 'parent_id' => 1, 'slug' => 'item', 'relative_path' => 'box/item'],
        ]);

        $storage = new MSTV_Storage(new MSTV_Settings());
        $result = $storage->move_folder(30, null, $folderRepo);

        self::assertTrue($result['success']);
        self::assertSame('item', $result['new_relative_path']);
        self::assertDirectoryExists($this->storagePath . '/item');
        self::assertDirectoryDoesNotExist($this->storagePath . '/box/item');
    }

    public function test_move_folder_rejects_move_into_descendant(): void
    {
        mkdir($this->storagePath . '/parent/child', 0777, true);

        $folderRepo = $this->folderRepoReturning([
            40 => (object) ['id' => 40, 'parent_id' => null, 'slug' => 'parent', 'relative_path' => 'parent'],
            41 => (object) ['id' => 41, 'parent_id' => 40, 'slug' => 'child', 'relative_path' => 'parent/child'],
        ]);

        $storage = new MSTV_Storage(new MSTV_Settings());
        $result = $storage->move_folder(40, 41, $folderRepo);

        self::assertFalse($result['success']);
        self::assertStringContainsString('itself', $result['error']);
        self::assertDirectoryExists($this->storagePath . '/parent/child');
    }

    public function test_move_folder_rejects_same_parent(): void
    {
        mkdir($this->storagePath . '/here', 0777, true);

        $folderRepo = $this->folderRepoReturning([
            50 => (object) ['id' => 50, 'parent_id' => null, 'slug' => 'here', 'relative_path' => 'here'],
        ]);

        $storage = new MSTV_Storage(new MSTV_Settings());
        $result = $storage->move_folder(50, null, $folderRepo);

        self::assertFalse($result['success']);
        self::assertStringContainsString('already', $result['error']);
    }

    public function test_controller_move_folder_updates_records_and_logs(): void
    {
        $folder = (object) [
            'id' => 5,
            'parent_id' => null,
            'name' => 'Child',
            'slug' => 'child',
            'relative_path' => 'child',
            'created_at' => '2026-01-01 00:00:00',
        ];
        $target = (object) [
            'id' => 9,
            'parent_id' => null,
            'name' => 'Dest',
            'slug' => 'dest',
            'relative_path' => 'dest',
            'created_at' => '2026-01-01 00:00:00',
        ];

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find')->willReturnCallback(static function ($id) use ($folder, $target) {
            if ((int) $id === 5) {
                return $folder;
            }
            if ((int) $id === 9) {
                return $target;
            }
            return null;
        });
        $folderRepo->method('count_children')->willReturn(0);
        $folderRepo->expects(self::once())
            ->method('update')
            ->with(5, self::callback(static function (array $data): bool {
                return $data['parent_id'] === 9 && $data['relative_path'] === 'dest/child';
            }));
        $folderRepo->expects(self::once())->method('update_relative_paths')->with(5, 'dest/child');

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->expects(self::once())
            ->method('update_relative_paths_for_folder_rename')
            ->with('child', 'dest/child')
            ->willReturn(0);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('move_folder')->willReturn([
            'success' => true,
            'new_relative_path' => 'dest/child',
        ]);

        $logger = $this->createMock(MSTV_Logger::class);
        $logger->expects(self::once())->method('log_folder_move')->with(5, 'Child', null, 9);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $this->createMock(MSTV_Validator::class),
            $folderRepo,
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $logger
        );

        $response = $controller->move_folder(new WP_REST_Request([
            'id' => 5,
            'parent_id' => '9',
        ]));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertTrue($response->data['success']);
        self::assertSame(5, $response->data['data']['id']);
    }

    public function test_controller_move_folder_returns_error_for_missing_target(): void
    {
        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find')->willReturn(null);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->expects(self::never())->method('move_folder');

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $this->createMock(MSTV_Validator::class),
            $folderRepo,
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );

        $response = $controller->move_folder(new WP_REST_Request([
            'id' => 5,
            'parent_id' => '999',
        ]));

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('Folder not found.', $response->get_error_message());
    }

    private function folderRepoReturning(array $byId): MSTV_Repository_Folders
    {
        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find')->willReturnCallback(static function ($id) use ($byId) {
            return $byId[(int) $id] ?? null;
        });

        return $folderRepo;
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
