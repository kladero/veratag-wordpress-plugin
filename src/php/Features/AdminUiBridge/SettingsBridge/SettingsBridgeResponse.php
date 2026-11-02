<?php

namespace VeraTag\Features\AdminUiBridge\SettingsBridge;

final class SettingsBridgeResponse
{
    public function __construct(
        private readonly string $api_key,
        private readonly array $channel_visitor_disclosure_language_settings,
        private readonly array $last_pull_synchronization_status,
        private readonly string $channel_display_name,
        private readonly string $channel_dashboard_url,
        private readonly bool $has_connected_successfully_at_least_once,
        private readonly string $dashboard_base_url,
        private readonly array $compatibility
    ) {
    }

    public function toArray(): array
    {
        return array(
            'apiKey'                                   => $this->api_key,
            'channelVisitorDisclosureLanguageSettings' => $this->channel_visitor_disclosure_language_settings,
            'lastPullSynchronizationStatus'            => $this->last_pull_synchronization_status,
            'channelDisplayName'                       => $this->channel_display_name,
            'channelDashboardUrl'                      => $this->channel_dashboard_url,
            'hasConnectedSuccessfullyAtLeastOnce'      => $this->has_connected_successfully_at_least_once,
            'veratagDashboardBaseUrl'                  => $this->dashboard_base_url,
            'compatibility'                            => $this->compatibility,
        );
    }
}
