<?php

namespace VeraTag\Bootstrap;

use Throwable;
use VeraTag\Features\PluginReset\PluginResetService;
use VeraTag\Logging\Logger;

final class PluginUninstaller
{
    public function __construct(
        private readonly ServiceFactory $service_factory,
        private readonly Logger $logger
    ) {
    }

    public static function run(): void
    {
        $service_factory = new ServiceFactory();
        $plugin_uninstaller = new self($service_factory, $service_factory->createLogger());
        $plugin_uninstaller->uninstall();
    }

    /**
     * Uninstall additionally drops the two schema-version options {@see PluginResetService}
     * deliberately keeps (there is no "next maybeUpgrade() call" once the
     * plugin is actually uninstalled) and does not re-arm any sync
     * schedule, unlike a reset — the plugin is being removed, not kept
     * running.
     */
    public function uninstall(): void
    {
        $plugin_reset_service = new PluginResetService($this->service_factory);
        $this->runCleanupPhase('scheduled hooks', fn () => $this->clearScheduledHooks());
        $this->runCleanupPhase('remote push API deregistration', fn () => $this->deregisterRemotePushApi());
        $this->runCleanupPhase('verification file', fn () => $plugin_reset_service->removeVerificationFile());
        $this->runCleanupPhase('renderer files', fn () => $plugin_reset_service->removeRendererFiles());
        $this->runCleanupPhase('disclosure database tables', fn () => $this->service_factory->createDisclosureSchema()->dropAll());
        $this->runCleanupPhase('push API database tables', fn () => $this->service_factory->createPushApiSchema()->dropAll());
        $this->runCleanupPhase('plugin options', fn () => $plugin_reset_service->deleteAllPluginOptions());
    }

    private function deregisterRemotePushApi(): void
    {
        $api_key = $this->service_factory->createGlobalSettings()->getApiKey();
        if ('' === $api_key) {
            return;
        }
        $result = $this->service_factory->createPushApiConfigurationApiService()->removeForUninstall($api_key);
        if (is_wp_error($result)) {
            throw new \RuntimeException(esc_html($result->get_error_message()));
        }
    }

    private function runCleanupPhase(string $phase_name, callable $cleanup): void
    {
        try {
            $cleanup();
        } catch (Throwable $throwable) {
            $this->logger->error(sprintf('vera[tag] uninstall cleanup failed for %s: %s', $phase_name, $throwable->getMessage()));
        }
    }

    private function clearScheduledHooks(): void
    {
        wp_clear_scheduled_hook($this->service_factory->createPullSynchronizationScheduler()->getCronHook());
        wp_clear_scheduled_hook($this->service_factory->createRenderScriptSyncScheduler()->getCronHook());
    }
}
