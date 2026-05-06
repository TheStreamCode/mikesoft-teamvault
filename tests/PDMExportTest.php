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
