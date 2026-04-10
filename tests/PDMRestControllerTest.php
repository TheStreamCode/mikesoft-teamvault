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
