<?php

namespace VeraTag\Features\PullSynchronization;

use VeraTag\Settings\OptionValues\SynchronizationStatusSource;
use VeraTag\VeraTagBackend\HttpResponse;

final class PullSynchronizationApiFetchResult
{
    public function __construct(
        private readonly HttpResponse $http_response,
        private readonly string $status_source,
        private readonly ?PullSynchronizationApiResponseDto $api_response_dto = null,
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

    public function getApiResponseDto(): ?PullSynchronizationApiResponseDto
    {
        return $this->api_response_dto;
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
            && null !== $this->api_response_dto
            && null === $this->getErrorMessage();
    }
}
