<?php

namespace VeraTag\Features\CompatibilityCheck;

use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpClient;
use VeraTag\VeraTagBackend\HttpEndpoints;
use VeraTag\VeraTagBackend\HttpResponse;

/**
 * Checks whether this plugin's own release is still accepted by the
 * backend's CMS plugin release-compatibility gate, on every admin page
 * load (not on the 45-minute pull-synchronization cron) — so the admin UI
 * notices an unsupported version or a disabled integration promptly
 * instead of waiting out the next scheduled sync. Piggybacks on the
 * cheapest existing read-only authenticated endpoint (push-api status)
 * purely to reach the gate check every backend request runs; the push-api
 * status payload itself is discarded here, since this service's only job
 * is reading the compatibility outcome off the HTTP response.
 *
 * The backend's response body carries an explicit "status" string
 * ("CMS_PLUGIN_UPDATE_REQUIRED", "CMS_PLUGIN_DISABLED") that is more
 * specific than the HTTP status code alone — a plain 403 can also mean an
 * unrelated API-key permission failure on this same endpoint, so matching
 * is done on that status string, never on the message text or the bare
 * HTTP code.
 */
final class CompatibilityCheckApiService
{
    private const BACKEND_STATUS_UPDATE_REQUIRED = 'CMS_PLUGIN_UPDATE_REQUIRED';
    private const BACKEND_STATUS_DISABLED        = 'CMS_PLUGIN_DISABLED';

    public function __construct(
        private readonly HttpClient $http_client,
        private readonly HttpEndpoints $http_endpoints,
        private readonly GlobalSettings $global_settings
    ) {
    }

    public function check(): CompatibilityStatus
    {

        $api_key = trim($this->global_settings->getApiKey());

        if ('' === $api_key) {
            // No key configured yet; nothing to check against the backend,
            // and this is not a compatibility problem.
            return CompatibilityStatus::ok();
        }

        $response = $this->http_client->get(
            $this->http_endpoints->getPushApiUrl(),
            array( 'Authorization' => 'Bearer ' . $api_key )
        );

        return $this->mapResponse($response);
    }

    private function mapResponse(HttpResponse $response): CompatibilityStatus
    {
        $backend_status = $response->getBackendStatus();

        if (self::BACKEND_STATUS_UPDATE_REQUIRED === $backend_status) {
            $message = $response->getBackendMessage()
                ?? 'This plugin version is no longer supported. Please update.';

            return CompatibilityStatus::updateRequired($message);
        }

        if (self::BACKEND_STATUS_DISABLED === $backend_status) {
            $message = $response->getBackendMessage()
                ?? 'This integration is currently disabled on the vera[tag] platform.';

            return CompatibilityStatus::disabled($message);
        }

        // Any other outcome (success, transport error, an unrelated
        // permission failure, etc.) is not this check's concern — those
        // are reported by the features that actually depend on their own
        // call succeeding.
        return CompatibilityStatus::ok();
    }
}
