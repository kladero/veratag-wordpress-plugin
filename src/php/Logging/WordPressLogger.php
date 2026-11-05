<?php

namespace VeraTag\Logging;

/**
 * Emits operational failures to the PHP server log only when the current build enables logging.
 *
 * The low-level call is intentionally isolated here so runtime services do not choose logging
 * mechanisms or environment policy. ServiceFactory supplies the generated build-time setting.
 */
final class WordPressLogger implements Logger
{
    public function __construct(private bool $enabled)
    {
    }

    public function error(string $message): void
    {
        if (! $this->enabled) {
            return;
        }

        error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Central build-configured logging adapter.
    }
}
