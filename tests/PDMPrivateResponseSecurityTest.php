<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMPrivateResponseSecurityTest extends TestCase
{
    public function test_private_binary_responses_keep_wordpress_no_cache_headers(): void
    {
        foreach (['class-mstv-download.php', 'class-mstv-preview.php', 'class-mstv-export.php'] as $file) {
            $source = (string) file_get_contents(__DIR__ . '/../includes/' . $file);

            self::assertStringContainsString('nocache_headers();', $source, $file);
            self::assertStringNotContainsString("header('Pragma: public');", $source, $file);
            self::assertStringNotContainsString('post-check=0, pre-check=0', $source, $file);
        }
    }

    public function test_download_and_preview_diagnostics_do_not_log_private_paths(): void
    {
        foreach (['class-mstv-download.php', 'class-mstv-preview.php'] as $file) {
            $source = (string) file_get_contents(__DIR__ . '/../includes/' . $file);

            self::assertStringNotContainsString("error_log('TeamVault: file not readable", $source, $file);
            self::assertStringNotContainsString('error_log(\'TeamVault: stream failed for download: \' . $path)', $source, $file);
            self::assertStringNotContainsString('error_log(\'TeamVault: stream failed for preview: \' . $path)', $source, $file);
        }
    }
}
