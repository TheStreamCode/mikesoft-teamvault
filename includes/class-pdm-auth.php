<?php

defined('ABSPATH') || exit;

class PDM_Auth
{
    private PDM_Settings $settings;

    public function __construct(PDM_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function can_access(): bool
    {
        return is_user_logged_in() && PDM_Capabilities::can_manage();
    }

    public function can_read(): bool
    {
        return $this->can_access();
    }

    public function can_write(): bool
    {
        return $this->can_access();
    }

    public function can_delete(): bool
    {
        return $this->can_access();
    }

    public function verify_request(\WP_REST_Request $request): bool|\WP_Error
    {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'pdm_unauthorized',
                __('Unauthorized access. Please log in.', 'private-document-manager'),
                ['status' => 401]
            );
        }

        if (!PDM_Capabilities::can_manage()) {
            return new \WP_Error(
                'pdm_forbidden',
                __('You do not have permission to access this resource.', 'private-document-manager'),
                ['status' => 403]
            );
        }

        $nonceCheck = $this->verify_nonce($request);
        if ($nonceCheck instanceof \WP_Error) {
            return $nonceCheck;
        }

        return true;
    }

    public function get_current_user_id(): int
    {
        return get_current_user_id();
    }

    public function verify_nonce(\WP_REST_Request $request): bool|\WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (empty($nonce)) {
            return new \WP_Error(
                'pdm_missing_nonce',
                __('Missing security token.', 'private-document-manager'),
                ['status' => 403]
            );
        }

        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'pdm_invalid_nonce',
                __('Invalid security token.', 'private-document-manager'),
                ['status' => 403]
            );
        }

        return true;
    }
}
