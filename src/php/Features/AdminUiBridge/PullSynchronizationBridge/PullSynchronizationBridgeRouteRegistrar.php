<?php

namespace VeraTag\Features\AdminUiBridge\PullSynchronizationBridge;

final class PullSynchronizationBridgeRouteRegistrar
{
    private const REST_NAMESPACE = 'veratag/v1';
    private const STATUS_ROUTE   = '/pull-synchronization/status';
    private const SYNC_ROUTE     = '/pull-synchronization/run';

    public function __construct(
        private readonly PullSynchronizationBridgeController $pull_synchronization_bridge_controller
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
                    'callback'            => array( $this->pull_synchronization_bridge_controller, 'getStatus' ),
                    'permission_callback' => array( $this, 'canManageSynchronization' ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            self::SYNC_ROUTE,
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this->pull_synchronization_bridge_controller, 'runSynchronization' ),
                    'permission_callback' => array( $this, 'canManageSynchronization' ),
                ),
            )
        );
    }

    public function canManageSynchronization(): bool
    {
        return current_user_can('manage_options');
    }
}
