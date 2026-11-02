<?php

namespace VeraTag\Features\AdminUiBridge\RenderScriptSyncBridge;

final class RenderScriptSyncBridgeRouteRegistrar
{
    private const REST_NAMESPACE = 'veratag/v1';
    private const STATUS_ROUTE   = '/render-script-sync/status';
    private const SYNC_ROUTE     = '/render-script-sync/pull';

    public function __construct(
        private readonly RenderScriptSyncBridgeController $render_script_sync_bridge_controller
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::STATUS_ROUTE,
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->render_script_sync_bridge_controller, 'getStatus' ),
                    'permission_callback' => array( $this, 'canManageSync' ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            self::SYNC_ROUTE,
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this->render_script_sync_bridge_controller, 'runSync' ),
                    'permission_callback' => array( $this, 'canManageSync' ),
                ),
            )
        );
    }

    public function canManageSync(): bool
    {
        return current_user_can('manage_options');
    }
}
