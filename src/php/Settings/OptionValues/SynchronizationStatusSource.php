<?php

namespace VeraTag\Settings\OptionValues;

final class SynchronizationStatusSource
{
    public const LOCAL_PLUGIN            = 'local-plugin';
    public const REMOTE_BACKEND_RESPONSE = 'remote-backend-response';
    public const REMOTE_HTTP_PROTOCOL    = 'remote-http-protocol';

    private function __construct()
    {
    }

    public static function normalize(string $status_source): string
    {
        $normalized_status_source = strtolower(trim($status_source));

        return match ($normalized_status_source) {
            self::LOCAL_PLUGIN => self::LOCAL_PLUGIN,
            self::REMOTE_BACKEND_RESPONSE => self::REMOTE_BACKEND_RESPONSE,
            self::REMOTE_HTTP_PROTOCOL => self::REMOTE_HTTP_PROTOCOL,
            default => self::LOCAL_PLUGIN,
        };
    }
}
