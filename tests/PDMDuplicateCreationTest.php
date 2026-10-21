<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMDuplicateCreationTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [];
        $GLOBALS['pdm_test_transients'] = [];
        $GLOBALS['wp_filesystem'] = new class {
            public function is_writable($path): bool
            {
                return is_writable($path);
            }
        };
        $_FILES = [];

        $this->storagePath = sys_get_temp_dir() . '/mstv-duplicate-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_filesystem'], $GLOBALS['wpdb']);
        $_FILES = [];
        $GLOBALS['pdm_test_options'] = [];

        $this->deleteDirectory($this->storagePath);
    }

    public function test_create_folder_reports_conflict_when_the_sibling_exists(): void
    {
        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('exists_by_name_and_parent')->willReturn(true);

        $controller = $this->buildController(
            $folderRepo,
            $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock()
        );

        $result = $controller->create_folder(new WP_REST_Request(['name' => 'Docs', 'parent_id' => null]));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('duplicate_error', $result->get_error_code());
        self::assertSame(409, $result->data['status']);
    }

    public function test_create_folder_race_maps_the_unique_violation_to_conflict(): void
    {
        $filesystem = $this->createMock(MSTV_Filesystem::class);
        $filesystem->expects(self::never())->method('delete_directory');

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('create_folder')->willReturn([
            'success' => true,
            'slug' => 'docs',
            'relative_path' => 'docs',
        ]);
        $storage->method('get_filesystem')->willReturn($filesystem);
        $storage->method('has_reindexable_content')->willReturn(false);

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('exists_by_name_and_parent')->willReturn(false);
        $folderRepo->method('create')->willReturn(0);
        $folderRepo->method('find_by_relative_path')->with('docs')->willReturn(
            (object) ['id' => 7, 'relative_path' => 'docs']
        );

        $controller = $this->buildController(
            $folderRepo,
            $storage,
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            true
        );

        $result = $controller->create_folder(new WP_REST_Request(['name' => 'Docs', 'parent_id' => null]));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('duplicate_error', $result->get_error_code());
        self::assertSame(409, $result->data['status']);
    }

    public function test_upload_maps_a_duplicate_storage_path_to_conflict(): void
    {
        $_FILES['file'] = [
            'name' => 'contract.pdf',
            'type' => 'application/pdf',
            'tmp_name' => '/tmp/contract.pdf',
            'error' => UPLOAD_ERR_OK,
            'size' => 123,
        ];

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('ensure_storage_directory')->willReturn(true);
        $storage->method('store_uploaded_file')->willReturn([
            'success' => true,
            'stored_name' => 'abc.pdf',
            'relative_path' => 'abc.pdf',
            'extension' => 'pdf',
            'file_size' => 123,
            'checksum' => 'checksum',
        ]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('create')->willReturn(0);
        $filesRepo->method('find_by_relative_path')->with('abc.pdf')->willReturn(
            (object) ['id' => 3, 'relative_path' => 'abc.pdf']
        );

        $controller = $this->buildController(
            $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock(),
            $storage,
            $filesRepo
        );

        $result = $controller->upload_file(new WP_REST_Request());

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('duplicate_file', $result->get_error_code());
        self::assertSame(409, $result->data['status']);
    }

    public function test_upload_reports_the_unprotected_storage_error_code(): void
    {
        $_FILES['file'] = [
            'name' => 'contract.pdf',
            'type' => 'application/pdf',
            'tmp_name' => '/tmp/contract.pdf',
            'error' => UPLOAD_ERR_OK,
            'size' => 123,
        ];

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('ensure_storage_directory')->willReturn(true);
        $storage->method('store_uploaded_file')->willReturn([
            'success' => false,
            'error_code' => 'storage_unprotected',
            'error' => 'Upload refused: TeamVault could not create the protection files (.htaccess) in the private storage directory.',
        ]);

        $controller = $this->buildController(
            $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock(),
            $storage,
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock()
        );

        $result = $controller->upload_file(new WP_REST_Request());

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('storage_unprotected', $result->get_error_code());
        self::assertSame(500, $result->data['status']);
    }

    public function test_reindex_reuses_a_folder_row_rejected_by_the_unique_key(): void
    {
        $this->prepareStorageDirectory(['docs' => ['brief.txt' => 'hello']]);

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_all')->willReturn([]);
        $folderRepo->method('create')->willReturn(0);
        $folderRepo->method('find_by_relative_path')->with('docs')->willReturn(
            (object) ['id' => 42, 'relative_path' => 'docs']
        );

        $createdFiles = [];
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find_all')->willReturn([]);
        $filesRepo->method('find_by_relative_path')->willReturn(null);
        $filesRepo->method('create')->willReturnCallback(static function (array $data) use (&$createdFiles): int {
            $createdFiles[] = $data;

            return count($createdFiles);
        });

        $result = (new MSTV_Storage(new MSTV_Settings()))->reindex_storage_records($folderRepo, $filesRepo, 7);

        self::assertTrue($result['success']);
        self::assertSame(0, $result['folders_created']);
        self::assertSame(1, $result['files_created']);
        self::assertSame(42, $createdFiles[0]['folder_id']);
    }

    public function test_reindex_skips_a_file_row_rejected_by_the_unique_key(): void
    {
        $this->prepareStorageDirectory(['brief.txt' => 'hello']);

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_all')->willReturn([]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find_all')->willReturn([]);
        $filesRepo->method('create')->willReturn(0);
        $filesRepo->method('find_by_relative_path')->with('brief.txt')->willReturn(
            (object) ['id' => 5, 'relative_path' => 'brief.txt']
        );

        $result = (new MSTV_Storage(new MSTV_Settings()))->reindex_storage_records($folderRepo, $filesRepo, 7);

        self::assertTrue($result['success']);
        self::assertSame(0, $result['files_created']);
        self::assertSame(1, $result['files_skipped']);
    }

    private function buildController(
        MSTV_Repository_Folders $folderRepo,
        MSTV_Storage $storage,
        MSTV_Repository_Files $filesRepo,
        bool $withFolderValidator = false
    ): MSTV_REST_Controller {
        $validator = $this->createMock(MSTV_Validator::class);

        if ($withFolderValidator) {
            $validator->method('validate_folder_name')->willReturn(['valid' => true, 'errors' => []]);
        }

        $validator->method('validate_upload_full')->willReturn([
            'valid' => true,
            'errors' => [],
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 123,
        ]);
        $validator->method('validate_file_name')->willReturn(['valid' => true, 'errors' => []]);

        $auth = $this->createMock(MSTV_Auth::class);
        $auth->method('get_current_user_id')->willReturn(5);

        return new MSTV_REST_Controller(
            new MSTV_Settings(),
            $auth,
            $storage,
            $validator,
            $folderRepo,
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );
    }

    /**
     * @param array<string,mixed> $structure Files and folders to create inside the storage root.
     */
    private function prepareStorageDirectory(array $structure): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }

        file_put_contents($this->storagePath . '/.mstv-storage', "marker\n");
        file_put_contents($this->storagePath . '/.htaccess', str_repeat('x', 10));
        file_put_contents($this->storagePath . '/web.config', str_repeat('y', 10));
        file_put_contents($this->storagePath . '/index.php', '<?php // Silence is golden');

        foreach ($structure as $name => $value) {
            if (is_array($value)) {
                mkdir($this->storagePath . '/' . $name, 0777, true);

                foreach ($value as $childName => $childValue) {
                    file_put_contents($this->storagePath . '/' . $name . '/' . $childName, (string) $childValue);
                }

                continue;
            }

            file_put_contents($this->storagePath . '/' . $name, (string) $value);
        }

        $GLOBALS['pdm_test_options'] = ['mstv_storage_path' => $this->storagePath];
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
