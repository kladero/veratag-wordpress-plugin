<?php

namespace VeraTag\Features\PullSynchronization;

/** Mirrors CmsPluginChannelDetailsDto; paths are preserved verbatim because their nested keys are backend-owned routing metadata. */
final class PullSynchronizationApiChannelDetailsDto
{
    public function __construct(
        private readonly string $display_name,
        private readonly array $paths,
        private readonly ?PullSynchronizationApiEmbeddedPushStatusDto $push_status,
        private readonly string $visitor_preferred_disclosure_language_mode,
        private readonly string $visitor_fallback_disclosure_language_bcp47
    ) {
    }
    public static function fromArray(array $value): self
    {
        return new self(
            isset($value['displayName']) && is_string($value['displayName']) ? trim($value['displayName']) : '',
            isset($value['paths']) && is_array($value['paths']) ? $value['paths'] : array(),
            isset($value['pushApiStatus']) && is_array($value['pushApiStatus']) ? PullSynchronizationApiEmbeddedPushStatusDto::fromArray($value['pushApiStatus']) : null,
            isset($value['visitorPreferredDisclosureLanguageMode']) && is_string($value['visitorPreferredDisclosureLanguageMode']) ? trim($value['visitorPreferredDisclosureLanguageMode']) : '',
            isset($value['visitorFallbackDisclosureLanguageBcp47']) && is_string($value['visitorFallbackDisclosureLanguageBcp47']) ? trim($value['visitorFallbackDisclosureLanguageBcp47']) : ''
        );
    }
    public function getDisplayName(): string
    {
        return $this->display_name;
    }
    public function getPaths(): array
    {
        return $this->paths;
    }
    public function getPushStatus(): ?PullSynchronizationApiEmbeddedPushStatusDto
    {
        return $this->push_status;
    }
    public function getVisitorPreferredDisclosureLanguageMode(): string
    {
        return $this->visitor_preferred_disclosure_language_mode;
    }
    public function getVisitorFallbackDisclosureLanguageBcp47(): string
    {
        return $this->visitor_fallback_disclosure_language_bcp47;
    }
    public function toArray(): array
    {
        return array(
            'displayName'                            => $this->display_name,
            'paths'                                  => $this->paths,
            'pushApiStatus'                          => $this->push_status?->toArray(),
            'visitorPreferredDisclosureLanguageMode' => $this->visitor_preferred_disclosure_language_mode,
            'visitorFallbackDisclosureLanguageBcp47' => $this->visitor_fallback_disclosure_language_bcp47,
        );
    }
}
