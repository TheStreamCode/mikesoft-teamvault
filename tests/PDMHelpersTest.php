<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMHelpersTest extends TestCase
{
    public function test_sanitize_archive_entry_segment_removes_path_characters(): void
    {
        self::assertSame(
            'Quarterly-Report',
            MSTV_Helpers::sanitize_archive_entry_segment("../Quarterly\\Report\r\n")
        );
    }

    public function test_build_safe_download_filename_preserves_extension_once(): void
    {
        self::assertSame(
            'budget-summary.pdf',
            MSTV_Helpers::build_safe_download_filename('budget/summary', 'pdf')
        );
    }

    public function test_resolve_file_display_name_falls_back_to_original_basename_when_empty(): void
    {
        self::assertSame(
            'photo_01',
            MSTV_Helpers::resolve_file_display_name('', 'photo_01.jpg')
        );
    }

    public function test_resolve_file_display_name_prefers_explicit_name_when_valid(): void
    {
        self::assertSame(
            'immagine',
            MSTV_Helpers::resolve_file_display_name('immagine', 'photo_01.jpg')
        );
    }

    public function test_protection_htaccess_denies_access_on_apache_2_2_and_2_4(): void
    {
        $dir = sys_get_temp_dir() . '/mstv-protect-' . uniqid();
        mkdir($dir);

        try {
            MSTV_Helpers::create_protection_files($dir);
            $htaccess = (string) file_get_contents($dir . '/.htaccess');

            // Apache 2.4 native directive and the 2.2 compatibility fallback must both be present.
            self::assertStringContainsString('Require all denied', $htaccess);
            self::assertStringContainsString('Deny from all', $htaccess);
            self::assertStringContainsString('mod_authz_core.c', $htaccess);
        } finally {
            foreach (['/.htaccess', '/web.config', '/index.php', '/.mstv-storage'] as $file) {
                if (file_exists($dir . $file)) {
                    unlink($dir . $file);
                }
            }
            rmdir($dir);
        }
    }
}
