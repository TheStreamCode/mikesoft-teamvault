<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMAuthTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'pdm_use_user_whitelist' => false,
            'pdm_allowed_users' => [],
        ];
        $GLOBALS['pdm_test_is_user_logged_in'] = true;
        $GLOBALS['pdm_test_current_user_can'] = true;
        $GLOBALS['pdm_test_current_user_id'] = 5;
    }

    public function test_can_access_allows_capability_when_whitelist_is_disabled(): void
    {
        $auth = new PDM_Auth(new PDM_Settings());

        self::assertTrue($auth->can_access());
    }

    public function test_can_access_blocks_non_whitelisted_user_when_whitelist_is_enabled(): void
    {
        $GLOBALS['pdm_test_options']['pdm_use_user_whitelist'] = true;
        $GLOBALS['pdm_test_options']['pdm_allowed_users'] = [99];

        $auth = new PDM_Auth(new PDM_Settings());

        self::assertFalse($auth->can_access());
    }

    public function test_verify_request_returns_forbidden_for_non_whitelisted_user(): void
    {
        $GLOBALS['pdm_test_options']['pdm_use_user_whitelist'] = true;
        $GLOBALS['pdm_test_options']['pdm_allowed_users'] = [99];

        $auth = new PDM_Auth(new PDM_Settings());
        $request = new WP_REST_Request([], ['X-WP-Nonce' => 'valid-nonce']);
        $result = $auth->verify_request($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('pdm_forbidden', $result->code);
    }
}
