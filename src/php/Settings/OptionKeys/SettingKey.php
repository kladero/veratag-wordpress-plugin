<?php

namespace VeraTag\Settings\OptionKeys;

final class SettingKey
{
    public const WORDPRESS_API_KEY                             = 'veratag_wordpress_api_key';
    public const CHANNEL_VISITOR_DISCLOSURE_LANGUAGE_SETTINGS  = 'veratag_channel_visitor_disclosure_language_settings';
    public const PULL_SYNCHRONIZATION_LATEST_ATTEMPT_STATUS    = 'veratag_disclosure_fetch_latest_fetch_status';
    public const PULL_SYNCHRONIZATION_NEXT_ATTEMPT_AT          = 'veratag_disclosure_fetch_next_fetch_timestamp';
    public const RENDER_SCRIPT_REQUIRED_FILE_NAME              = 'veratag_render_script_required_file_name';
    public const RENDER_SCRIPT_REQUIRED_VERSION                = 'veratag_render_script_required_version';
    public const RENDER_SCRIPT_LATEST_FETCH_STATUS             = 'veratag_render_script_latest_fetch_status';
    public const SCRIPT_FETCH_RECOVERY_NEXT_FETCH_TIMESTAMP    = 'veratag_script_fetch_recovery_next_fetch_timestamp';
    public const CHANNEL_DETAILS                               = 'veratag_channel_details';
    public const HAS_CONNECTED_SUCCESSFULLY_AT_LEAST_ONCE      = 'veratag_has_connected_successfully_at_least_once';
    public const VERATAG_DISCLOSURE_PERSISTENCE_SCHEMA_VERSION = 'veratag_disclosure_sync_schema_version';
    public const PUSH_API_ENABLED                              = 'veratag_push_api_enabled';
    public const PUSH_API_SECRET                               = 'veratag_push_api_secret';
    public const PUSH_API_LAST_RECEIVED_AT                     = 'veratag_push_api_last_received_at';
    public const VERATAG_PUSH_API_SCHEMA_VERSION               = 'veratag_push_api_schema_version';

    private function __construct()
    {
    }
}
