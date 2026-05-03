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
        $externalPath = dirname(sys_get_temp_dir()) . '/mstv-private-documents';
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
}
