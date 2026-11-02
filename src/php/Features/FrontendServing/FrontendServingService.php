<?php

namespace VeraTag\Features\FrontendServing;

use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataInjectionService;
use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataLoadingService;
use VeraTag\Features\FrontendServing\DisclosuresRendering\DisclosuresRenderScriptEnqueuingService;

final class FrontendServingService
{
    public function __construct(
        private readonly DisclosuresDataLoadingService $disclosures_data_loading_service,
        private readonly DisclosuresRenderScriptEnqueuingService $disclosures_render_script_enqueuing_service,
        private readonly DisclosuresDataInjectionService $disclosures_data_injection_service
    ) {
    }

    /** @var array{payload: array, render_scripts: list<array{file_name: string, required_version: string}>}|null */
    private ?array $current_page = null;

    public function enqueueApiScript(): void
    {
        $this->disclosures_render_script_enqueuing_service->enqueueRequiredScripts($this->loadCurrentPage()['render_scripts']);
    }

    public function injectTaggerData(): void
    {
        $this->disclosures_data_injection_service->injectDisclosureDataScript($this->loadCurrentPage()['payload']);
    }

    /** Both frontend hooks serve the same request, so the page is resolved once and shared. */
    private function loadCurrentPage(): array
    {
        return $this->current_page ??= $this->disclosures_data_loading_service->loadCurrentPage();
    }
}
