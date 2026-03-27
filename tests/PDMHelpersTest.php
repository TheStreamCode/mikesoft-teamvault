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
}
