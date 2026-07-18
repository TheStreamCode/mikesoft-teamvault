<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMRestControllerTest extends TestCase
{
    public function test_resolve_folder_id_returns_error_for_missing_folder(): void
    {
        $folderRepo = $this->createMock(MSTV_Repository_Folders::class);
        $folderRepo->method('find')->with(99)->willReturn(null);

        $controller = $this->buildController($folderRepo);
        $method = new ReflectionMethod(MSTV_REST_Controller::class, 'resolve_folder_id');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '99');

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('Folder not found.', $result->get_error_message());
    }

    public function test_resolve_folder_id_returns_integer_for_existing_folder(): void
    {
        $folderRepo = $this->createMock(MSTV_Repository_Folders::class);
        $folderRepo->method('find')->with(12)->willReturn((object) ['id' => 12]);

        $controller = $this->buildController($folderRepo);
        $method = new ReflectionMethod(MSTV_REST_Controller::class, 'resolve_folder_id');
        $method->setAccessible(true);

        self::assertSame(12, $method->invoke($controller, '12'));
        self::assertNull($method->invoke($controller, null));
    }

    public function test_resolve_folder_id_rejects_non_integer_input(): void
    {
        $folderRepo = $this->createMock(MSTV_Repository_Folders::class);
        $folderRepo->expects(self::never())->method('find');

        $controller = $this->buildController($folderRepo);
        $method = new ReflectionMethod(MSTV_REST_Controller::class, 'resolve_folder_id');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '12abc');

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('Folder not found.', $result->get_error_message());
    }

    public function test_resolve_folder_id_rejects_non_string_scalar_input(): void
    {
        $folderRepo = $this->createMock(MSTV_Repository_Folders::class);
        $folderRepo->expects(self::never())->method('find');

        $controller = $this->buildController($folderRepo);
        $method = new ReflectionMethod(MSTV_REST_Controller::class, 'resolve_folder_id');
        $method->setAccessible(true);

        self::assertInstanceOf(WP_Error::class, $method->invoke($controller, 12.5));
        self::assertInstanceOf(WP_Error::class, $method->invoke($controller, true));
    }

    public function test_format_file_hides_preview_metadata_when_pdf_preview_is_disabled(): void
    {
        update_option('mstv_pdf_preview_enabled', false);

        $filesystem = $this->createMock(MSTV_Filesystem::class);
        $filesystem->method('is_file')->willReturn(true);
        $filesystem->method('get_mime_type')->willReturn('application/pdf');
        $filesystem->method('get_file_size')->willReturn(2048);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('get_filesystem')->willReturn($filesystem);

        $preview = $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock();
        $preview->expects(self::never())->method('get_preview_url');

        $download = $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock();
        $download->method('get_download_url')->willReturn('https://example.test/download');

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $this->createMock(MSTV_Validator::class),
            $this->createMock(MSTV_Repository_Folders::class),
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            $download,
            $preview,
            $this->createMock(MSTV_Logger::class)
        );

        $method = new ReflectionMethod(MSTV_REST_Controller::class, 'format_file');
        $method->setAccessible(true);

        $result = $method->invoke($controller, (object) [
            'id' => 7,
            'folder_id' => null,
            'original_name' => 'spec.pdf',
            'display_name' => 'Spec',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'relative_path' => 'spec.pdf',
            'created_at' => '2026-03-28 12:00:00',
            'created_by' => 1,
        ]);

        self::assertFalse($result['is_previewable']);
        self::assertNull($result['preview_url']);
    }

    public function test_format_file_uses_original_name_when_display_name_is_empty(): void
    {
        $filesystem = $this->createMock(MSTV_Filesystem::class);
        $filesystem->method('is_file')->willReturn(false);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('get_filesystem')->willReturn($filesystem);

        $preview = $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock();
        $download = $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock();

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $this->createMock(MSTV_Validator::class),
            $this->createMock(MSTV_Repository_Folders::class),
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            $download,
            $preview,
            $this->createMock(MSTV_Logger::class)
        );

        $method = new ReflectionMethod(MSTV_REST_Controller::class, 'format_file');
        $method->setAccessible(true);

        $result = $method->invoke($controller, (object) [
            'id' => 9,
            'folder_id' => null,
            'original_name' => 'photo_01.jpg',
            'display_name' => '',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'relative_path' => 'photo_01.jpg',
            'created_at' => '2026-03-28 12:00:00',
            'created_by' => 1,
        ]);

        self::assertSame('photo_01', $result['display_name']);
    }

    public function test_update_file_accepts_valid_name_even_if_existing_display_name_is_empty(): void
    {
        $validator = new MSTV_Validator(new MSTV_Settings());
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find')->willReturnOnConsecutiveCalls(
            (object) [
                'id' => 4,
                'display_name' => '',
                'original_name' => 'photo_01.jpg',
                'extension' => 'jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 100,
                'relative_path' => 'photo_01.jpg',
                'folder_id' => null,
                'created_at' => '2026-03-28 12:00:00',
                'created_by' => 1,
            ],
            (object) [
                'id' => 4,
                'display_name' => 'immagine',
                'original_name' => 'photo_01.jpg',
                'extension' => 'jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 100,
                'relative_path' => 'photo_01.jpg',
                'folder_id' => null,
                'created_at' => '2026-03-28 12:00:00',
                'created_by' => 1,
            ]
        );
        $filesRepo->expects(self::once())->method('rename')->with(4, 'immagine')->willReturn(true);

        $filesystem = $this->createMock(MSTV_Filesystem::class);
        $filesystem->method('is_file')->willReturn(false);
        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('get_filesystem')->willReturn($filesystem);

        $download = $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock();
        $preview = $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock();
        $logger = $this->createMock(MSTV_Logger::class);
        $logger->expects(self::once())->method('log_rename')->with('file', 4, '', 'immagine');

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $validator,
            $this->createMock(MSTV_Repository_Folders::class),
            $filesRepo,
            $download,
            $preview,
            $logger
        );

        $response = $controller->update_file(new WP_REST_Request([
            'id' => 4,
            'display_name' => 'immagine',
        ]));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertTrue($response->data['success']);
        self::assertSame('immagine', $response->data['data']['display_name']);
    }

    public function test_upload_file_persists_detected_storage_file_size(): void
    {
        $_FILES['file'] = [
            'name' => 'spec.pdf',
            'type' => 'application/pdf',
            'tmp_name' => __FILE__,
            'error' => UPLOAD_ERR_OK,
            'size' => 9999,
        ];

        $validator = $this->createMock(MSTV_Validator::class);
        $validator->method('validate_file_name')->willReturn([
            'valid' => true,
            'errors' => [],
        ]);
        $validator->method('validate_upload_full')->willReturn([
            'valid' => true,
            'errors' => [],
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 9999,
        ]);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('ensure_storage_directory')->willReturn(true);
        $storage->method('store_uploaded_file')->willReturn([
            'success' => true,
            'stored_name' => 'stored.pdf',
            'relative_path' => 'stored.pdf',
            'extension' => 'pdf',
            'file_size' => 1234,
            'checksum' => 'checksum',
        ]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->expects(self::once())
            ->method('create')
            ->with(self::callback(function (array $data): bool {
                self::assertSame(1234, $data['file_size']);

                return true;
            }))
            ->willReturn(42);
        $filesRepo->method('find')->willReturn((object) [
            'id' => 42,
            'folder_id' => null,
            'original_name' => 'spec.pdf',
            'display_name' => 'spec',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1234,
            'relative_path' => 'stored.pdf',
            'created_at' => '2026-04-17 10:00:00',
            'created_by' => 1,
        ]);

        $filesystem = $this->createMock(MSTV_Filesystem::class);
        $filesystem->method('is_file')->willReturn(false);
        $storage->method('get_filesystem')->willReturn($filesystem);

        $logger = $this->createMock(MSTV_Logger::class);
        $logger->expects(self::once())->method('log_upload')->with(42, 'spec');

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $validator,
            $this->createMock(MSTV_Repository_Folders::class),
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $logger
        );

        $response = $controller->upload_file(new WP_REST_Request());

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertTrue($response->data['success']);

        unset($_FILES['file']);
    }

    public function test_upload_acquires_and_releases_quota_lock_around_metadata_insert(): void
    {
        $_FILES['file'] = [
            'name' => 'spec.pdf',
            'type' => 'application/pdf',
            'tmp_name' => __FILE__,
            'error' => UPLOAD_ERR_OK,
            'size' => 9999,
        ];

        $validator = $this->createMock(MSTV_Validator::class);
        $validator->method('validate_file_name')->willReturn(['valid' => true, 'errors' => []]);
        $validator->method('validate_upload_full')->willReturn([
            'valid' => true,
            'errors' => [],
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 9999,
        ]);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('ensure_storage_directory')->willReturn(true);
        $storage->method('store_uploaded_file')->willReturn([
            'success' => true,
            'stored_name' => 'stored.pdf',
            'relative_path' => 'stored.pdf',
            'extension' => 'pdf',
            'file_size' => 1234,
            'checksum' => 'checksum',
        ]);
        $filesystem = $this->createMock(MSTV_Filesystem::class);
        $filesystem->method('is_file')->willReturn(false);
        $storage->method('get_filesystem')->willReturn($filesystem);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('create')->willReturn(42);
        $filesRepo->method('find')->willReturn((object) [
            'id' => 42,
            'folder_id' => null,
            'original_name' => 'spec.pdf',
            'display_name' => 'spec',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1234,
            'relative_path' => 'stored.pdf',
            'created_at' => '2026-04-17 10:00:00',
            'created_by' => 1,
        ]);

        $quota = $this->getMockBuilder(MSTV_Quota::class)->disableOriginalConstructor()->getMock();
        $quota->expects(self::once())->method('acquire_upload_lock')->willReturn(true);
        $quota->expects(self::once())->method('check_upload')->willReturn(null);
        $quota->expects(self::once())->method('release_upload_lock');

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $validator,
            $this->createMock(MSTV_Repository_Folders::class),
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class),
            null,
            $quota
        );

        $response = $controller->upload_file(new WP_REST_Request());

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertTrue($response->data['success']);

        unset($_FILES['file']);
    }

    public function test_upload_file_reports_size_limit_when_php_drops_oversized_request(): void
    {
        unset($_FILES['file']);
        $_SERVER['CONTENT_LENGTH'] = (string) (1024 * 1024 * 1024);

        $controller = $this->buildController($this->createMock(MSTV_Repository_Folders::class));
        $response = $controller->upload_file(new WP_REST_Request());

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('upload_too_large', $response->get_error_code());
        self::assertStringContainsString('exceeds the maximum allowed size', $response->get_error_message());

        unset($_SERVER['CONTENT_LENGTH']);
    }

    public function test_browser_data_response_disables_http_cache(): void
    {
        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_by_parent')->with(null)->willReturn([]);
        $folderRepo->method('find_all_with_hierarchy')->willReturn([]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find_by_folder_paginated')->willReturn([
            'items' => [],
            'pagination' => [
                'page' => 1,
                'per_page' => 50,
                'total_items' => 0,
                'total_pages' => 0,
                'has_prev' => false,
                'has_next' => false,
                'from_item' => 0,
                'to_item' => 0,
            ],
        ]);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('has_reindexable_content')->willReturn(false);
        $storage->method('get_storage_stats')->with($filesRepo)->willReturn([
            'plugin_used_bytes' => 0,
            'plugin_used_formatted' => '0 B',
        ]);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $this->createMock(MSTV_Validator::class),
            $folderRepo,
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );

        $response = $controller->get_browser_data(new WP_REST_Request());

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertStringContainsString('no-store', $response->get_headers()['Cache-Control'] ?? '');
    }

    public function test_search_users_does_not_expose_email_addresses(): void
    {
        $GLOBALS['pdm_test_users'] = [
            7 => (object) [
                'ID' => 7,
                'user_login' => 'editor',
                'user_email' => 'editor@example.test',
                'display_name' => 'Editorial User',
            ],
        ];

        $controller = $this->buildController($this->createMock(MSTV_Repository_Folders::class));
        $response = $controller->search_users(new WP_REST_Request(['q' => 'edi']));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(7, $response->data['data'][0]['id']);
        self::assertArrayNotHasKey('email', $response->data['data'][0]);

        $GLOBALS['pdm_test_users'] = [];
    }

    public function test_search_restricts_results_to_folders_the_user_can_view(): void
    {
        $permissions = $this->getMockBuilder(MSTV_Permissions::class)
            ->disableOriginalConstructor()
            ->getMock();
        // Grantable only on folder 10; folder 20 and root (null) are denied.
        $permissions->method('user_can')->willReturnCallback(
            static fn (int $userId, ?int $folderId, string $action): bool => $folderId === 10
        );

        $auth = $this->createMock(MSTV_Auth::class);
        $auth->method('get_current_user_id')->willReturn(3);

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_all')->willReturn([
            (object) ['id' => 10],
            (object) ['id' => 20],
        ]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->expects(self::once())
            ->method('search_paginated')
            ->with('report', null, 'display_name', 'ASC', 1, self::anything(), [10], false)
            ->willReturn(['items' => [], 'pagination' => $this->emptyPagination()]);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $auth,
            $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Validator::class),
            $folderRepo,
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class),
            $permissions
        );

        $response = $controller->search(new WP_REST_Request(['q' => 'report']));

        self::assertInstanceOf(WP_REST_Response::class, $response);
    }

    public function test_search_is_unrestricted_when_no_permission_engine_is_wired(): void
    {
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->expects(self::once())
            ->method('search_paginated')
            ->with('report', null, 'display_name', 'ASC', 1, self::anything())
            ->willReturn(['items' => [], 'pagination' => $this->emptyPagination()]);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Validator::class),
            $this->createMock(MSTV_Repository_Folders::class),
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );

        $response = $controller->search(new WP_REST_Request(['q' => 'report']));

        self::assertInstanceOf(WP_REST_Response::class, $response);
    }

    public function test_browser_skips_storage_reindex_when_index_is_populated(): void
    {
        $GLOBALS['pdm_test_transients'] = [];

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_by_parent')->willReturn([]);
        $folderRepo->method('find_all_with_hierarchy')->willReturn([]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('get_count')->willReturn(5);
        $filesRepo->method('find_by_folder_paginated')->willReturn([
            'items' => [], 'pagination' => $this->emptyPagination(),
        ]);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->expects(self::never())->method('reindex_storage_records');
        $storage->method('get_storage_stats')->willReturn(['plugin_used_bytes' => 0, 'plugin_used_formatted' => '0 B']);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(), $this->createMock(MSTV_Auth::class), $storage,
            $this->createMock(MSTV_Validator::class), $folderRepo, $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );

        $controller->get_browser_data(new WP_REST_Request());
    }

    public function test_browser_reindexes_when_index_empty_but_storage_has_content(): void
    {
        $GLOBALS['pdm_test_transients'] = [];

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_all')->willReturn([]);
        $folderRepo->method('find_by_parent')->willReturn([]);
        $folderRepo->method('find_all_with_hierarchy')->willReturn([]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('get_count')->willReturn(0);
        $filesRepo->method('find_by_folder_paginated')->willReturn([
            'items' => [], 'pagination' => $this->emptyPagination(),
        ]);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('has_reindexable_content')->willReturn(true);
        $storage->expects(self::once())->method('reindex_storage_records')->willReturn([
            'success' => true, 'folders_created' => 0, 'files_created' => 0, 'files_skipped' => 0,
        ]);
        $storage->method('get_storage_stats')->willReturn(['plugin_used_bytes' => 0, 'plugin_used_formatted' => '0 B']);

        $auth = $this->createMock(MSTV_Auth::class);
        $auth->method('get_current_user_id')->willReturn(1);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(), $auth, $storage,
            $this->createMock(MSTV_Validator::class), $folderRepo, $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );

        $controller->get_browser_data(new WP_REST_Request());
    }

    public function test_browser_allows_reindex_retry_after_metadata_failure(): void
    {
        $GLOBALS['pdm_test_transients'] = [];

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_all')->willReturn([]);
        $folderRepo->method('find_by_parent')->willReturn([]);
        $folderRepo->method('find_all_with_hierarchy')->willReturn([]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('get_count')->willReturn(0);
        $filesRepo->method('find_by_folder_paginated')->willReturn([
            'items' => [], 'pagination' => $this->emptyPagination(),
        ]);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('has_reindexable_content')->willReturn(true);
        $storage->expects(self::once())->method('reindex_storage_records')->willReturn([
            'success' => false, 'error' => 'Database write failed.',
        ]);
        $storage->method('get_storage_stats')->willReturn(['plugin_used_bytes' => 0, 'plugin_used_formatted' => '0 B']);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(), $this->createMock(MSTV_Auth::class), $storage,
            $this->createMock(MSTV_Validator::class), $folderRepo, $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );

        $controller->get_browser_data(new WP_REST_Request());

        self::assertFalse(get_transient('mstv_auto_reindex_1'));
    }

    public function test_create_folder_is_forbidden_without_manage_permission(): void
    {
        $permissions = $this->getMockBuilder(MSTV_Permissions::class)
            ->disableOriginalConstructor()
            ->getMock();
        $permissions->method('current_user_can')->willReturn(false);

        $auth = $this->createMock(MSTV_Auth::class);
        $auth->method('get_current_user_id')->willReturn(5);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->expects(self::never())->method('create_folder');

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $auth,
            $storage,
            $this->createMock(MSTV_Validator::class),
            $this->createMock(MSTV_Repository_Folders::class),
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class),
            $permissions
        );

        $result = $controller->create_folder(new WP_REST_Request(['name' => 'Reports', 'parent_id' => 7]));

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('mstv_forbidden', $result->get_error_code());
        self::assertSame(403, $result->data['status']);
    }

    public function test_browser_grants_full_permission_map_without_engine(): void
    {
        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_by_parent')->willReturn([]);
        $folderRepo->method('find_all_with_hierarchy')->willReturn([]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('get_count')->willReturn(3);
        $filesRepo->method('find_by_folder_paginated')->willReturn(['items' => [], 'pagination' => $this->emptyPagination()]);

        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('get_storage_stats')->willReturn(['plugin_used_bytes' => 0, 'plugin_used_formatted' => '0 B']);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $this->createMock(MSTV_Auth::class),
            $storage,
            $this->createMock(MSTV_Validator::class),
            $folderRepo,
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class)
        );

        $response = $controller->get_browser_data(new WP_REST_Request());

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertTrue($response->data['data']['permissions']['view']);
        self::assertTrue($response->data['data']['permissions']['manage']);
    }

    public function test_browser_promotes_shared_descendant_and_redacts_hidden_breadcrumb_parent(): void
    {
        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_by_parent')->willReturn([]);
        $folderRepo->method('find_all_with_hierarchy')->willReturn([
            [
                'id' => 10,
                'name' => 'Restricted',
                'slug' => 'restricted',
                'parent_id' => null,
                'has_children' => true,
                'children' => [[
                    'id' => 20,
                    'name' => 'Shared',
                    'slug' => 'shared',
                    'parent_id' => 10,
                    'has_children' => false,
                    'children' => [],
                ]],
            ],
        ]);
        $folderRepo->method('get_breadcrumb_data')->willReturn([
            ['id' => 10, 'name' => 'Restricted'],
            ['id' => 20, 'name' => 'Shared'],
        ]);

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('get_count')->willReturn(1);
        $filesRepo->method('find_by_folder_paginated')->willReturn([
            'items' => [],
            'pagination' => $this->emptyPagination(),
        ]);

        $permissions = $this->getMockBuilder(MSTV_Permissions::class)->disableOriginalConstructor()->getMock();
        $permissions->method('current_user_can')->willReturn(true);
        $permissions->method('ruled_folder_ids')->willReturn([]);
        $permissions->method('user_can')->willReturnCallback(
            static fn ($userId, $folderId, $action): bool => (int) $folderId === 20
        );

        $auth = $this->createMock(MSTV_Auth::class);
        $auth->method('get_current_user_id')->willReturn(7);
        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $storage->method('get_storage_stats')->willReturn(['plugin_used_bytes' => 0, 'plugin_used_formatted' => '0 B']);

        $controller = new MSTV_REST_Controller(
            new MSTV_Settings(),
            $auth,
            $storage,
            $this->createMock(MSTV_Validator::class),
            $folderRepo,
            $filesRepo,
            $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Logger::class),
            $permissions
        );

        $response = $controller->get_browser_data(new WP_REST_Request(['folder_id' => 20]));
        $tree = $response->data['data']['folder_tree'];

        self::assertCount(1, $tree);
        self::assertSame(20, $tree[0]['id']);
        self::assertNull($tree[0]['parent_id']);
        self::assertTrue($tree[0]['is_shared_root']);
        self::assertSame([['id' => 20, 'name' => 'Shared']], $response->data['data']['breadcrumb']);
    }

    private function emptyPagination(): array
    {
        return [
            'page' => 1, 'per_page' => 50, 'total_items' => 0, 'total_pages' => 0,
            'has_prev' => false, 'has_next' => false, 'from_item' => 0, 'to_item' => 0,
        ];
    }

    private function buildController(MSTV_Repository_Folders $folderRepo): MSTV_REST_Controller
    {
        $settings = new MSTV_Settings();
        $auth = $this->createMock(MSTV_Auth::class);
        $storage = $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock();
        $validator = $this->createMock(MSTV_Validator::class);
        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $download = $this->getMockBuilder(MSTV_Download::class)->disableOriginalConstructor()->getMock();
        $preview = $this->getMockBuilder(MSTV_Preview::class)->disableOriginalConstructor()->getMock();
        $logger = $this->createMock(MSTV_Logger::class);

        return new MSTV_REST_Controller(
            $settings,
            $auth,
            $storage,
            $validator,
            $folderRepo,
            $filesRepo,
            $download,
            $preview,
            $logger
        );
    }
}
