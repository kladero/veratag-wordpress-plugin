<?php

namespace VeraTag\Features\AdminUiBridge\SettingsBridge;

use VeraTag\Features\CompatibilityCheck\CompatibilityCheckApiService;
use VeraTag\Features\PullSynchronization\PullSynchronizationScheduler;
use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpConfig;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class SettingsBridgeController
{
    public function __construct(
        private readonly GlobalSettings $global_settings,
        private readonly PullSynchronizationScheduler $pull_synchronization_scheduler,
        private readonly CompatibilityCheckApiService $compatibility_check_service
    ) {
    }

    public function getSettings(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        $response = new SettingsBridgeResponse(
            $this->global_settings->getApiKey(),
            $this->channelVisitorDisclosureLanguageSettingsPayload(),
            $this->global_settings->getLastPullSynchronizationStatus()->toArray(),
            $this->global_settings->getChannelDisplayName(),
            $this->global_settings->getChannelDashboardUrl(),
            $this->global_settings->hasConnectedSuccessfullyAtLeastOnce(),
            $this->createHttpConfig()->getDashboardBaseUrl(),
            $this->compatibility_check_service->check()->toArray()
        );

        return new WP_REST_Response($response->toArray(), 200);
    }

    public function updateSettings(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();

        if (! is_array($params)) {
            return new WP_Error(
                'veratag_admin_ui_bridge_invalid_settings_payload',
                'Settings payload must be a JSON object.',
                array( 'status' => 400 )
            );
        }

        if (array_key_exists('apiKey', $params)) {
            if (! is_string($params['apiKey'])) {
                return new WP_Error(
                    'veratag_admin_ui_bridge_invalid_api_key',
                    'apiKey must be a string.',
                    array( 'status' => 400 )
                );
            }

            $previous_api_key = $this->global_settings->getApiKey();
            $this->global_settings->setApiKey($params['apiKey']);
            $updated_api_key = $this->global_settings->getApiKey();

            if ($previous_api_key !== $updated_api_key) {
                $this->pull_synchronization_scheduler->resetLatestAttemptStatus();
                $this->pull_synchronization_scheduler->run();
            }
        }

        $response = new SettingsBridgeResponse(
            $this->global_settings->getApiKey(),
            $this->channelVisitorDisclosureLanguageSettingsPayload(),
            $this->global_settings->getLastPullSynchronizationStatus()->toArray(),
            $this->global_settings->getChannelDisplayName(),
            $this->global_settings->getChannelDashboardUrl(),
            $this->global_settings->hasConnectedSuccessfullyAtLeastOnce(),
            $this->createHttpConfig()->getDashboardBaseUrl(),
            $this->compatibility_check_service->check()->toArray()
        );

        return new WP_REST_Response($response->toArray(), 200);
    }

    private function channelVisitorDisclosureLanguageSettingsPayload(): array
    {
        $settings = $this->global_settings->getChannelVisitorDisclosureLanguageSettings();
        return $settings->toArray() + array( 'hasBeenSynced' => $settings->hasBeenSynced() );
    }

    private function createHttpConfig(): HttpConfig
    {
        return new HttpConfig();
    }

}
