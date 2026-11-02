<?php

namespace VeraTag\Settings\OptionValues;

/**
 * Dashboard deep-link paths for this channel, each relative to
 * `veratagDashboardBaseUrl` — mirrors the backend's `CmsPluginChannelPathsDto`
 * (see `FrontendRouteConverterUtils` on the backend, which builds every one
 * of these from the same channel). Persisted verbatim from the most recent
 * successful pull synchronization's `channelDetails.paths`.
 */
final class ChannelPaths
{
    public function __construct(
        private readonly string $channel_dashboard_path,
        private readonly string $channel_disclosure_configurations_path,
        private readonly string $channel_disclosure_text_templates_path,
        private readonly string $channel_disclosure_placements_path,
        private readonly string $channel_content_management_path,
        private readonly string $channel_settings_path
    ) {
    }

    public static function createInitial(): self
    {
        return new self('', '', '', '', '', '');
    }

    public static function fromArray(array $value): self
    {
        return new self(
            self::readString($value, 'channelDashboardPath'),
            self::readString($value, 'channelDisclosureConfigurationsPath'),
            self::readString($value, 'channelDisclosureTextTemplatesPath'),
            self::readString($value, 'channelDisclosurePlacementsPath'),
            self::readString($value, 'channelContentManagementPath'),
            self::readString($value, 'channelSettingsPath')
        );
    }

    public function getChannelDashboardPath(): string
    {
        return $this->channel_dashboard_path;
    }

    public function getChannelDisclosureConfigurationsPath(): string
    {
        return $this->channel_disclosure_configurations_path;
    }

    public function getChannelDisclosureTextTemplatesPath(): string
    {
        return $this->channel_disclosure_text_templates_path;
    }

    public function getChannelDisclosurePlacementsPath(): string
    {
        return $this->channel_disclosure_placements_path;
    }

    public function getChannelContentManagementPath(): string
    {
        return $this->channel_content_management_path;
    }

    public function getChannelSettingsPath(): string
    {
        return $this->channel_settings_path;
    }

    public function toArray(): array
    {
        return array(
            'channelDashboardPath'                => $this->channel_dashboard_path,
            'channelDisclosureConfigurationsPath' => $this->channel_disclosure_configurations_path,
            'channelDisclosureTextTemplatesPath'  => $this->channel_disclosure_text_templates_path,
            'channelDisclosurePlacementsPath'     => $this->channel_disclosure_placements_path,
            'channelContentManagementPath'        => $this->channel_content_management_path,
            'channelSettingsPath'                 => $this->channel_settings_path,
        );
    }

    private static function readString(array $value, string $key): string
    {
        return isset($value[ $key ]) && is_string($value[ $key ]) ? trim($value[ $key ]) : '';
    }
}
