<?php

namespace VeraTag\Features\AdminUiBridge\SettingsBridge;

final class SettingsBridgeRouteRegistrar
{
    private const REST_NAMESPACE = 'veratag/v1';
    private const SETTINGS_ROUTE = '/settings';

    public function __construct(
        private readonly SettingsBridgeController $settings_bridge_controller
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::SETTINGS_ROUTE,
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->settings_bridge_controller, 'getSettings' ),
                    'permission_callback' => array( $this, 'canManageSettings' ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this->settings_bridge_controller, 'updateSettings' ),
                    'permission_callback' => array( $this, 'canManageSettings' ),
                ),
            )
        );
    }

    public function canManageSettings(): bool
    {
        return current_user_can('manage_options');
    }
}
