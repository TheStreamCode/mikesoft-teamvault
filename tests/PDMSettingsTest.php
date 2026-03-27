<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'pdm_use_user_whitelist' => true,
            'pdm_allowed_users' => [5],
        ];
        $GLOBALS['pdm_test_users'] = [
            5 => new FakePDMUser(5, [PDM_Capabilities::CAP_MANAGE => true]),
        ];
        $GLOBALS['pdm_test_user_meta'] = [
            5 => ['pdm_granted_capability' => true],
        ];
    }

    public function test_sync_capabilities_preserves_access_for_existing_legacy_whitelist_user(): void
    {
        $settings = new PDM_Settings();

        $settings->sync_capabilities_on_whitelist_change([5], true);

        self::assertTrue($GLOBALS['pdm_test_users'][5]->has_cap(PDM_Capabilities::CAP_MANAGE));
        self::assertTrue($GLOBALS['pdm_test_user_meta'][5]['pdm_granted_capability_1']);
        self::assertArrayNotHasKey('pdm_granted_capability', $GLOBALS['pdm_test_user_meta'][5]);
    }

    public function test_sync_capabilities_regrants_access_when_old_whitelist_is_disabled_but_allowed_users_persist(): void
    {
        $GLOBALS['pdm_test_options']['pdm_use_user_whitelist'] = false;

        $settings = new PDM_Settings();

        $settings->sync_capabilities_on_whitelist_change([5], true);

        self::assertTrue($GLOBALS['pdm_test_users'][5]->has_cap(PDM_Capabilities::CAP_MANAGE));
        self::assertTrue($GLOBALS['pdm_test_user_meta'][5]['pdm_granted_capability_1']);
        self::assertArrayNotHasKey('pdm_granted_capability', $GLOBALS['pdm_test_user_meta'][5]);
    }
}
