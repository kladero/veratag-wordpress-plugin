<?php

namespace VeraTag\Bootstrap;

use VeraTag\Features\AdminUiBridge\ContentOverviewBridge\ContentOverviewBridgeController;
use VeraTag\Features\AdminUiBridge\ContentOverviewBridge\ContentOverviewBridgeRouteRegistrar;
use VeraTag\Features\AdminUiBridge\PullSynchronizationBridge\PullSynchronizationBridgeController;
use VeraTag\Features\AdminUiBridge\PullSynchronizationBridge\PullSynchronizationBridgeRouteRegistrar;
use VeraTag\Features\AdminUiBridge\PluginResetBridge\PluginResetBridgeController;
use VeraTag\Features\AdminUiBridge\PluginResetBridge\PluginResetBridgeRouteRegistrar;
use VeraTag\Features\AdminUiBridge\PushApiConfigurationBridge\PushApiConfigurationBridgeController;
use VeraTag\Features\AdminUiBridge\PushApiConfigurationBridge\PushApiConfigurationBridgeRouteRegistrar;
use VeraTag\Features\AdminUiBridge\RawDisclosuresBridge\RawDisclosuresBridgeController;
use VeraTag\Features\AdminUiBridge\RawDisclosuresBridge\RawDisclosuresBridgeRouteRegistrar;
use VeraTag\Features\AdminUiBridge\RenderScriptSyncBridge\RenderScriptSyncBridgeController;
use VeraTag\Features\AdminUiBridge\RenderScriptSyncBridge\RenderScriptSyncBridgeRouteRegistrar;
use VeraTag\Features\AdminUiBridge\SettingsBridge\SettingsBridgeController;
use VeraTag\Features\AdminUiBridge\SettingsBridge\SettingsBridgeRouteRegistrar;
use VeraTag\Features\CompatibilityCheck\CompatibilityCheckApiService;
use VeraTag\Features\AdminUiBridge\VerificationFileBridge\VerificationFileBridgeController;
use VeraTag\Features\AdminUiBridge\VerificationFileBridge\VerificationFileBridgeRouteRegistrar;
use VeraTag\Features\AdminUiBridge\VisitorDisclosureLanguageSettingsBridge\VisitorDisclosureLanguageSettingsBridgeController;
use VeraTag\Features\AdminUiBridge\VisitorDisclosureLanguageSettingsBridge\VisitorDisclosureLanguageSettingsBridgeRouteRegistrar;
use VeraTag\Features\VisitorDisclosureLanguageSettings\VisitorDisclosureLanguageSettingsApiService;
use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataInjectionService;
use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataLoadingService;
use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataQueryService;
use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresLanguageDeciderService;
use VeraTag\Features\FrontendServing\DisclosuresRendering\DisclosuresRenderScriptEnqueuingService;
use VeraTag\Features\FrontendServing\DisclosuresRendering\RenderScriptFileWriter;
use VeraTag\Features\FrontendServing\FrontendServingService;
use VeraTag\Features\PullSynchronization\PullSynchronizationApiService;
use VeraTag\Features\PullSynchronization\PullSynchronizationPersistenceService;
use VeraTag\Features\PullSynchronization\PullSynchronizationScheduler;
use VeraTag\Features\PushApiConfiguration\PushApiConfigurationApiService;
use VeraTag\Features\PushApiReceiving\PushApiReceivingController;
use VeraTag\Features\PushApiReceiving\PushApiReceivingRouteRegistrar;
use VeraTag\Features\RenderScriptSync\RenderScriptReconciliationService;
use VeraTag\Features\RenderScriptSync\RenderScriptSyncApiService;
use VeraTag\Features\RenderScriptSync\RenderScriptSyncScheduler;
use VeraTag\Features\VerificationFileConfiguration\VerificationFileConfigurationService;
use VeraTag\Persistence\Disclosures\ContentItemIssueAggregationService;
use VeraTag\Persistence\Disclosures\DisclosureSnapshotTransactionService;
use VeraTag\Persistence\Disclosures\DictionaryGarbageCollector;
use VeraTag\Persistence\Disclosures\Repositories\BatchesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ConfigurationsRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemIssuesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentOverviewRepository;
use VeraTag\Persistence\Disclosures\Repositories\PagesRepository;
use VeraTag\Persistence\Disclosures\Repositories\PlacementsRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptFetchStatusesRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptsRepository;
use VeraTag\Persistence\Disclosures\Repositories\TextTemplatesRepository;
use VeraTag\Persistence\Disclosures\Schema\BatchesTable;
use VeraTag\Persistence\Disclosures\Schema\ConfigurationsTable;
use VeraTag\Persistence\Disclosures\Schema\ContentItemIssuesTable;
use VeraTag\Persistence\Disclosures\Schema\ContentItemsTable;
use VeraTag\Persistence\Disclosures\Schema\ContentOverviewTable;
use VeraTag\Persistence\Disclosures\Schema\DisclosureSchema;
use VeraTag\Persistence\Disclosures\Schema\PagesTable;
use VeraTag\Persistence\Disclosures\Schema\PlacementsTable;
use VeraTag\Persistence\Disclosures\Schema\RenderScriptFetchStatusesTable;
use VeraTag\Persistence\Disclosures\Schema\RenderScriptsTable;
use VeraTag\Persistence\Disclosures\Schema\TextTemplatesTable;
use VeraTag\Persistence\PushApi\Repositories\PushReceivedLogRepository;
use VeraTag\Persistence\PushApi\Schema\PushApiSchema;
use VeraTag\Persistence\PushApi\Schema\PushReceivedLogTable;
use VeraTag\Persistence\PushApi\PushInstructionTransactionService;
use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpClient;
use VeraTag\VeraTagBackend\HttpConfig;
use VeraTag\VeraTagBackend\HttpEndpoints;

final class ServiceFactory
{
    public function createAdminShellBootstrap(): AdminShellBootstrap
    {
        return new AdminShellBootstrap();
    }
    public function createAdminUiBootstrap(): AdminUiBootstrap
    {
        return new AdminUiBootstrap();
    }
    public function createGlobalSettings(): GlobalSettings
    {
        return new GlobalSettings(new HttpConfig());
    }
    public function createVerificationFileConfigurationService(): VerificationFileConfigurationService
    {
        return new VerificationFileConfigurationService();
    }

    public function createDisclosureSchema(): DisclosureSchema
    {
        return new DisclosureSchema(new BatchesTable(), new ContentOverviewTable(), new PagesTable(), new ContentItemsTable(), new ContentItemIssuesTable(), new ConfigurationsTable(), new PlacementsTable(), new TextTemplatesTable(), new RenderScriptsTable(), new RenderScriptFetchStatusesTable());
    }
    public function createPushApiSchema(): PushApiSchema
    {
        return new PushApiSchema(new PushReceivedLogTable());
    }

    private function batches(): BatchesRepository
    {
        return new BatchesRepository(new BatchesTable());
    }
    private function pages(): PagesRepository
    {
        return new PagesRepository(new PagesTable());
    }
    private function contentItems(): ContentItemsRepository
    {
        return new ContentItemsRepository(new ContentItemsTable());
    }
    private function contentIssues(): ContentItemIssuesRepository
    {
        return new ContentItemIssuesRepository(new ContentItemIssuesTable());
    }
    private function contentOverview(): ContentOverviewRepository
    {
        return new ContentOverviewRepository(new ContentOverviewTable());
    }
    private function configurations(): ConfigurationsRepository
    {
        return new ConfigurationsRepository(new ConfigurationsTable());
    }
    private function placements(): PlacementsRepository
    {
        return new PlacementsRepository(new PlacementsTable());
    }
    private function textTemplates(): TextTemplatesRepository
    {
        return new TextTemplatesRepository(new TextTemplatesTable());
    }
    public function createRenderScriptsRepository(): RenderScriptsRepository
    {
        return new RenderScriptsRepository(new RenderScriptsTable());
    }
    private function renderStatuses(): RenderScriptFetchStatusesRepository
    {
        return new RenderScriptFetchStatusesRepository(new RenderScriptFetchStatusesTable());
    }
    private function pushLog(): PushReceivedLogRepository
    {
        return new PushReceivedLogRepository(new PushReceivedLogTable());
    }
    private function pushInstructionTransaction(): PushInstructionTransactionService
    {
        return new PushInstructionTransactionService();
    }
    public function createRenderScriptFileWriter(): RenderScriptFileWriter
    {
        return new RenderScriptFileWriter();
    }
    private function fileWriter(): RenderScriptFileWriter
    {
        return $this->createRenderScriptFileWriter();
    }

    private function createDictionaryGarbageCollector(): DictionaryGarbageCollector
    {
        return new DictionaryGarbageCollector($this->configurations(), $this->placements(), $this->textTemplates(), $this->createRenderScriptsRepository(), $this->renderStatuses(), $this->contentItems(), $this->fileWriter());
    }
    private function createContentItemIssueAggregationService(): ContentItemIssueAggregationService
    {
        return new ContentItemIssueAggregationService($this->contentItems(), $this->contentIssues(), $this->configurations(), $this->placements(), $this->textTemplates());
    }
    public function createRenderScriptReconciliationService(): RenderScriptReconciliationService
    {
        $config   = new HttpConfig();
        $settings = $this->createGlobalSettings();
        return new RenderScriptReconciliationService(
            new RenderScriptSyncApiService(new HttpClient(), new HttpEndpoints($config), $settings),
            $this->placements(),
            $this->createRenderScriptsRepository(),
            $this->renderStatuses(),
            $settings,
            $this->fileWriter()
        );
    }
    public function createRenderScriptSyncScheduler(): RenderScriptSyncScheduler
    {
        return new RenderScriptSyncScheduler($this->createRenderScriptReconciliationService());
    }

    public function createPullSynchronizationPersistenceService(): PullSynchronizationPersistenceService
    {
        return new PullSynchronizationPersistenceService(
            $this->batches(),
            $this->contentOverview(),
            $this->pages(),
            $this->contentItems(),
            $this->contentIssues(),
            $this->configurations(),
            $this->placements(),
            $this->textTemplates(),
            $this->createDictionaryGarbageCollector(),
            $this->createRenderScriptReconciliationService(),
            $this->createContentItemIssueAggregationService(),
            new DisclosureSnapshotTransactionService()
        );
    }
    public function createPullSynchronizationScheduler(): PullSynchronizationScheduler
    {
        $config   = new HttpConfig();
        $settings = $this->createGlobalSettings();
        return new PullSynchronizationScheduler(
            new PullSynchronizationApiService(new HttpClient(), new HttpEndpoints($config), $settings),
            $this->createPullSynchronizationPersistenceService(),
            $settings
        );
    }

    public function createFrontendServingService(): FrontendServingService
    {
        return new FrontendServingService(
            new DisclosuresDataLoadingService(
                new DisclosuresDataQueryService($this->batches(), $this->pages(), $this->contentItems(), $this->configurations(), $this->placements(), $this->textTemplates()),
                new DisclosuresLanguageDeciderService(),
                $this->createGlobalSettings()
            ),
            new DisclosuresRenderScriptEnqueuingService($this->createRenderScriptsRepository(), $this->fileWriter()),
            new DisclosuresDataInjectionService()
        );
    }

    public function createSettingsBridgeRouteRegistrar(): SettingsBridgeRouteRegistrar
    {
        $config                      = new HttpConfig();
        $settings                    = $this->createGlobalSettings();
        $compatibility_check_service = new CompatibilityCheckApiService(new HttpClient(), new HttpEndpoints($config), $settings);
        return new SettingsBridgeRouteRegistrar(new SettingsBridgeController($settings, $this->createPullSynchronizationScheduler(), $compatibility_check_service));
    }
    public function createVerificationFileBridgeRouteRegistrar(): VerificationFileBridgeRouteRegistrar
    {
        return new VerificationFileBridgeRouteRegistrar(new VerificationFileBridgeController($this->createVerificationFileConfigurationService()));
    }
    public function createPullSynchronizationBridgeRouteRegistrar(): PullSynchronizationBridgeRouteRegistrar
    {
        return new PullSynchronizationBridgeRouteRegistrar(new PullSynchronizationBridgeController($this->createGlobalSettings(), $this->createPullSynchronizationScheduler(), $this->contentItems(), $this->batches()));
    }
    public function createRenderScriptSyncBridgeRouteRegistrar(): RenderScriptSyncBridgeRouteRegistrar
    {
        return new RenderScriptSyncBridgeRouteRegistrar(new RenderScriptSyncBridgeController($this->createRenderScriptSyncScheduler(), $this->placements(), $this->createRenderScriptsRepository(), $this->renderStatuses()));
    }
    public function createRawDisclosuresBridgeRouteRegistrar(): RawDisclosuresBridgeRouteRegistrar
    {
        return new RawDisclosuresBridgeRouteRegistrar(new RawDisclosuresBridgeController($this->configurations(), $this->placements(), $this->textTemplates(), $this->pages(), $this->contentItems(), $this->contentIssues(), $this->createGlobalSettings()));
    }
    public function createContentOverviewBridgeRouteRegistrar(): ContentOverviewBridgeRouteRegistrar
    {
        return new ContentOverviewBridgeRouteRegistrar(new ContentOverviewBridgeController($this->contentItems(), $this->contentIssues(), $this->configurations()));
    }

    public function createPushApiReceivingRouteRegistrar(): PushApiReceivingRouteRegistrar
    {
        $settings   = $this->createGlobalSettings();
        $controller = new PushApiReceivingController(
            $this->batches(),
            $this->pages(),
            $this->contentItems(),
            $this->contentIssues(),
            $this->configurations(),
            $this->placements(),
            $this->textTemplates(),
            $this->createRenderScriptsRepository(),
            $this->renderStatuses(),
            $this->createDictionaryGarbageCollector(),
            $this->createRenderScriptReconciliationService(),
            $this->fileWriter(),
            $this->pushLog(),
            $settings,
            $this->createContentItemIssueAggregationService(),
            $this->pushInstructionTransaction()
        );
        return new PushApiReceivingRouteRegistrar($controller, $settings);
    }
    public function createPushApiConfigurationBridgeRouteRegistrar(): PushApiConfigurationBridgeRouteRegistrar
    {
        $settings = $this->createGlobalSettings();
        return new PushApiConfigurationBridgeRouteRegistrar(new PushApiConfigurationBridgeController($this->createPushApiConfigurationApiService($settings), $this->pushLog(), $settings));
    }
    public function createPushApiConfigurationApiService(?GlobalSettings $settings = null): PushApiConfigurationApiService
    {
        return new PushApiConfigurationApiService(new HttpClient(), new HttpEndpoints(new HttpConfig()), $settings ?? $this->createGlobalSettings());
    }
    public function createVisitorDisclosureLanguageSettingsBridgeRouteRegistrar(): VisitorDisclosureLanguageSettingsBridgeRouteRegistrar
    {
        $config   = new HttpConfig();
        $settings = $this->createGlobalSettings();
        $service  = new VisitorDisclosureLanguageSettingsApiService(new HttpClient(), new HttpEndpoints($config), $settings);
        return new VisitorDisclosureLanguageSettingsBridgeRouteRegistrar(new VisitorDisclosureLanguageSettingsBridgeController($service, $settings));
    }
    public function createPluginResetBridgeRouteRegistrar(): PluginResetBridgeRouteRegistrar
    {
        $config   = new HttpConfig();
        $settings = $this->createGlobalSettings();
        $service  = new PushApiConfigurationApiService(new HttpClient(), new HttpEndpoints($config), $settings);
        return new PluginResetBridgeRouteRegistrar(new PluginResetBridgeController($service, $settings));
    }
}
