<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMNonceRejectionTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pdm_test_options'] = [
            'mstv_use_user_whitelist' => false,
            'mstv_allowed_users' => [],
        ];
        $GLOBALS['pdm_test_current_user_caps'] = [
            MSTV_Capabilities::CAP_MANAGE => true,
            'manage_options' => true,
        ];
        $GLOBALS['pdm_test_is_user_logged_in'] = true;
        $GLOBALS['pdm_test_current_user_id'] = 5;
        $GLOBALS['pdm_test_verified_nonce_actions'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['pdm_test_current_user_caps'] = [];
        $GLOBALS['pdm_test_is_user_logged_in'] = false;
        $GLOBALS['pdm_test_current_user_id'] = 0;
        $GLOBALS['pdm_test_verified_nonce_actions'] = [];
    }

    public function test_read_request_rejects_missing_nonce(): void
    {
        $auth = new MSTV_Auth(new MSTV_Settings());
        $request = new WP_REST_Request();

        $result = $auth->verify_read_request($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('mstv_missing_nonce', $result->get_error_code());
    }

    public function test_read_request_rejects_invalid_nonce(): void
    {
        $auth = new MSTV_Auth(new MSTV_Settings());
        $request = new WP_REST_Request([], ['X-WP-Nonce' => 'forged-nonce']);

        $result = $auth->verify_read_request($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('mstv_invalid_nonce', $result->get_error_code());
    }

    public function test_write_delete_and_admin_guards_reject_missing_nonce(): void
    {
        $auth = new MSTV_Auth(new MSTV_Settings());
        $request = new WP_REST_Request();

        foreach (['verify_write_request', 'verify_delete_request', 'verify_admin_request'] as $method) {
            $result = $auth->{$method}($request);

            self::assertInstanceOf(WP_Error::class, $result, "Guard {$method} did not reject a missing nonce.");
            self::assertSame('mstv_missing_nonce', $result->get_error_code());
        }
    }

    public function test_unauthenticated_request_is_rejected_before_nonce_check(): void
    {
        $GLOBALS['pdm_test_is_user_logged_in'] = false;

        $auth = new MSTV_Auth(new MSTV_Settings());
        $request = new WP_REST_Request();

        $result = $auth->verify_read_request($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('mstv_unauthorized', $result->get_error_code());
        self::assertSame([], $GLOBALS['pdm_test_verified_nonce_actions']);
    }

    public function test_authorized_request_records_the_rest_nonce_action(): void
    {
        $auth = new MSTV_Auth(new MSTV_Settings());
        $request = new WP_REST_Request([], ['X-WP-Nonce' => 'valid-nonce']);

        self::assertTrue($auth->verify_read_request($request));
        self::assertSame(['wp_rest'], $GLOBALS['pdm_test_verified_nonce_actions']);
    }
}
