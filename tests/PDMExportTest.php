<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMExportTest extends TestCase
{
    public function test_temp_zip_paths_are_unique_for_repeated_exports_in_same_second(): void
    {
        $export = $this->buildExport();
        $method = new ReflectionMethod(MSTV_Export::class, 'create_temp_zip_path');
        $method->setAccessible(true);

        $first = $method->invoke($export, 'documents-export');
        $second = $method->invoke($export, 'documents-export');

        try {
            self::assertNotSame($first, $second);
            self::assertStringEndsWith('.zip', $first);
            self::assertStringEndsWith('.zip', $second);
        } finally {
            if (is_string($first) && file_exists($first)) {
                wp_delete_file($first);
            }

            if (is_string($second) && file_exists($second)) {
                wp_delete_file($second);
            }
        }
    }

    public function test_restricted_folder_names_are_omitted_while_shared_descendants_remain_exportable(): void
    {
        if (!class_exists('ZipArchive')) {
            self::markTestSkipped('ZipArchive is not available in this environment.');
        }

        $folderRepo = $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock();
        $folderRepo->method('find_by_parent')->willReturnCallback(static function ($parentId): array {
            if ($parentId === null) {
                return [(object) ['id' => 10, 'name' => 'Restricted']];
            }

            if ((int) $parentId === 10) {
                return [(object) ['id' => 20, 'name' => 'Shared']];
            }

            return [];
        });

        $filesRepo = $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $filesRepo->method('find_by_folder')->willReturn([]);

        $permissions = $this->getMockBuilder(MSTV_Permissions::class)->disableOriginalConstructor()->getMock();
        $permissions->method('current_user_can')->willReturnCallback(
            static fn ($folderId, $action): bool => $folderId === null || (int) $folderId === 20
        );

        $export = new MSTV_Export(
            $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock(),
            $filesRepo,
            $folderRepo,
            $this->createMock(MSTV_Auth::class),
            $permissions
        );
        $path = wp_tempnam('teamvault-export-test.zip');
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        $method = new ReflectionMethod(MSTV_Export::class, 'add_folder_to_zip');
        $method->setAccessible(true);
        $method->invoke($export, $zip, null, '');
        $zip->close();

        self::assertTrue($zip->open($path));
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }
        $zip->close();
        wp_delete_file($path);

        self::assertNotContains('Restricted/', $entries);
        self::assertContains('Shared/', $entries);
    }

    private function buildExport(): MSTV_Export
    {
        return new MSTV_Export(
            $this->getMockBuilder(MSTV_Storage::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Repository_Files::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(MSTV_Repository_Folders::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(MSTV_Auth::class)
        );
    }
}
