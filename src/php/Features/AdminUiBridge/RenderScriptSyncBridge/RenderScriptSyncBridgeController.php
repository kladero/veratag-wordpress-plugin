<?php

namespace VeraTag\Features\AdminUiBridge\RenderScriptSyncBridge;

use VeraTag\Features\RenderScriptSync\RenderScriptSyncScheduler;
use VeraTag\Persistence\Disclosures\Repositories\PlacementsRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptFetchStatusesRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptsRepository;
use WP_REST_Request;
use WP_REST_Response;

final class RenderScriptSyncBridgeController
{
    public function __construct(
        private readonly RenderScriptSyncScheduler $scheduler,
        private readonly PlacementsRepository $placements_repository,
        private readonly RenderScriptsRepository $render_scripts_repository,
        private readonly RenderScriptFetchStatusesRepository $status_repository
    ) {
    }
    public function getStatus(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        return new WP_REST_Response($this->buildPayload(), 200);
    }
    public function runSync(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        $this->scheduler->run();
        return new WP_REST_Response($this->buildPayload(), 200);
    }
    private function buildPayload(): array
    {
        $statuses = array();
        $seen     = array();
        $all_ok   = true;
        foreach ($this->placements_repository->findDistinctRequiredScripts() as $required) {
            $file    = trim((string) ( $required['file_name'] ?? '' ));
            $version = trim((string) ( $required['required_version'] ?? '' ));
            if ('' === $file || '' === $version || isset($seen[ $file ])) {
                continue;
            } $seen[ $file ] = true;
            $cached          = $this->render_scripts_repository->findByFileName($file);
            $fetch           = $this->status_repository->findByFileName($file);
            $cached_version  = is_array($cached) && is_string($cached['script_version'] ?? null) ? (string) $cached['script_version'] : null;
            $ok              = $cached_version === $version && ( ! is_array($fetch) || '1' === (string) ( $fetch['ok'] ?? '0' ) );
            $all_ok          = $all_ok && $ok;
            $statuses[]      = array(
                'fileName'              => $file,
                'requiredVersion'       => $version,
                'cachedVersion'         => $cached_version,
                'lastFetchOk'           => $ok,
                'lastFetchErrorMessage' => is_array($fetch) && '1' !== (string) ( $fetch['ok'] ?? '0' ) ? (string) ( $fetch['message'] ?? '' ) : null,
                'lastFetchAt'           => is_array($fetch) && is_string($fetch['last_fetch_at'] ?? null) ? (string) $fetch['last_fetch_at'] : null,
            );
        }
        return array(
            'ok'       => $all_ok,
            'message'  => array() === $statuses ? 'No render scripts are currently required.' : ( $all_ok ? 'All required render scripts are cached.' : 'One or more required render scripts are missing or stale.' ),
            'statuses' => $statuses,
        );
    }
}
