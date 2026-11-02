<?php

namespace VeraTag\Bootstrap;

use VeraTag\Features\FrontendServing\FrontendServingService;
use VeraTag\Persistence\Disclosures\Schema\DisclosureSchema;
use VeraTag\Persistence\PushApi\Schema\PushApiSchema;

final class Plugin
{
    /** Shared by both frontend hooks so the visited page is resolved once per request. */
    private ?FrontendServingService $frontend_serving_service = null;

    public function __construct(
        private readonly ServiceFactory $service_factory = new ServiceFactory()
    ) {
    }

    public function register(): void
    {
        add_filter('cron_schedules', array( $this, 'registerPullSynchronizationCronSchedule' ));
        add_filter('cron_schedules', array( $this, 'registerRenderScriptSyncCronSchedule' ));
        add_action('plugins_loaded', array( $this, 'maybeUpgradeDisclosureSchema' ));
        add_action('plugins_loaded', array( $this, 'maybeUpgradePushApiSchema' ));
        add_action('plugins_loaded', array( $this, 'ensurePullSynchronizationSchedule' ));
        add_action('plugins_loaded', array( $this, 'ensureRenderScriptSyncSchedule' ));
        add_action('rest_api_init', array( $this, 'registerAdminUiBridgeRoutes' ));
        add_action('admin_menu', array( $this, 'bootstrapAdminMenu' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueueAdminAssets' ));
        add_action('admin_head', array( $this, 'hideCoreUpdateNag' ));
        add_action('admin_head', array( $this, 'bootstrapAdminMenuIconStyles' ));
        add_action('admin_head', array( $this, 'bootstrapAdminCanvasStyles' ));
        add_action($this->getPullSynchronizationScheduler()->getCronHook(), array( $this, 'runPullSynchronization' ));
        add_action($this->getRenderScriptSyncScheduler()->getCronHook(), array( $this, 'runRenderScriptSync' ));
        add_action('wp_enqueue_scripts', array( $this, 'enqueueFrontendAssets' ));
        add_action('wp_head', array( $this, 'injectFrontendTaggerData' ));
    }

    public function onActivate(): void
    {
        add_filter('cron_schedules', array( $this, 'registerPullSynchronizationCronSchedule' ));
        add_filter('cron_schedules', array( $this, 'registerRenderScriptSyncCronSchedule' ));
        $this->getDisclosureSchema()->maybeUpgrade();
        $this->getPushApiSchema()->maybeUpgrade();
        $this->getPullSynchronizationScheduler()->ensureSchedule();
        $this->getRenderScriptSyncScheduler()->ensureSchedule();
    }

    public function onDeactivate(): void
    {
        $this->getPullSynchronizationScheduler()->clearSchedule();
        $this->getRenderScriptSyncScheduler()->clearSchedule();
    }

    public function maybeUpgradeDisclosureSchema(): void
    {
        $this->getDisclosureSchema()->maybeUpgrade();
    }

    public function maybeUpgradePushApiSchema(): void
    {
        $this->getPushApiSchema()->maybeUpgrade();
    }

    public function ensurePullSynchronizationSchedule(): void
    {
        $this->getPullSynchronizationScheduler()->ensureSchedule();
    }

    public function ensureRenderScriptSyncSchedule(): void
    {
        $this->getRenderScriptSyncScheduler()->ensureSchedule();
    }

    public function registerPullSynchronizationCronSchedule(array $schedules): array
    {
        return $this->getPullSynchronizationScheduler()->extendCronSchedules($schedules);
    }

    public function registerRenderScriptSyncCronSchedule(array $schedules): array
    {
        return $this->getRenderScriptSyncScheduler()->extendCronSchedules($schedules);
    }

    public function runPullSynchronization(): void
    {
        $this->getPullSynchronizationScheduler()->run();
    }

    public function runRenderScriptSync(): void
    {
        $this->getRenderScriptSyncScheduler()->run();
    }

    public function registerAdminUiBridgeRoutes(): void
    {
        $this->getSettingsBridgeRouteRegistrar()->registerRoutes();
        $this->getPullSynchronizationBridgeRouteRegistrar()->registerRoutes();
        $this->getVerificationFileBridgeRouteRegistrar()->registerRoutes();
        $this->getRawDisclosuresBridgeRouteRegistrar()->registerRoutes();
        $this->getContentOverviewBridgeRouteRegistrar()->registerRoutes();
        $this->getRenderScriptSyncBridgeRouteRegistrar()->registerRoutes();
        $this->getPushApiConfigurationBridgeRouteRegistrar()->registerRoutes();
        $this->getPushApiReceivingRouteRegistrar()->registerRoutes();
        $this->getVisitorDisclosureLanguageSettingsBridgeRouteRegistrar()->registerRoutes();
        $this->getPluginResetBridgeRouteRegistrar()->registerRoutes();
    }

    public function enqueueFrontendAssets(): void
    {
        $this->getFrontendServingService()->enqueueApiScript();
    }

    public function injectFrontendTaggerData(): void
    {
        $this->getFrontendServingService()->injectTaggerData();
    }

    public function bootstrapAdminMenu(): void
    {
        $this->service_factory->createAdminShellBootstrap()->registerMenu();
    }

    public function enqueueAdminAssets(string $hookSuffix): void
    {
        $current_page = $this->getAdminShellBootstrap()->getCurrentAdminPage();
        $this->service_factory->createAdminUiBootstrap()->enqueueAssets(
            $current_page,
            $this->getAdminShellBootstrap()->resolveCurrentPageKey($current_page),
            $this->getAdminShellBootstrap()->getAdminWordmarkUrl(),
            $this->service_factory->createGlobalSettings()->getChannelDashboardUrl()
        );
    }

    public function hideCoreUpdateNag(): void
    {
        $this->getAdminShellBootstrap()->hideCoreUpdateNag(
            $this->getAdminShellBootstrap()->getCurrentAdminPage()
        );
    }

    public function bootstrapAdminMenuIconStyles(): void
    {
        $this->getAdminShellBootstrap()->printMenuIconStyles();
    }

    public function bootstrapAdminCanvasStyles(): void
    {
        $this->getAdminShellBootstrap()->printCanvasStyles(
            $this->getAdminShellBootstrap()->getCurrentAdminPage()
        );
    }

    private function getDisclosureSchema(): DisclosureSchema
    {
        return $this->service_factory->createDisclosureSchema();
    }

    private function getPushApiSchema(): PushApiSchema
    {
        return $this->service_factory->createPushApiSchema();
    }

    private function getPullSynchronizationScheduler()
    {
        return $this->service_factory->createPullSynchronizationScheduler();
    }

    private function getRenderScriptSyncScheduler()
    {
        return $this->service_factory->createRenderScriptSyncScheduler();
    }

    private function getSettingsBridgeRouteRegistrar()
    {
        return $this->service_factory->createSettingsBridgeRouteRegistrar();
    }

    private function getPullSynchronizationBridgeRouteRegistrar()
    {
        return $this->service_factory->createPullSynchronizationBridgeRouteRegistrar();
    }

    private function getVerificationFileBridgeRouteRegistrar()
    {
        return $this->service_factory->createVerificationFileBridgeRouteRegistrar();
    }

    private function getRawDisclosuresBridgeRouteRegistrar()
    {
        return $this->service_factory->createRawDisclosuresBridgeRouteRegistrar();
    }

    private function getRenderScriptSyncBridgeRouteRegistrar()
    {
        return $this->service_factory->createRenderScriptSyncBridgeRouteRegistrar();
    }

    private function getPushApiConfigurationBridgeRouteRegistrar()
    {
        return $this->service_factory->createPushApiConfigurationBridgeRouteRegistrar();
    }

    private function getPushApiReceivingRouteRegistrar()
    {
        return $this->service_factory->createPushApiReceivingRouteRegistrar();
    }

    private function getContentOverviewBridgeRouteRegistrar()
    {
        return $this->service_factory->createContentOverviewBridgeRouteRegistrar();
    }

    private function getVisitorDisclosureLanguageSettingsBridgeRouteRegistrar()
    {
        return $this->service_factory->createVisitorDisclosureLanguageSettingsBridgeRouteRegistrar();
    }

    private function getPluginResetBridgeRouteRegistrar()
    {
        return $this->service_factory->createPluginResetBridgeRouteRegistrar();
    }

    private function getAdminShellBootstrap(): AdminShellBootstrap
    {
        return $this->service_factory->createAdminShellBootstrap();
    }

    private function getFrontendServingService(): FrontendServingService
    {
        return $this->frontend_serving_service ??= $this->service_factory->createFrontendServingService();
    }
}
