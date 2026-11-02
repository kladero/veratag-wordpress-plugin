<?php

namespace VeraTag\Features\AdminUiBridge\PluginResetBridge;

final class PluginResetBridgeRouteRegistrar
{
    private const REST_NAMESPACE = 'veratag/v1';
    private const ROUTE          = '/unregister-from-channel';

    public function __construct(
        private readonly PluginResetBridgeController $controller
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::ROUTE,
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this->controller, 'unregisterFromChannel' ),
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
