<?php

namespace VeraTag\Features\AdminUiBridge\VisitorDisclosureLanguageSettingsBridge;

use VeraTag\Features\VisitorDisclosureLanguageSettings\VisitorDisclosureLanguageSettingsApiService;
use VeraTag\Settings\OptionValues\ChannelVisitorDisclosureLanguageSettings;
use VeraTag\Settings\Services\GlobalSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class VisitorDisclosureLanguageSettingsBridgeController
{
    public function __construct(
        private readonly VisitorDisclosureLanguageSettingsApiService $api_service,
        private readonly GlobalSettings $settings
    ) {
    }

    public function getSettings(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        return new WP_REST_Response(
            $this->settings->getChannelVisitorDisclosureLanguageSettings()->toArray() + array(
                'hasBeenSynced' => $this->settings->getChannelVisitorDisclosureLanguageSettings()->hasBeenSynced(),
            ),
            200
        );
    }

    /**
     * Writes through to the backend first — only once the backend confirms
     * the new values are persisted there does this plugin store them
     * locally, so the backend always stays the ground truth and the plugin
     * never shows a value it did not actually confirm with the backend.
     */
    public function updateSettings(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();
        if (
            ! is_array($params) || ! isset($params['preferredMode']) || ! is_string($params['preferredMode'])
            || ! isset($params['fallbackLanguageBcp47']) || ! is_string($params['fallbackLanguageBcp47'])
        ) {
            return new WP_Error(
                'veratag_visitor_disclosure_language_settings_invalid_payload',
                'preferredMode and fallbackLanguageBcp47 are required strings.',
                array( 'status' => 400 )
            );
        }

        $result = $this->api_service->update($this->settings->getApiKey(), $params['preferredMode'], $params['fallbackLanguageBcp47']);
        if (is_wp_error($result)) {
            return $result;
        }

        $updated = new ChannelVisitorDisclosureLanguageSettings($result['preferredMode'], $result['fallbackLanguageBcp47']);
        $this->settings->setChannelVisitorDisclosureLanguageSettings($updated);

        return new WP_REST_Response($updated->toArray() + array( 'hasBeenSynced' => $updated->hasBeenSynced() ), 200);
    }
}
