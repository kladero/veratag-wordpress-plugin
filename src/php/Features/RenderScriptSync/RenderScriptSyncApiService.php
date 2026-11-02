<?php

namespace VeraTag\Features\RenderScriptSync;

use VeraTag\Settings\OptionValues\SynchronizationStatusSource;
use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpClient;
use VeraTag\VeraTagBackend\HttpEndpoints;
use VeraTag\VeraTagBackend\HttpResponse;

/**
 * Mirrors PullSynchronizationApiService's shape but calls
 * GET /api/cms-integrations/render-script?version=... instead,
 * which returns the raw JavaScript source as its response body (no JSON
 * envelope) rather than a decodable DTO — so there is nothing in the
 * response to independently verify the version against; a 200 OK with a
 * non-empty body IS the confirmation that the requested version was
 * fetched (see backend CmsPluginController.getRenderScript, which
 * 404s if the version does not exist at all).
 */
final class RenderScriptSyncApiService
{
    private const MAXIMUM_RENDER_SCRIPT_BYTES = 1048576;

    public function __construct(
        private readonly HttpClient $http_client,
        private readonly HttpEndpoints $http_endpoints,
        private readonly GlobalSettings $global_settings
    ) {
    }

    public function fetchRenderScript(string $api_key, string $version): RenderScriptSyncApiFetchResult
    {
        $normalized_api_key = trim($api_key);
        $normalized_version = trim($version);

        if ('' === $normalized_api_key) {
            return new RenderScriptSyncApiFetchResult(
                new HttpResponse(0, '', array(), '', 'Missing Pull Synchronization API Key.'),
                SynchronizationStatusSource::LOCAL_PLUGIN,
                null,
                'Missing Pull Synchronization API Key.'
            );
        }

        if ('' === $normalized_version) {
            return new RenderScriptSyncApiFetchResult(
                new HttpResponse(0, '', array(), '', 'Missing render script version.'),
                SynchronizationStatusSource::LOCAL_PLUGIN,
                null,
                'Missing render script version.'
            );
        }

        $render_script_url    = $this->http_endpoints->getRenderScriptUrl($normalized_version);
        $url_validation_error = $this->validateEndpointUrl($render_script_url, $normalized_version);

        if (null !== $url_validation_error) {
            return new RenderScriptSyncApiFetchResult(
                new HttpResponse(0, '', array(), '', $url_validation_error),
                SynchronizationStatusSource::LOCAL_PLUGIN,
                null,
                $url_validation_error
            );
        }

        return $this->mapHttpResponse($this->httpApiCallFetchRenderScript($normalized_api_key, $render_script_url));
    }

    private function httpApiCallFetchRenderScript(string $api_key, string $render_script_url): HttpResponse
    {
        return $this->http_client->get(
            $render_script_url,
            array( 'Authorization' => 'Bearer ' . $api_key ),
            20,
            self::MAXIMUM_RENDER_SCRIPT_BYTES + 1
        );
    }

    private function validateEndpointUrl(string $url, string $version): ?string
    {
        $expected_url = $this->http_endpoints->getRenderScriptUrl($version);

        if (! hash_equals($expected_url, $url)) {
            return 'Render script endpoint URL does not match the configured VeraTag backend endpoint.';
        }

        return null;
    }

    /**
     * Describes a non-2xx response with as much real detail as is available,
     * rather than a generic "request failed" — the backend's error
     * responses are JSON with a "message" field (see backend
     * GlobalExceptionHandler/ApiResponse), so that is preferred; if the body
     * isn't parseable JSON (e.g. a reverse proxy or gateway error page, or
     * the backend being entirely unreachable at the network level and
     * returning something other than the app's own error shape), the
     * status code/text plus a short excerpt of the raw body is used
     * instead, so the admin always sees the actual HTTP status and whatever
     * the backend/proxy actually said, not a static string.
     */
    private function describeUnsuccessfulResponse(HttpResponse $response): string
    {
        $status_code  = $response->getStatusCode();
        $status_text  = trim($response->getStatusText());
        $status_label = 0 !== $status_code
            ? ( '' !== $status_text ? "{$status_code} {$status_text}" : (string) $status_code )
            : 'Unknown status';

        $backend_message = $this->extractBackendMessage($response->getBody());
        if (null !== $backend_message) {
            return "({$status_label}) - {$backend_message}";
        }

        $body_excerpt = $this->excerptBody($response->getBody());
        if ('' !== $body_excerpt) {
            return "({$status_label}) - {$body_excerpt}";
        }

        return "({$status_label}) - Request failed with no further details.";
    }

    /** The backend's error responses are JSON with a "message" field — extract it if the body decodes that way. */
    private function extractBackendMessage(string $body): ?string
    {
        if ('' === trim($body)) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! isset($decoded['message']) || ! is_string($decoded['message'])) {
            return null;
        }

        $message = trim($decoded['message']);

        return '' !== $message ? $message : null;
    }

    private function excerptBody(string $body): string
    {
        $trimmed_body = trim(wp_strip_all_tags($body));

        if ('' === $trimmed_body) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($trimmed_body, 0, 200);
        }

        return substr($trimmed_body, 0, 200);
    }

    private function mapHttpResponse(HttpResponse $response): RenderScriptSyncApiFetchResult
    {
        if ($response->hasError()) {
            return new RenderScriptSyncApiFetchResult(
                $response,
                SynchronizationStatusSource::REMOTE_HTTP_PROTOCOL,
                null,
                $response->getErrorMessage()
            );
        }

        if (! $response->isSuccessful()) {
            return new RenderScriptSyncApiFetchResult(
                $response,
                SynchronizationStatusSource::REMOTE_HTTP_PROTOCOL,
                null,
                $this->describeUnsuccessfulResponse($response)
            );
        }

        $script_content = $response->getBody();

        if (self::MAXIMUM_RENDER_SCRIPT_BYTES < strlen($script_content)) {
            return new RenderScriptSyncApiFetchResult(
                $response,
                SynchronizationStatusSource::REMOTE_BACKEND_RESPONSE,
                null,
                'Render script response exceeds the maximum permitted size.'
            );
        }

        if ('' === trim($script_content)) {
            return new RenderScriptSyncApiFetchResult(
                $response,
                SynchronizationStatusSource::REMOTE_BACKEND_RESPONSE,
                null,
                'Backend response body is empty.'
            );
        }

        return new RenderScriptSyncApiFetchResult(
            $response,
            SynchronizationStatusSource::REMOTE_BACKEND_RESPONSE,
            $script_content,
            null
        );
    }
}
