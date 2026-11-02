<?php

namespace VeraTag\Features\PluginReset;

use VeraTag\Bootstrap\ServiceFactory;
use VeraTag\Settings\OptionKeys\SettingKey;

/**
 * Resets this WordPress site's local plugin state back to exactly how it
 * was on a fresh install — every stored setting, every locally cached
 * disclosure/content/configuration/placement/text-template/render-script,
 * and the received-push log are wiped, and the sync cron schedules are
 * cleared and re-armed from "now" (not left stale/unscheduled). The
 * verification file is also removed, since it is meaningless without a
 * channel to verify.
 *
 * Deliberately does NOT deactivate/uninstall the plugin itself — unlike
 * {@see \VeraTag\Bootstrap\PluginUninstaller}, which this class otherwise
 * mirrors closely, the plugin keeps running afterward so the site admin
 * can immediately re-enter a new API key and connect to a different
 * channel without having to deactivate/reactivate anything.
 *
 * Used for both:
 *  - an authenticated CHANNEL_DELETE push from the backend (the channel
 *    this site was connected to no longer exists there at all), and
 *  - the admin UI's own "Unregister from Channel" action, after that
 *    action's own outbound push-API removal call to the backend succeeds.
 */
final class PluginResetService
{
    public function __construct(
        private readonly ServiceFactory $service_factory = new ServiceFactory()
    ) {
    }

    public function reset(): void
    {
        $this->clearScheduledHooks();
        $this->removePluginArtifacts();
        $this->resetDisclosureSchema();
        $this->resetPushApiSchema();
        $this->deleteOptions();
        $this->rescheduleSyncs();
    }

    /** Removes generated files without modifying database state or schedules. */
    public function removePluginArtifacts(): void
    {
        $this->removeVerificationFile();
        $this->removeRendererFiles();
    }

    /** Removes only the plugin's well-known verification file. */
    public function removeVerificationFile(): void
    {
        $verification_file_result = $this->service_factory->createVerificationFileConfigurationService()->removeConfiguredVerificationFile();
        if (is_wp_error($verification_file_result)) {
            throw new \RuntimeException(esc_html($verification_file_result->get_error_message()));
        }
    }

    /** Removes only renderer assets stored in the plugin-owned uploads directory. */
    public function removeRendererFiles(): void
    {
        if (! $this->service_factory->createRenderScriptFileWriter()->deleteAll()) {
            throw new \RuntimeException('Unable to delete generated renderer files.');
        }
    }

    /** Removes all plugin options, including schema versions for permanent uninstallation. */
    public function deleteAllPluginOptions(): void
    {
        $this->deleteOptions();
        delete_option(SettingKey::VERATAG_DISCLOSURE_PERSISTENCE_SCHEMA_VERSION);
        delete_option(SettingKey::VERATAG_PUSH_API_SCHEMA_VERSION);
    }

    private function clearScheduledHooks(): void
    {
        wp_clear_scheduled_hook($this->service_factory->createPullSynchronizationScheduler()->getCronHook());
        wp_clear_scheduled_hook($this->service_factory->createRenderScriptSyncScheduler()->getCronHook());
    }

    /** Drops and immediately recreates every disclosure table — same operation DisclosureSchema::maybeUpgrade() already performs routinely, just forced rather than version-gated. */
    private function resetDisclosureSchema(): void
    {
        $schema = $this->service_factory->createDisclosureSchema();
        $schema->dropAll();
        $schema->createAll();
    }

    private function resetPushApiSchema(): void
    {
        $this->service_factory->createPushApiSchema()->resetLog();
    }

    private function deleteOptions(): void
    {
        delete_option(SettingKey::WORDPRESS_API_KEY);
        delete_option(SettingKey::CHANNEL_VISITOR_DISCLOSURE_LANGUAGE_SETTINGS);
        delete_option(SettingKey::PULL_SYNCHRONIZATION_LATEST_ATTEMPT_STATUS);
        delete_option(SettingKey::PULL_SYNCHRONIZATION_NEXT_ATTEMPT_AT);
        delete_option(SettingKey::CHANNEL_DETAILS);
        delete_option(SettingKey::HAS_CONNECTED_SUCCESSFULLY_AT_LEAST_ONCE);
        delete_option(SettingKey::PUSH_API_ENABLED);
        delete_option(SettingKey::PUSH_API_SECRET);
        delete_option(SettingKey::PUSH_API_LAST_RECEIVED_AT);
        delete_option(SettingKey::RENDER_SCRIPT_REQUIRED_FILE_NAME);
        delete_option(SettingKey::RENDER_SCRIPT_REQUIRED_VERSION);
        delete_option(SettingKey::RENDER_SCRIPT_LATEST_FETCH_STATUS);
        delete_option(SettingKey::SCRIPT_FETCH_RECOVERY_NEXT_FETCH_TIMESTAMP);
        // Deliberately NOT deleted: VERATAG_DISCLOSURE_SCHEMA_VERSION /
        // VERATAG_PUSH_API_SCHEMA_VERSION — resetDisclosureSchema()/
        // resetPushApiSchema() above already recreated those exact schema
        // versions, so clearing these two would only cause the next
        // maybeUpgrade() call to redundantly drop+recreate tables that are
        // already fresh.
    }

    /** A freshly-installed site has its sync schedules armed immediately, not left unscheduled until the next admin page load triggers ensureSchedule() incidentally. */
    private function rescheduleSyncs(): void
    {
        $this->service_factory->createPullSynchronizationScheduler()->scheduleImmediate();
        $this->service_factory->createRenderScriptSyncScheduler()->ensureSchedule();
    }
}
