<?php

namespace VeraTag\Features\PullSynchronization;

/** Mirrors CmsPluginChannelPushApiStatusDto (the narrower status embedded in a disclosure pull). */
final class PullSynchronizationApiEmbeddedPushStatusDto
{
    public function __construct(
        private readonly bool $configured,
        private readonly bool $enabled,
        private readonly bool $has_secret,
        private readonly ?string $secret_stub,
        private readonly ?string $last_push_attempt_at,
        private readonly ?string $last_push_attempt_status,
        private readonly ?string $last_push_attempt_status_message,
        private readonly ?string $last_successful_push_at,
        private readonly ?string $last_successful_push_message
    ) {
    }
    public static function fromArray(array $v): self
    {
        return new self(
            (bool) ( $v['configured'] ?? false ),
            (bool) ( $v['enabled'] ?? false ),
            (bool) ( $v['hasSecret'] ?? false ),
            self::nullableString($v['secretStub'] ?? null),
            self::nullableString($v['lastPushAttemptAt'] ?? null),
            self::nullableString($v['lastPushAttemptStatus'] ?? null),
            self::nullableString($v['lastPushAttemptStatusMessage'] ?? null),
            self::nullableString($v['lastSuccessfulPushAt'] ?? null),
            self::nullableString($v['lastSuccessfulPushMessage'] ?? null)
        );
    }
    public function toArray(): array
    {
        return array(
            'configured'                   => $this->configured,
            'enabled'                      => $this->enabled,
            'hasSecret'                    => $this->has_secret,
            'secretStub'                   => $this->secret_stub,
            'lastPushAttemptAt'            => $this->last_push_attempt_at,
            'lastPushAttemptStatus'        => $this->last_push_attempt_status,
            'lastPushAttemptStatusMessage' => $this->last_push_attempt_status_message,
            'lastSuccessfulPushAt'         => $this->last_successful_push_at,
            'lastSuccessfulPushMessage'    => $this->last_successful_push_message,
        );
    }
    private static function nullableString(mixed $v): ?string
    {
        return is_string($v) && '' !== trim($v) ? trim($v) : null;
    }
}
