<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMValidatorTest extends TestCase
{
    public function test_previewable_extension_requires_matching_mime_type(): void
    {
        self::assertTrue(MSTV_Helpers::is_previewable('jpg', 'image/jpeg'));
        self::assertFalse(MSTV_Helpers::is_previewable('jpg', 'text/html'));
        self::assertFalse(MSTV_Helpers::is_previewable('txt', 'image/jpeg'));
    }

    public function test_validator_requires_mime_to_match_extension(): void
    {
        $validator = new MSTV_Validator(new MSTV_Settings());

        self::assertTrue($validator->validate_extension_mime_pair('pdf', 'application/pdf'));
        self::assertTrue($validator->validate_extension_mime_pair('docx', 'application/zip'));
        self::assertFalse($validator->validate_extension_mime_pair('jpg', 'text/html'));
        self::assertFalse($validator->validate_extension_mime_pair('pdf', 'image/jpeg'));
    }

    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [];
    }

    public function test_default_allowed_extensions_do_not_include_svg(): void
    {
        $settings = new MSTV_Settings();

        self::assertNotContains('svg', $settings->get_allowed_extensions());
    }

    public function test_existing_saved_extensions_are_normalized_to_remove_svg(): void
    {
        update_option('mstv_allowed_extensions', 'pdf,jpg,svg,txt');

        $settings = new MSTV_Settings();

        self::assertSame(['pdf', 'jpg', 'txt'], $settings->get_allowed_extensions());
    }

    public function test_validator_rejects_svg_even_if_extensions_are_filtered_back_in(): void
    {
        $settings = new class extends MSTV_Settings {
            public function get_allowed_extensions(): array
            {
                return ['svg'];
            }
        };

        $validator = new MSTV_Validator($settings);

        self::assertFalse($validator->validate_extension('svg'));
    }

    public function test_scan_file_content_rejects_svg_event_handlers(): void
    {
        $settings = new MSTV_Settings();
        $validator = new MSTV_Validator($settings);
        $tempFile = tempnam(sys_get_temp_dir(), 'pdm-svg-');

        file_put_contents($tempFile, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');

        $result = $validator->scan_file_content($tempFile);

        @unlink($tempFile);

        self::assertFalse($result['valid']);
        self::assertNotEmpty($result['errors']);
    }

    public function test_scan_file_content_allows_plain_text_script_snippets(): void
    {
        $settings = new MSTV_Settings();
        $validator = new MSTV_Validator($settings);
        $tempFile = tempnam(sys_get_temp_dir(), 'pdm-txt-');

        file_put_contents($tempFile, 'Example documentation mentioning <script> tags and onload= handlers.');

        $result = $validator->scan_file_content($tempFile, 'txt', 'text/plain');

        @unlink($tempFile);

        self::assertTrue($result['valid']);
        self::assertSame([], $result['errors']);
    }

    public function test_scan_file_content_allows_plain_text_php_snippets(): void
    {
        $settings = new MSTV_Settings();
        $validator = new MSTV_Validator($settings);
        $tempFile = tempnam(sys_get_temp_dir(), 'pdm-php-text-');

        file_put_contents($tempFile, "Example docs: <?php echo 'hello'; ?>");

        $result = $validator->scan_file_content($tempFile, 'txt', 'text/plain');

        @unlink($tempFile);

        self::assertTrue($result['valid']);
        self::assertSame([], $result['errors']);
    }
}
