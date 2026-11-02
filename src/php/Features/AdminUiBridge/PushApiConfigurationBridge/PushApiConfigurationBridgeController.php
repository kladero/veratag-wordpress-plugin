<?php

namespace VeraTag\Features\AdminUiBridge\PushApiConfigurationBridge;

use VeraTag\Features\PushApiConfiguration\PushApiConfigurationApiService;
use VeraTag\Persistence\PushApi\Repositories\PushReceivedLogRepository;
use VeraTag\Settings\OptionValues\PushApiSettings;
use VeraTag\Settings\Services\GlobalSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PushApiConfigurationBridgeController
{
    public function __construct(private readonly PushApiConfigurationApiService $api_service, private readonly PushReceivedLogRepository $log_repository, private readonly GlobalSettings $settings)
    {
    }
    public function getStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        $api_key = trim($this->settings->getApiKey());
        if ('' === $api_key) {
            return $this->respond(PushApiConfigurationApiService::emptyStatus());
        }
        return $this->respond($this->api_service->getStatus($api_key));
    }
    public function configure(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        $secret = $this->settings->generatePushApiSecret();
        $result = $this->api_service->configure($this->settings->getApiKey(), rest_url('veratag/v1/push-api/receive'), $secret);
        // Only persist the new secret once the backend has actually
        // confirmed it accepted it — a failed call must leave this site's
        // stored secret untouched, since the backend still expects the old
        // one in that case. See GlobalSettings::generatePushApiSecret.
        if (! is_wp_error($result)) {
            $this->settings->storePushApiSecret($secret);
            $this->settings->setPushApiEnabled((bool) $result['enabled']);
        }
        return $this->respond($result);
    }
    public function setEnabled(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();
        if (! is_array($params) || ! array_key_exists('enabled', $params) || ! is_bool($params['enabled'])) {
            return new WP_Error('veratag_push_api_invalid_enabled', 'enabled must be a boolean.', array( 'status' => 400 ));
        }
        $result = $this->api_service->setEnabled($this->settings->getApiKey(), $params['enabled']);
        if (! is_wp_error($result)) {
            $this->settings->setPushApiEnabled((bool) $result['enabled']);
        }
        return $this->respond($result);
    }
    public function remove(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        $result = $this->api_service->remove($this->settings->getApiKey());
        if (! is_wp_error($result)) {
            $last = $this->settings->getPushApiSettings()->getLastReceivedAt();
            $this->settings->setPushApiSettings(new PushApiSettings(false, '', $last));
        }
        return $this->respond($result);
    }
    private const DEFAULT_RECENT_PAGE_SIZE = 5;
    private const MAX_RECENT_PAGE_SIZE     = 200;

    public function recent(WP_REST_Request $request): WP_REST_Response
    {
        $page_size = (int) ( $request->get_param('pageSize') ?? self::DEFAULT_RECENT_PAGE_SIZE );
        if ($page_size < 1) {
            $page_size = self::DEFAULT_RECENT_PAGE_SIZE;
        }
        $page_size   = min($page_size, self::MAX_RECENT_PAGE_SIZE);
        $page_number = max(1, (int) ( $request->get_param('pageNumber') ?? 1 ));
        $offset      = ( $page_number - 1 ) * $page_size;

        $total_elements = $this->log_repository->countAll();
        $rows           = $this->log_repository->findPage($page_size, $offset);
        $content        = array_map(
            static fn (array $row): array => array(
                'id'                  => (int) $row['id'],
                'receivedAt'          => (string) $row['received_at'],
                'instruction'         => (string) $row['instruction'],
                'ref'                 => isset($row['ref_value']) && null !== $row['ref_value'] ? (string) $row['ref_value'] : null,
                'appliedSuccessfully' => '1' === (string) $row['applied_successfully'],
                'errorMessage'        => isset($row['error_message']) && null !== $row['error_message'] ? (string) $row['error_message'] : null,
            ),
            $rows
        );
        $total_pages    = 0 === $page_size ? 0 : (int) ceil($total_elements / $page_size);

        return new WP_REST_Response(
            array(
                'content'          => $content,
                'totalPages'       => $total_pages,
                'totalElements'    => $total_elements,
                'pageNumber'       => $page_number - 1,
                'pageSize'         => $page_size,
                'first'            => 1 === $page_number,
                'last'             => $page_number >= $total_pages,
                'numberOfElements' => count($content),
                'empty'            => array() === $content,
            ),
            200
        );
    }
    /**
     * Always folds `receivingUrl` into a successful status response — the
     * real, fixed WordPress REST route the backend must POST pushes to
     * ({@see PushApiReceivingRouteRegistrar}), regardless of whether push is
     * currently configured. This lets the Settings page show the admin
     * exactly what URL will be registered before they ever click "Configure
     * push API", so there is no surprise about what gets sent to the backend.
     */
    private function respond(array|WP_Error $result): WP_REST_Response|WP_Error
    {
        if (is_wp_error($result)) {
            return $result;
        }
        $result['receivingUrl'] = rest_url('veratag/v1/push-api/receive');
        return new WP_REST_Response($result, 200);
    }
}
