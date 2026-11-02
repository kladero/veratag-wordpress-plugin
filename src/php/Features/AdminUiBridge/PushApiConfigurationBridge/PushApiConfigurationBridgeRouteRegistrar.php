<?php

namespace VeraTag\Features\AdminUiBridge\PushApiConfigurationBridge;

final class PushApiConfigurationBridgeRouteRegistrar
{
    private const NS = 'veratag/v1';
    public function __construct(private readonly PushApiConfigurationBridgeController $controller)
    {
    }
    public function registerRoutes(): void
    {
        register_rest_route(
            self::NS,
            '/push-api',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->controller, 'getStatus' ),
                    'permission_callback' => array( $this, 'canManage' ),
                ),
                array(
                    'methods'             => 'PUT',
                    'callback'            => array( $this->controller, 'configure' ),
                    'permission_callback' => array( $this, 'canManage' ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array( $this->controller, 'remove' ),
                    'permission_callback' => array( $this, 'canManage' ),
                ),
            )
        );
        register_rest_route(
            self::NS,
            '/push-api/enabled',
            array(
                array(
                    'methods'             => 'PATCH',
                    'callback'            => array( $this->controller, 'setEnabled' ),
                    'permission_callback' => array( $this, 'canManage' ),
                ),
            )
        );
        register_rest_route(
            self::NS,
            '/push-api/recent',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->controller, 'recent' ),
                    'permission_callback' => array( $this, 'canManage' ),
                ),
            )
        );
    }
    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
