<?php

namespace VeraTag\Settings\OptionValues;

final class PushApiSettings
{
    public function __construct(private readonly bool $enabled, private readonly string $secret, private readonly ?string $last_received_at)
    {
    }
    public static function createInitial(): self
    {
        return new self(false, '', null);
    }
    public static function fromArray(array $data): self
    {
        return new self(
            true === ( $data['enabled'] ?? false ) || 1 === ( $data['enabled'] ?? null ) || '1' === ( $data['enabled'] ?? null ),
            isset($data['secret']) && is_string($data['secret']) ? trim($data['secret']) : '',
            isset($data['lastReceivedAt']) && is_string($data['lastReceivedAt']) && '' !== trim($data['lastReceivedAt']) ? trim($data['lastReceivedAt']) : null
        );
    }
    public function toArray(): array
    {
        return array(
            'enabled'        => $this->enabled,
            'secret'         => $this->secret,
            'lastReceivedAt' => $this->last_received_at,
        );
    }
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    public function getSecret(): string
    {
        return $this->secret;
    }
    public function getLastReceivedAt(): ?string
    {
        return $this->last_received_at;
    }
}
