<?php

namespace VeraTag\Settings\OptionValues;

/**
 * The backend-ground-truth visitor disclosure language settings for this
 * channel — synced down via /pull-synchronization (see
 * PullSynchronizationScheduler::updateChannelDetailsFromApiResponse) and
 * editable from the admin UI, which writes through to the backend before
 * ever being stored here (see PushApiConfigurationBridgeController-style
 * write-through endpoints). Never defaulted/guessed locally — until the
 * first successful sync, {@see createInitial} with empty strings is the
 * only state, and the admin UI must not show the configuration section
 * until a real value has been received at least once.
 */
final class ChannelVisitorDisclosureLanguageSettings
{
    public function __construct(
        private readonly string $preferred_mode,
        private readonly string $fallback_language_bcp47
    ) {
    }

    public static function createInitial(): self
    {
        return new self('', '');
    }

    public static function fromArray(array $value): self
    {
        $preferred_mode          = isset($value['preferredMode']) && is_string($value['preferredMode'])
            ? trim($value['preferredMode'])
            : '';
        $fallback_language_bcp47 = isset($value['fallbackLanguageBcp47']) && is_string($value['fallbackLanguageBcp47'])
            ? trim($value['fallbackLanguageBcp47'])
            : '';

        return new self($preferred_mode, $fallback_language_bcp47);
    }

    public function getPreferredMode(): string
    {
        return $this->preferred_mode;
    }

    public function getFallbackLanguageBcp47(): string
    {
        return $this->fallback_language_bcp47;
    }

    /** True once a real value has been received from the backend at least once — never true for {@see createInitial}'s empty state. */
    public function hasBeenSynced(): bool
    {
        return '' !== $this->preferred_mode && '' !== $this->fallback_language_bcp47;
    }

    public function toArray(): array
    {
        return array(
            'preferredMode'         => $this->preferred_mode,
            'fallbackLanguageBcp47' => $this->fallback_language_bcp47,
        );
    }
}
