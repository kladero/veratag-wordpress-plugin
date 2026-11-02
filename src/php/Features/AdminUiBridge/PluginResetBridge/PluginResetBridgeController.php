<?php

namespace VeraTag\Features\AdminUiBridge\PluginResetBridge;

use VeraTag\Features\PluginReset\PluginResetService;
use VeraTag\Features\PushApiConfiguration\PushApiConfigurationApiService;
use VeraTag\Settings\Services\GlobalSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * "Unregister from Channel": the admin-initiated counterpart to an
 * authenticated CHANNEL_DELETE push (see PushApiReceivingController) —
 * here the site, not the backend, decides to disconnect. First tells the
 * backend to remove this channel's push API configuration (the one write
 * the backend needs informed about before this plugin stops being
 * reachable/authenticated), then resets local plugin state back to a
 * fresh install exactly like a CHANNEL_DELETE push would.
 *
 * The backend call is attempted on a best-effort basis only in the sense
 * that its result does not block the reset: if the API key is already
 * missing/invalid, or the backend is unreachable, there is nothing left
 * to meaningfully unregister on the backend side anyway, and the site
 * admin's intent ("disconnect this site") must still be honored locally
 * regardless. A genuine backend error is still surfaced to the admin UI as
 * a warning after the reset completes, rather than silently swallowed.
 */
final class PluginResetBridgeController
{
    public function __construct(
        private readonly PushApiConfigurationApiService $push_api_service,
        private readonly GlobalSettings $settings
    ) {
    }

    public function unregisterFromChannel(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        $api_key                 = $this->settings->getApiKey();
        $backend_removal_warning = null;

        if ('' !== trim($api_key)) {
            $result = $this->push_api_service->remove($api_key);
            if (is_wp_error($result)) {
                $backend_removal_warning = $result->get_error_message();
            }
        }

        ( new PluginResetService() )->reset();

        return new WP_REST_Response(
            array(
                'ok'                    => true,
                'backendRemovalWarning' => $backend_removal_warning,
            ),
            200
        );
    }
}
