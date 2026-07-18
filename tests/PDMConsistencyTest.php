<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMConsistencyTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/mstv-consistency-' . bin2hex(random_bytes(6));
        mkdir($this->storagePath, 0777, true);
        file_put_contents($this->storagePath . '/.mstv-storage', '1');
        $GLOBALS['pdm_test_options'] = ['mstv_storage_path' => $this->storagePath];
        $GLOBALS['pdm_test_transients'] = [];
        $GLOBALS['wp_filesystem'] = new class {
            public function mkdir($path, $mode = false): bool
            {
                return is_dir($path) || mkdir($path, 0777, true);
            }

            public function is_writable($path): bool
            {
                return is_writable($path);
            }

            public function move($from, $to, $overwrite = false): bool
            {
                return false;
            }

            public function rmdir($path, $recursive = false): bool
            {
                return is_dir($path) && rmdir($path);
            }
        };
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->storagePath);
        unset($GLOBALS['wp_filesystem']);
        $GLOBALS['pdm_test_options'] = [];
        $GLOBALS['pdm_test_transients'] = [];
    }

    public function test_create_folder_removes_directory_when_metadata_insert_fails(): void
    {
        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('exists_by_name_and_parent')->willReturn(false);
        $folderRepo->method('create')->willReturn(0);
        $controller = $this->controller(new MSTV_Storage(new MSTV_Settings()), $folderRepo);

        $result = $controller->create_folder(new WP_REST_Request(['name' => 'Reports']));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('database_error', $result->get_error_code());
        self::assertDirectoryDoesNotExist($this->storagePath . '/reports');
    }

    public function test_move_file_restores_original_path_when_metadata_update_fails(): void
    {
        mkdir($this->storagePath . '/source');
        mkdir($this->storagePath . '/target');
        file_put_contents($this->storagePath . '/source/file.pdf', 'content');
        $file = $this->fileRecord();

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find')->willReturn((object) ['id' => 9, 'relative_path' => 'target']);
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find')->willReturn($file);
        $filesRepo->method('move_to_folder')->willReturn(false);
        $controller = $this->controller(new MSTV_Storage(new MSTV_Settings()), $folderRepo, $filesRepo);

        $result = $controller->move_file(new WP_REST_Request(['id' => 5, 'folder_id' => 9]));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertFileExists($this->storagePath . '/source/file.pdf');
        self::assertFileDoesNotExist($this->storagePath . '/target/file.pdf');
    }

    public function test_delete_file_restores_staged_binary_when_metadata_delete_fails(): void
    {
        mkdir($this->storagePath . '/source');
        file_put_contents($this->storagePath . '/source/file.pdf', 'content');
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find')->willReturn($this->fileRecord());
        $filesRepo->method('delete')->willReturn(false);
        $controller = $this->controller(
            new MSTV_Storage(new MSTV_Settings()),
            $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock(),
            $filesRepo
        );

        $result = $controller->delete_file(new WP_REST_Request(['id' => 5]));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertFileExists($this->storagePath . '/source/file.pdf');
        self::assertSame('content', file_get_contents($this->storagePath . '/source/file.pdf'));
    }

    private function fileRecord(): object
    {
        return (object) [
            'id' => 5,
            'folder_id' => 1,
            'stored_name' => 'file.pdf',
            'relative_path' => 'source/file.pdf',
            'display_name' => 'File',
        ];
    }

    private function controller(
        MSTV_Storage $storage,
        MSTV_Repository_Folders $folderRepo,
        ?MSTV_Repository_Files $filesRepo = null
    ): MSTV_REST_Controller {
        return new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            new MSTV_Validator(new MSTV_Settings()),
            $folderRepo,
            $filesRepo ?: $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
