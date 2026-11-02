<?php

namespace VeraTag\Features\AdminUiBridge\ContentOverviewBridge;

final class ContentOverviewBridgeRouteRegistrar
{
    private const REST_NAMESPACE  = 'veratag/v1';
    private const CONTENT_ROUTE   = '/content-overview';
    private const PAGE_URLS_ROUTE = '/content-overview/page-urls';

    public function __construct(
        private readonly ContentOverviewBridgeController $content_overview_bridge_controller
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::CONTENT_ROUTE,
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->content_overview_bridge_controller, 'getContentPage' ),
                    'permission_callback' => array( $this->content_overview_bridge_controller, 'canViewContentOverview' ),
                ),
            )
        );
        register_rest_route(
            self::REST_NAMESPACE,
            self::PAGE_URLS_ROUTE,
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->content_overview_bridge_controller, 'getPageUrls' ),
                    'permission_callback' => array( $this->content_overview_bridge_controller, 'canViewContentOverview' ),
                ),
            )
        );
    }
}
