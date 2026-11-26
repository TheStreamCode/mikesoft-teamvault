<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_use_user_whitelist' => true,
            'mstv_allowed_users' => [5],
        ];
        $GLOBALS['pdm_test_users'] = [
            5 => new FakePDMUser(5, [MSTV_Capabilities::CAP_MANAGE => true]),
        ];
        $GLOBALS['pdm_test_user_meta'] = [
            5 => ['mstv_granted_capability' => true],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['pdm_test_max_upload_size']);
    }

    public function test_sync_capabilities_preserves_access_for_existing_legacy_whitelist_user(): void
    {
        $settings = new MSTV_Settings();

        $settings->sync_capabilities_on_whitelist_change([5], true);

        self::assertTrue($GLOBALS['pdm_test_users'][5]->has_cap(MSTV_Capabilities::CAP_MANAGE));
        self::assertTrue($GLOBALS['pdm_test_user_meta'][5]['mstv_granted_capability_1']);
        self::assertArrayNotHasKey('mstv_granted_capability', $GLOBALS['pdm_test_user_meta'][5]);
    }

    public function test_sync_capabilities_regrants_access_when_old_whitelist_is_disabled_but_allowed_users_persist(): void
    {
        $GLOBALS['pdm_test_options']['mstv_use_user_whitelist'] = false;

        $settings = new MSTV_Settings();

        $settings->sync_capabilities_on_whitelist_change([5], true);

        self::assertTrue($GLOBALS['pdm_test_users'][5]->has_cap(MSTV_Capabilities::CAP_MANAGE));
        self::assertTrue($GLOBALS['pdm_test_user_meta'][5]['mstv_granted_capability_1']);
        self::assertArrayNotHasKey('mstv_granted_capability', $GLOBALS['pdm_test_user_meta'][5]);
    }

    public function test_detects_storage_path_inside_wordpress_uploads(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_storage_path' => sys_get_temp_dir() . '/private-documents',
        ];

        $settings = new MSTV_Settings();

        self::assertTrue($settings->is_storage_path_inside_uploads());
    }

    public function test_detects_storage_path_outside_wordpress_uploads(): void
    {
        $externalPath = sys_get_temp_dir() . '/mstv-private-documents';
        if (!is_dir($externalPath)) {
            mkdir($externalPath, 0777, true);
        }
        file_put_contents($externalPath . '/.mstv-storage', 'marker');

        $GLOBALS['pdm_test_options'] = [
            'mstv_storage_path' => $externalPath,
        ];

        $settings = new MSTV_Settings();

        self::assertFalse($settings->is_storage_path_inside_uploads());

        @unlink($externalPath . '/.mstv-storage');
        @rmdir($externalPath);
    }

    public function test_effective_max_file_size_is_limited_by_server_upload_limit(): void
    {
        $GLOBALS['pdm_test_options']['mstv_max_file_size'] = 52428800;
        $GLOBALS['pdm_test_max_upload_size'] = 31457280;

        $settings = new MSTV_Settings();

        self::assertSame(31457280, $settings->get_server_max_upload_size());
        self::assertSame(31457280, $settings->get_effective_max_file_size());
        self::assertSame(31457280, $settings->clamp_max_file_size(52428800));
    }

    public function test_effective_max_file_size_keeps_a_smaller_configured_value(): void
    {
        $GLOBALS['pdm_test_options']['mstv_max_file_size'] = 10485760;
        $GLOBALS['pdm_test_max_upload_size'] = 31457280;

        $settings = new MSTV_Settings();

        self::assertSame(10485760, $settings->get_effective_max_file_size());
        self::assertSame(10485760, $settings->clamp_max_file_size(10485760));
    }

    public function test_effective_max_file_size_is_not_clamped_when_server_limit_is_unknown(): void
    {
        $GLOBALS['pdm_test_options']['mstv_max_file_size'] = 52428800;
        $GLOBALS['pdm_test_max_upload_size'] = 0;

        $settings = new MSTV_Settings();

        self::assertSame(0, $settings->get_server_max_upload_size());
        self::assertSame(52428800, $settings->get_effective_max_file_size());
        self::assertSame(52428800, $settings->clamp_max_file_size(52428800));
    }
}
