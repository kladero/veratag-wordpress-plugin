<?php

namespace VeraTag\Features\CompatibilityCheck;

/** Outcome of a backend release-compatibility check — see CompatibilityCheckApiService. */
final class CompatibilityStatus
{
    public const OK              = 'ok';
    public const UPDATE_REQUIRED = 'update_required';
    public const DISABLED        = 'disabled';

    private function __construct(
        private readonly string $status,
        private readonly ?string $message
    ) {
    }

    public static function ok(): self
    {
        return new self(self::OK, null);
    }

    public static function updateRequired(string $message): self
    {
        return new self(self::UPDATE_REQUIRED, $message);
    }

    public static function disabled(string $message): self
    {
        return new self(self::DISABLED, $message);
    }

    public function toArray(): array
    {
        return array(
            'status'  => $this->status,
            'message' => $this->message,
        );
    }
}
