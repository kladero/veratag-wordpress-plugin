<?php

namespace VeraTag\Features\PushApiReceiving;

use VeraTag\Settings\Services\GlobalSettings;
use WP_Error;
use WP_REST_Request;

final class PushApiReceivingRouteRegistrar
{
    private const REST_NAMESPACE = 'veratag/v1';
    private const RECEIVE_ROUTE  = '/push-api/receive';
    public function __construct(private readonly PushApiReceivingController $controller, private readonly GlobalSettings $settings)
    {
    }
    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::RECEIVE_ROUTE,
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this->controller, 'receivePush' ),
                    'permission_callback' => array( $this, 'isAuthenticPushRequest' ),
                ),
            )
        );
    }
    public function isAuthenticPushRequest(WP_REST_Request $request): bool|WP_Error
    {
        $push_api_settings = $this->settings->getPushApiSettings();
        if (! $push_api_settings->isEnabled()) {
            return new WP_Error('veratag_push_api_disabled', 'Push API receiving is disabled.', array( 'status' => 403 ));
        }
        $expected = $push_api_settings->getSecret();
        $header   = trim((string) $request->get_header('authorization'));
        if ('' === $expected || ! preg_match('/^Bearer\s+(.+)$/i', $header, $matches) || ! hash_equals($expected, trim($matches[1]))) {
            return new WP_Error('veratag_push_api_unauthorized', 'Invalid push API credential.', array( 'status' => 401 ));
        }
        return true;
    }
}
