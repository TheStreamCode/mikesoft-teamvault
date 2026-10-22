<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAssetsTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['pdm_test_current_screen_id'] = '';
    }

    public function test_effective_client_upload_limit_uses_lowest_positive_limit(): void
    {
        $assets = new MSTV_Assets(new MSTV_Settings());
        $method = new ReflectionMethod(MSTV_Assets::class, 'get_effective_client_upload_limit');
        $method->setAccessible(true);

        self::assertSame(5 * 1024 * 1024, $method->invoke($assets, 50 * 1024 * 1024, '8M', '5M'));
    }

    public function test_admin_body_class_marks_only_teamvault_screens(): void
    {
        $assets = new MSTV_Assets(new MSTV_Settings());

        $GLOBALS['pdm_test_current_screen_id'] = 'teamvault_page_mikesoft-teamvault-settings';
        self::assertSame('wp-admin pdm-admin-page', $assets->add_admin_body_class('wp-admin'));

        $GLOBALS['pdm_test_current_screen_id'] = 'toplevel_page_mikesoft-teamvault';
        self::assertSame('wp-admin pdm-admin-page', $assets->add_admin_body_class('wp-admin'));

        $GLOBALS['pdm_test_current_screen_id'] = 'edit-post';
        self::assertSame('wp-admin', $assets->add_admin_body_class('wp-admin'));
    }
}
