<?php

namespace VeraTag\Features\VisitorDisclosureLanguageSettings;

use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpClient;
use VeraTag\VeraTagBackend\HttpEndpoints;
use VeraTag\VeraTagBackend\HttpResponse;
use WP_Error;

/**
 * Outbound write-through client for the backend's own visitor disclosure
 * language settings endpoint — the backend is the ground truth for
 * {@code preferredMode}/{@code fallbackLanguageBcp47}; this plugin never
 * writes either value locally without the backend confirming it first (see
 * VisitorDisclosureLanguageSettingsBridgeController, which calls this and
 * only persists the backend's own returned values, mirroring
 * PushApiConfigurationApiService's pattern).
 */
final class VisitorDisclosureLanguageSettingsApiService
{
    public function __construct(
        private readonly HttpClient $http_client,
        private readonly HttpEndpoints $http_endpoints,
        private readonly GlobalSettings $settings
    ) {
    }

    public function update(string $api_key, string $preferred_mode, string $fallback_language_bcp47): array|WP_Error
    {
        $api_key = trim($api_key);
        if ('' === $api_key) {
            return $this->missingApiKey();
        }
        return $this->mapResponse($this->httpApiCallUpdate($api_key, $preferred_mode, $fallback_language_bcp47));
    }

    private function httpApiCallUpdate(string $api_key, string $preferred_mode, string $fallback_language_bcp47): HttpResponse
    {
        return $this->http_client->put(
            $this->http_endpoints->getVisitorDisclosureLanguageSettingsUrl(),
            array( 'Authorization' => 'Bearer ' . $api_key ),
            array(
                'preferredMode'         => $preferred_mode,
                'fallbackLanguageBcp47' => $fallback_language_bcp47,
            )
        );
    }

    private function mapResponse(HttpResponse $response): array|WP_Error
    {
        if ($response->hasError()) {
            return new WP_Error('veratag_visitor_disclosure_language_settings_backend_error', (string) $response->getErrorMessage(), array( 'status' => 502 ));
        }
        if (! $response->isSuccessful()) {
            $message = $this->extractErrorMessage($response->getBody())
                ?? sprintf('Visitor disclosure language settings backend request failed (%d %s).', $response->getStatusCode(), $response->getStatusText());
            $status  = $response->getStatusCode() >= 400 && $response->getStatusCode() < 600 ? $response->getStatusCode() : 502;
            return new WP_Error('veratag_visitor_disclosure_language_settings_backend_error', $message, array( 'status' => $status ));
        }
        $decoded = json_decode($response->getBody(), true);
        if (! is_array($decoded)) {
            return new WP_Error('veratag_visitor_disclosure_language_settings_invalid_backend_response', 'Backend response was not valid JSON.', array( 'status' => 502 ));
        }
        return array(
            'preferredMode'         => isset($decoded['preferredMode']) && is_string($decoded['preferredMode']) ? trim($decoded['preferredMode']) : '',
            'fallbackLanguageBcp47' => isset($decoded['fallbackLanguageBcp47']) && is_string($decoded['fallbackLanguageBcp47']) ? trim($decoded['fallbackLanguageBcp47']) : '',
        );
    }

    private function extractErrorMessage(string $body): ?string
    {
        $decoded = json_decode($body, true);
        return is_array($decoded) && is_string($decoded['message'] ?? null) && '' !== trim($decoded['message']) ? trim($decoded['message']) : null;
    }

    private function missingApiKey(): WP_Error
    {
        return new WP_Error('veratag_visitor_disclosure_language_settings_missing_api_key', 'Missing Pull Synchronization API Key.', array( 'status' => 400 ));
    }
}
