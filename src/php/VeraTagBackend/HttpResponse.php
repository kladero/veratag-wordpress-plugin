<?php

namespace VeraTag\VeraTagBackend;

final class HttpResponse
{
    public function __construct(
        private readonly int $status_code,
        private readonly string $status_text,
        private readonly array $headers,
        private readonly string $body,
        private readonly ?string $error_message = null
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    public function getStatusText(): string
    {
        return $this->status_text;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function hasError(): bool
    {
        return null !== $this->error_message && '' !== trim($this->error_message);
    }

    /**
     * The backend's response envelope carries an explicit "status" string
     * (e.g. "CMS_PLUGIN_UPDATE_REQUIRED", "CMS_PLUGIN_DISABLED") that is
     * more specific than the HTTP status code alone — a plain 403 can also
     * mean an unrelated permission failure, so callers that need to tell
     * those apart should match on this, not on getStatusCode().
     */
    public function getBackendStatus(): ?string
    {
        $decoded = $this->decodeBody();
        if (! is_array($decoded) || ! isset($decoded['status']) || ! is_string($decoded['status'])) {
            return null;
        }
        $status = trim($decoded['status']);
        return '' !== $status ? $status : null;
    }

    /**
     * The backend's error responses are JSON with a "message" field —
     * extract it if the body decodes that way, else null. Centralizes what
     * was previously duplicated per API service (e.g.
     * PullSynchronizationApiService::extractBackendMessage,
     * RenderScriptSyncApiService's equivalent).
     */
    public function getBackendMessage(): ?string
    {
        $decoded = $this->decodeBody();
        if (! is_array($decoded) || ! isset($decoded['message']) || ! is_string($decoded['message'])) {
            return null;
        }

        $message = trim($decoded['message']);

        return '' !== $message ? $message : null;
    }

    private function decodeBody(): mixed
    {
        if ('' === trim($this->body)) {
            return null;
        }
        return json_decode($this->body, true);
    }
}
