<?php

namespace VeraTag\Features\AdminUiBridge\VerificationFileBridge;

final class VerificationFileBridgeRouteRegistrar
{
    private const REST_NAMESPACE          = 'veratag/v1';
    private const VERIFICATION_FILE_ROUTE = '/verification-file';

    public function __construct(
        private readonly VerificationFileBridgeController $verification_file_bridge_controller
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::VERIFICATION_FILE_ROUTE,
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->verification_file_bridge_controller, 'getVerificationFile' ),
                    'permission_callback' => array( $this, 'canManageVerificationFile' ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this->verification_file_bridge_controller, 'configureVerificationFile' ),
                    'permission_callback' => array( $this, 'canManageVerificationFile' ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array( $this->verification_file_bridge_controller, 'removeVerificationFile' ),
                    'permission_callback' => array( $this, 'canManageVerificationFile' ),
                ),
            )
        );
    }

    public function canManageVerificationFile(): bool
    {
        return current_user_can('manage_options');
    }
}
