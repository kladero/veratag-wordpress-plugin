<?php

namespace VeraTag\Features\AdminUiBridge\RawDisclosuresBridge;

final class RawDisclosuresBridgeRouteRegistrar
{
    private const REST_NAMESPACE        = 'veratag/v1';
    private const RAW_DISCLOSURES_ROUTE = '/raw-disclosures';

    public function __construct(
        private readonly RawDisclosuresBridgeController $raw_disclosures_bridge_controller
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::RAW_DISCLOSURES_ROUTE,
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->raw_disclosures_bridge_controller, 'getRawDisclosures' ),
                    'permission_callback' => array( $this, 'canViewRawDisclosures' ),
                ),
            )
        );
    }

    public function canViewRawDisclosures(): bool
    {
        return current_user_can('manage_options');
    }
}
