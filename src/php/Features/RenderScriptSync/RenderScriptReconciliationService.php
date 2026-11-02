<?php

namespace VeraTag\Features\RenderScriptSync;

use VeraTag\Features\FrontendServing\DisclosuresRendering\RenderScriptFileWriter;
use VeraTag\Persistence\Disclosures\Repositories\PlacementsRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptFetchStatusesRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptsRepository;
use VeraTag\Settings\OptionValues\SynchronizationStatusSource;
use VeraTag\Settings\Services\GlobalSettings;

/** Canonical per-file render-script reconciliation, shared by pull, push, cron and the admin "check now" action. */
final class RenderScriptReconciliationService
{
    public function __construct(
        private readonly RenderScriptSyncApiService $api_service,
        private readonly PlacementsRepository $placements_repository,
        private readonly RenderScriptsRepository $render_scripts_repository,
        private readonly RenderScriptFetchStatusesRepository $status_repository,
        private readonly GlobalSettings $global_settings,
        private readonly RenderScriptFileWriter $file_writer
    ) {
    }

    public function reconcileAllRequired(): void
    {
        $seen = array();
        foreach ($this->placements_repository->findDistinctRequiredScripts() as $requirement) {
            $file_name = isset($requirement['file_name']) ? trim((string) $requirement['file_name']) : '';
            $version   = isset($requirement['required_version']) ? trim((string) $requirement['required_version']) : '';
            if ('' === $file_name || '' === $version || isset($seen[ $file_name ])) {
                continue;
            }
            $seen[ $file_name ] = true;
            $this->reconcile($file_name, $version);
        }
    }

    public function reconcile(string $file_name, string $required_version): void
    {
        $file_name        = trim($file_name);
        $required_version = trim($required_version);
        if ('' === $file_name || '' === $required_version) {
            return;
        }

        $current         = $this->render_scripts_repository->findByFileName($file_name);
        $current_version = is_array($current) && isset($current['script_version']) ? (string) $current['script_version'] : '';
        $public_url      = $this->file_writer->getPublicUrl($file_name);
        if ($current_version === $required_version && null !== $public_url) {
            $this->record($file_name, $required_version, true, 0, '', SynchronizationStatusSource::LOCAL_PLUGIN, 'Render script is already up to date.', false);
            return;
        }

        $result = $this->api_service->fetchRenderScript($this->global_settings->getApiKey(), $required_version);
        if (! $result->isSuccessful()) {
            $message = $result->getErrorMessage();
            $this->record($file_name, $required_version, false, $result->getStatusCode(), $result->getStatusText(), $result->getStatusSource(), is_string($message) && '' !== trim($message) ? $message : 'Render script sync failed.', true);
            return;
        }

        $script_content = (string) $result->getScriptContent();
        if (! $this->file_writer->write($file_name, $script_content)) {
            $this->record($file_name, $required_version, false, $result->getStatusCode(), $result->getStatusText(), SynchronizationStatusSource::LOCAL_PLUGIN, 'Render script was downloaded but could not be written to the uploads directory.', true);
            return;
        }

        $stored = $this->render_scripts_repository->upsert($file_name, $required_version, $script_content, current_time('mysql', true));
        if (is_wp_error($stored)) {
            $this->record($file_name, $required_version, false, $result->getStatusCode(), $result->getStatusText(), SynchronizationStatusSource::LOCAL_PLUGIN, $stored->get_error_message(), true);
            return;
        }

        $this->record($file_name, $required_version, true, $result->getStatusCode(), $result->getStatusText(), SynchronizationStatusSource::REMOTE_BACKEND_RESPONSE, 'Render script downloaded successfully.', true);
    }

    private function record(string $file_name, string $version, bool $ok, int $http_code, string $http_text, string $source, string $message, bool $http_call_made): void
    {
        $this->status_repository->upsert($file_name, $version, $ok, $http_code, $http_text, $source, $message, current_time('mysql', true), $http_call_made);
    }
}
