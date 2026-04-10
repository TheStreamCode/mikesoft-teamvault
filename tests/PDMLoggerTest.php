<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMLoggerTest extends TestCase
{
    public function test_normalize_target_type_maps_legacy_files_value(): void
    {
        self::assertSame('file', MSTV_Logger::normalize_target_type('files'));
    }

    public function test_normalize_target_type_keeps_folder_value(): void
    {
        self::assertSame('folder', MSTV_Logger::normalize_target_type('folder'));
    }
}
