<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMHelpersTest extends TestCase
{
    public function test_sanitize_archive_entry_segment_removes_path_characters(): void
    {
        self::assertSame(
            'Quarterly-Report',
            PDM_Helpers::sanitize_archive_entry_segment("../Quarterly\\Report\r\n")
        );
    }

    public function test_build_safe_download_filename_preserves_extension_once(): void
    {
        self::assertSame(
            'budget-summary.pdf',
            PDM_Helpers::build_safe_download_filename('budget/summary', 'pdf')
        );
    }

    public function test_resolve_file_display_name_falls_back_to_original_basename_when_empty(): void
    {
        self::assertSame(
            'photo_01',
            PDM_Helpers::resolve_file_display_name('', 'photo_01.jpg')
        );
    }

    public function test_resolve_file_display_name_prefers_explicit_name_when_valid(): void
    {
        self::assertSame(
            'immagine',
            PDM_Helpers::resolve_file_display_name('immagine', 'photo_01.jpg')
        );
    }
}
