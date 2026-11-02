<?php

namespace VeraTag\Features\AdminUiBridge\VisitorDisclosureLanguageSettingsBridge;

final class VisitorDisclosureLanguageSettingsBridgeRouteRegistrar
{
    private const REST_NAMESPACE = 'veratag/v1';
    private const ROUTE          = '/visitor-disclosure-language-settings';

    public function __construct(
        private readonly VisitorDisclosureLanguageSettingsBridgeController $controller
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::ROUTE,
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this->controller, 'getSettings' ),
                    'permission_callback' => array( $this, 'canManage' ),
                ),
                array(
                    'methods'             => 'PUT',
                    'callback'            => array( $this->controller, 'updateSettings' ),
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
