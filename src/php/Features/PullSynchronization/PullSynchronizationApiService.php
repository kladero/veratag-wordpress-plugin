<?php

namespace VeraTag\Features\PullSynchronization;

use VeraTag\Settings\OptionValues\SynchronizationStatusSource;
use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpClient;
use VeraTag\VeraTagBackend\HttpEndpoints;
use VeraTag\VeraTagBackend\HttpResponse;

final class PullSynchronizationApiService
{
    public function __construct(
        private readonly HttpClient $http_client,
        private readonly HttpEndpoints $http_endpoints,
        private readonly GlobalSettings $global_settings
    ) {
    }

    public function fetchDisclosures(string $api_key): PullSynchronizationApiFetchResult
    {

        $normalized_api_key = trim($api_key);

        if ('' === $normalized_api_key) {
            return new PullSynchronizationApiFetchResult(
                new HttpResponse(0, '', array(), '', 'Missing Pull Synchronization API Key.'),
                SynchronizationStatusSource::LOCAL_PLUGIN,
                null,
                'Missing Pull Synchronization API Key.'
            );
        }

        $disclosures_url      = $this->http_endpoints->getDisclosuresUrl();
        $url_validation_error = $this->validateEndpointUrl($disclosures_url);

        if (null !== $url_validation_error) {
            return new PullSynchronizationApiFetchResult(
                new HttpResponse(0, '', array(), '', $url_validation_error),
                SynchronizationStatusSource::LOCAL_PLUGIN,
                null,
                $url_validation_error
            );
        }

        return $this->mapHttpResponse($this->httpApiCallFetchDisclosures($normalized_api_key));
    }

    private function httpApiCallFetchDisclosures(string $api_key): HttpResponse
    {
        return $this->http_client->get(
            $this->http_endpoints->getDisclosuresUrl(),
            array( 'Authorization' => 'Bearer ' . $api_key )
        );
    }

    private function validateEndpointUrl(string $url): ?string
    {
        $normalized_url = strtolower(trim($url));

        if ('' === $normalized_url) {
            return 'Missing disclosures endpoint URL.';
        }

        if (0 !== strpos($normalized_url, 'https://')) {
            return 'Disclosures endpoint URL must use HTTPS.';
        }

        return null;
    }

    /**
     * Describes a non-2xx response with as much real detail as is available,
     * rather than a generic "request failed" — mirrors
     * RenderScriptSyncApiService::describeUnsuccessfulResponse exactly; see
     * that method's docs for the full rationale.
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

    private function mapHttpResponse(HttpResponse $response): PullSynchronizationApiFetchResult
    {
        if ($response->hasError()) {
            return new PullSynchronizationApiFetchResult(
                $response,
                SynchronizationStatusSource::REMOTE_HTTP_PROTOCOL,
                null,
                $response->getErrorMessage()
            );
        }

        if (! $response->isSuccessful()) {
            return new PullSynchronizationApiFetchResult(
                $response,
                SynchronizationStatusSource::REMOTE_HTTP_PROTOCOL,
                null,
                $this->describeUnsuccessfulResponse($response)
            );
        }

        $payload = json_decode($response->getBody(), true);

        if (! is_array($payload)) {
            return new PullSynchronizationApiFetchResult(
                $response,
                SynchronizationStatusSource::REMOTE_BACKEND_RESPONSE,
                null,
                'Backend response body is not valid JSON.'
            );
        }

        $payload = $this->restoreEmptyObjectShapes($payload, $response->getBody());

        return new PullSynchronizationApiFetchResult(
            $response,
            SynchronizationStatusSource::REMOTE_BACKEND_RESPONSE,
            PullSynchronizationApiResponseDto::fromArray($payload),
            null
        );
    }

    /**
     * json_decode($body, true) collapses an empty JSON object ("{}") and an
     * empty JSON array ("[]") into the same empty PHP array — every
     * placement's params tree can contain both kinds (e.g. an unused
     * primitive's "content": {} alongside a genuinely empty list like
     * "classNames": []), and this whole pipeline (fromArray()'s DTOs,
     * PullSynchronizationPersistenceService's storage, and eventually
     * wp_json_encode() when the WordPress plugin serves it back out) only
     * ever works with that array form, so the distinction is lost for good
     * once it round-trips through here — the renderer's decoder then
     * rejects a "{}" that came back as "[]".
     *
     * This decodes the same body a second time as stdClass (which DOES keep
     * objects distinguishable from arrays), walks it in parallel with the
     * already-decoded array payload, and replaces any node that is an empty
     * array in both forms with a genuine PHP stdClass — which
     * PullSynchronizationPersistenceService's encodeJson() (a thin
     * wp_json_encode() wrapper) then correctly re-serializes as "{}". Every
     * downstream consumer that only reads array-shaped fields (channelDetails,
     * pages, placementName, …) is unaffected; only params subtrees actually
     * contain schema-defined objects deep enough to hit this.
     */
    private function restoreEmptyObjectShapes(array $payload, string $raw_body): array
    {
        $object_form = json_decode($raw_body);

        if (! ( $object_form instanceof \stdClass )) {
            return $payload;
        }

        return $this->mergeEmptyObjectShapes($payload, $object_form);
    }

    private function mergeEmptyObjectShapes(mixed $array_node, mixed $object_node): mixed
    {
        if (is_array($array_node) && array() === $array_node && $object_node instanceof \stdClass) {
            return new \stdClass();
        }

        if (is_array($array_node) && $object_node instanceof \stdClass) {
            $object_properties = get_object_vars($object_node);
            foreach ($array_node as $key => $value) {
                if (array_key_exists($key, $object_properties)) {
                    $array_node[ $key ] = $this->mergeEmptyObjectShapes($value, $object_properties[ $key ]);
                }
            }
            return $array_node;
        }

        if (is_array($array_node) && is_array($object_node)) {
            foreach ($array_node as $index => $value) {
                if (array_key_exists($index, $object_node)) {
                    $array_node[ $index ] = $this->mergeEmptyObjectShapes($value, $object_node[ $index ]);
                }
            }
            return $array_node;
        }

        return $array_node;
    }
}
