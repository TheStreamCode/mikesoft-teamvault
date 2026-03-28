<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [];
    }

    public function test_can_preview_blocks_pdf_when_pdf_preview_setting_is_disabled(): void
    {
        update_option('pdm_pdf_preview_enabled', false);

        $settings = new PDM_Settings();
        $storage = $this->getMockBuilder(PDM_Storage::class)->disableOriginalConstructor()->getMock();
        $filesRepo = $this->getMockBuilder(PDM_Repository_Files::class)->disableOriginalConstructor()->getMock();
        $auth = $this->createMock(PDM_Auth::class);
        $preview = new PDM_Preview($storage, $filesRepo, $auth, $settings);

        $file = (object) [
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
        ];

        self::assertFalse($preview->can_preview($file));
    }
}
