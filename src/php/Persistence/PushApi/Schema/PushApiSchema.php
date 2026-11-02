<?php

namespace VeraTag\Persistence\PushApi\Schema;

use VeraTag\Settings\OptionKeys\SettingKey;

final class PushApiSchema
{
    private const SCHEMA_VERSION = '1.0.0';
    public function __construct(private readonly PushReceivedLogTable $log_table)
    {
    }
    public function maybeUpgrade(): void
    {
        $installed = get_option(SettingKey::VERATAG_PUSH_API_SCHEMA_VERSION);
        if (self::SCHEMA_VERSION === $installed && $this->log_table->exists()) {
            return;
        }
        $this->log_table->drop();
        $this->log_table->create();
        update_option(SettingKey::VERATAG_PUSH_API_SCHEMA_VERSION, self::SCHEMA_VERSION, false);
    }
    public function dropAll(): void
    {
        $this->log_table->drop();
    }
    /** Drops and immediately recreates the push-received-log table — same operation maybeUpgrade() performs routinely, just forced rather than version-gated. Used when the log must actually be cleared regardless of whether the schema version already matches (e.g. PluginResetService). */
    public function resetLog(): void
    {
        $this->log_table->drop();
        $this->log_table->create();
    }
}
