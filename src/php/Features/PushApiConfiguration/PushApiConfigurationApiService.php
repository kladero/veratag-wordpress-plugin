<?php

namespace VeraTag\Features\PushApiConfiguration;

use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpClient;
use VeraTag\VeraTagBackend\HttpEndpoints;
use VeraTag\VeraTagBackend\HttpResponse;
use WP_Error;

/** Outbound management client for the backend's CMS push-api registration endpoints. */
final class PushApiConfigurationApiService
{
    private const UNINSTALL_REQUEST_TIMEOUT_SECONDS = 3;
    public function __construct(private readonly HttpClient $http_client, private readonly HttpEndpoints $http_endpoints, private readonly GlobalSettings $settings)
    {
    }

    public function getStatus(string $api_key): array|WP_Error
    {
        $api_key = trim($api_key);
        if ('' === $api_key) {
            return $this->missingApiKey();
        }
        $response = $this->httpApiCallGetStatus($api_key);
        return $this->mapStatusResponse($response);
    }
    public function configure(string $api_key, string $endpoint_url, string $secret): array|WP_Error
    {
        $api_key = trim($api_key);
        if ('' === $api_key) {
            return $this->missingApiKey();
        }
        $response = $this->httpApiCallConfigure($api_key, $endpoint_url, $secret);
        return $this->mapStatusResponse($response);
    }
    public function setEnabled(string $api_key, bool $enabled): array|WP_Error
    {
        $api_key = trim($api_key);
        if ('' === $api_key) {
            return $this->missingApiKey();
        }
        $response = $this->httpApiCallSetEnabled($api_key, $enabled);
        return $this->mapStatusResponse($response);
    }
    public function remove(string $api_key): array|WP_Error
    {
        $api_key = trim($api_key);
        if ('' === $api_key) {
            return $this->missingApiKey();
        }
        $response = $this->httpApiCallRemove($api_key);
        if ($response->isSuccessful() && '' === trim($response->getBody())) {
            return self::emptyStatus();
        }
        return $this->mapStatusResponse($response);
    }
    /** Uses a short timeout because uninstall must never wait on an unavailable backend. */
    public function removeForUninstall(string $api_key): array|WP_Error
    {
        $api_key = trim($api_key);
        if ('' === $api_key) {
            return $this->missingApiKey();
        }
        $response = $this->httpApiCallRemove($api_key, self::UNINSTALL_REQUEST_TIMEOUT_SECONDS);
        if ($response->isSuccessful() && '' === trim($response->getBody())) {
            return self::emptyStatus();
        }
        return $this->mapStatusResponse($response);
    }

    private function httpApiCallGetStatus(string $api_key): HttpResponse
    {
        return $this->http_client->get($this->http_endpoints->getPushApiUrl(), $this->auth($api_key));
    }
    private function httpApiCallConfigure(string $api_key, string $endpoint_url, string $secret): HttpResponse
    {
        return $this->http_client->put(
            $this->http_endpoints->getPushApiUrl(),
            $this->auth($api_key),
            array(
                'endpointUrl' => $endpoint_url,
                'secret'      => $secret,
            )
        );
    }
    private function httpApiCallSetEnabled(string $api_key, bool $enabled): HttpResponse
    {
        return $this->http_client->patch($this->http_endpoints->getPushApiEnabledUrl(), $this->auth($api_key), array( 'enabled' => $enabled ));
    }
    private function httpApiCallRemove(string $api_key, ?int $timeout = null): HttpResponse
    {
        return null === $timeout
            ? $this->http_client->delete($this->http_endpoints->getPushApiUrl(), $this->auth($api_key))
            : $this->http_client->delete($this->http_endpoints->getPushApiUrl(), $this->auth($api_key), null, $timeout);
    }

    private function mapStatusResponse(HttpResponse $response): array|WP_Error
    {
        // A genuine transport failure (DNS, connection refused, timeout) - the backend never
        // actually responded, so 502 Bad Gateway is the correct, honest status to report here.
        if ($response->hasError()) {
            return new WP_Error('veratag_push_api_backend_error', (string) $response->getErrorMessage(), array( 'status' => 502 ));
        }
        if (! $response->isSuccessful()) {
            // The backend DID respond, just not with 2xx (e.g. 403 missing permission, 404 channel
            // not found, 401 bad API key) - surface its real status code instead of masking every
            // non-2xx response as a generic 502, which hides the actual cause from the admin UI/logs.
            $message = $this->extractErrorMessage($response->getBody()) ?? sprintf('Push API backend request failed (%d %s).', $response->getStatusCode(), $response->getStatusText());
            $status  = $response->getStatusCode() >= 400 && $response->getStatusCode() < 600 ? $response->getStatusCode() : 502;
            return new WP_Error(
                'veratag_push_api_backend_error',
                $message,
                array(
                    'status'         => $status,
                    'backend_status' => $response->getStatusCode(),
                )
            );
        }
        $decoded = json_decode($response->getBody(), true);
        if (! is_array($decoded)) {
            return new WP_Error('veratag_push_api_invalid_backend_response', 'Push API backend response was not valid JSON.', array( 'status' => 502 ));
        }
        return self::normalizeStatus($decoded);
    }
    private function auth(string $api_key): array
    {
        return array( 'Authorization' => 'Bearer ' . $api_key );
    }
    private function missingApiKey(): WP_Error
    {
        return new WP_Error('veratag_push_api_missing_api_key', 'Missing Pull Synchronization API Key.', array( 'status' => 400 ));
    }
    private function extractErrorMessage(string $body): ?string
    {
        $decoded = json_decode($body, true);
        return is_array($decoded) && is_string($decoded['message'] ?? null) && '' !== trim($decoded['message']) ? trim($decoded['message']) : null;
    }
    public static function normalizeStatus(array $v): array
    {
        return array(
            'configured'                   => (bool) ( $v['configured'] ?? false ),
            'enabled'                      => (bool) ( $v['enabled'] ?? false ),
            'endpointUrl'                  => self::nullableString($v['endpointUrl'] ?? null),
            'hasCredential'                => (bool) ( $v['hasCredential'] ?? false ),
            'secretStub'                   => self::nullableString($v['secretStub'] ?? null),
            'lastPushAttemptAt'            => self::nullableString($v['lastPushAttemptAt'] ?? null),
            'lastPushAttemptStatus'        => self::nullableString($v['lastPushAttemptStatus'] ?? null),
            'lastPushAttemptStatusMessage' => self::nullableString($v['lastPushAttemptStatusMessage'] ?? null),
            'lastSuccessfulPushAt'         => self::nullableString($v['lastSuccessfulPushAt'] ?? null),
            'lastSuccessfulPushMessage'    => self::nullableString($v['lastSuccessfulPushMessage'] ?? null),
        );
    }
    public static function emptyStatus(): array
    {
        return self::normalizeStatus(array());
    }
    private static function nullableString(mixed $v): ?string
    {
        return is_string($v) && '' !== trim($v) ? trim($v) : null;
    }
}
