<?php

namespace VeraTag\Features\RenderScriptSync;

use VeraTag\Settings\OptionValues\SynchronizationStatusSource;
use VeraTag\VeraTagBackend\HttpResponse;

/** Mirrors PullSynchronizationApiFetchResult, but the payload is the raw JS body rather than a decoded DTO. */
final class RenderScriptSyncApiFetchResult
{
    public function __construct(
        private readonly HttpResponse $http_response,
        private readonly string $status_source,
        private readonly ?string $script_content = null,
        private readonly ?string $error_message = null
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->http_response->getStatusCode();
    }

    public function getStatusText(): string
    {
        return $this->http_response->getStatusText();
    }

    public function getStatusSource(): string
    {
        return SynchronizationStatusSource::normalize($this->status_source);
    }

    public function getScriptContent(): ?string
    {
        return $this->script_content;
    }

    public function getErrorMessage(): ?string
    {
        if (is_string($this->error_message) && '' !== trim($this->error_message)) {
            return $this->error_message;
        }

        return $this->http_response->getErrorMessage();
    }

    public function isSuccessful(): bool
    {
        return $this->http_response->isSuccessful()
            && null !== $this->script_content
            && null === $this->getErrorMessage();
    }
}
