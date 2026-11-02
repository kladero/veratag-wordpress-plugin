<?php

namespace VeraTag\Features\AdminUiBridge\VerificationFileBridge;

use VeraTag\Features\VerificationFileConfiguration\VerificationFileConfigurationService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class VerificationFileBridgeController
{
    public function __construct(
        private readonly VerificationFileConfigurationService $verification_file_configuration_service
    ) {
    }

    public function getVerificationFile(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        $response = new VerificationFileBridgeResponse(
            $this->verification_file_configuration_service->getVerificationFileState()
        );

        return new WP_REST_Response($response->toArray(), 200);
    }

    public function configureVerificationFile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();

        if (! is_array($params)) {
            return new WP_Error(
                'veratag_admin_ui_bridge_invalid_verification_file_payload',
                'Verification file payload must be a JSON object.',
                array( 'status' => 400 )
            );
        }

        if (! array_key_exists('verificationFileToken', $params) || ! is_string($params['verificationFileToken'])) {
            return new WP_Error(
                'veratag_admin_ui_bridge_invalid_verification_file_token',
                'verificationFileToken must be a string.',
                array( 'status' => 400 )
            );
        }

        $configure_verification_file_result = $this->verification_file_configuration_service->configureVerificationFile(
            $params['verificationFileToken']
        );

        if (is_wp_error($configure_verification_file_result)) {
            return $configure_verification_file_result;
        }

        $response = new VerificationFileBridgeResponse(
            $this->verification_file_configuration_service->getVerificationFileState()
        );

        return new WP_REST_Response($response->toArray(), 200);
    }

    public function removeVerificationFile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);

        $remove_verification_file_result = $this->verification_file_configuration_service->removeConfiguredVerificationFile();

        if (is_wp_error($remove_verification_file_result)) {
            return $remove_verification_file_result;
        }

        $response = new VerificationFileBridgeResponse(
            $this->verification_file_configuration_service->getVerificationFileState()
        );

        return new WP_REST_Response($response->toArray(), 200);
    }
}
