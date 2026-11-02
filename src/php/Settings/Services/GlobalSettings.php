<?php

namespace VeraTag\Settings\Services;

use VeraTag\Settings\OptionKeys\SettingKey;
use VeraTag\Settings\OptionValues\ChannelDetails;
use VeraTag\Settings\OptionValues\ChannelPaths;
use VeraTag\Settings\OptionValues\ChannelVisitorDisclosureLanguageSettings;
use VeraTag\Settings\OptionValues\PullSynchronizationAttemptStatus;
use VeraTag\Settings\OptionValues\RenderScriptFetchLatestFetchStatus;
use VeraTag\Settings\OptionValues\PushApiSettings;
use VeraTag\VeraTagBackend\HttpConfig;

final class GlobalSettings
{
    public function __construct(
        private readonly HttpConfig $http_config
    ) {
    }

    public function getApiKey(): string
    {
        $stored_api_key = get_option(SettingKey::WORDPRESS_API_KEY);

        if (! is_string($stored_api_key)) {
            return '';
        }

        return trim($stored_api_key);
    }

    public function setApiKey(string $api_key): void
    {
        update_option(SettingKey::WORDPRESS_API_KEY, trim($api_key), false);
    }

    public function getChannelVisitorDisclosureLanguageSettings(): ChannelVisitorDisclosureLanguageSettings
    {
        $stored = get_option(SettingKey::CHANNEL_VISITOR_DISCLOSURE_LANGUAGE_SETTINGS);

        if (! is_array($stored)) {
            return ChannelVisitorDisclosureLanguageSettings::createInitial();
        }

        return ChannelVisitorDisclosureLanguageSettings::fromArray($stored);
    }

    public function setChannelVisitorDisclosureLanguageSettings(ChannelVisitorDisclosureLanguageSettings $settings): void
    {
        update_option(SettingKey::CHANNEL_VISITOR_DISCLOSURE_LANGUAGE_SETTINGS, $settings->toArray(), false);
    }

    public function getLastPullSynchronizationStatus(): PullSynchronizationAttemptStatus
    {
        $stored_pull_synchronization_status = get_option(SettingKey::PULL_SYNCHRONIZATION_LATEST_ATTEMPT_STATUS);

        if (! is_array($stored_pull_synchronization_status)) {
            return PullSynchronizationAttemptStatus::createInitial();
        }

        return PullSynchronizationAttemptStatus::fromArray($stored_pull_synchronization_status);
    }

    public function setLastPullSynchronizationStatus(PullSynchronizationAttemptStatus $last_pull_synchronization_status): void
    {
        update_option(
            SettingKey::PULL_SYNCHRONIZATION_LATEST_ATTEMPT_STATUS,
            $last_pull_synchronization_status->toArray(),
            false
        );
    }

    public function getNextPullSynchronizationAt(): string
    {
        $stored_next_pull_synchronization_at = get_option(SettingKey::PULL_SYNCHRONIZATION_NEXT_ATTEMPT_AT);

        if (! is_string($stored_next_pull_synchronization_at)) {
            return '';
        }

        return trim($stored_next_pull_synchronization_at);
    }

    public function setNextPullSynchronizationAt(string $next_pull_synchronization_at): void
    {
        update_option(
            SettingKey::PULL_SYNCHRONIZATION_NEXT_ATTEMPT_AT,
            trim($next_pull_synchronization_at),
            false
        );
    }

    public function getRenderScriptLatestFetchStatus(): RenderScriptFetchLatestFetchStatus
    {
        $stored_status = get_option(SettingKey::RENDER_SCRIPT_LATEST_FETCH_STATUS);

        if (! is_array($stored_status)) {
            return RenderScriptFetchLatestFetchStatus::createInitial();
        }

        return RenderScriptFetchLatestFetchStatus::fromArray($stored_status);
    }

    public function setRenderScriptLatestFetchStatus(RenderScriptFetchLatestFetchStatus $render_script_latest_fetch_status): void
    {
        update_option(
            SettingKey::RENDER_SCRIPT_LATEST_FETCH_STATUS,
            $render_script_latest_fetch_status->toArray(),
            false
        );
    }

    /**
     * Empty string means "healthy" (current version already matches
     * required, or the last download attempt succeeded). Non-empty means a
     * download attempt failed and RenderScriptSyncScheduler should retry at
     * this timestamp.
     */
    public function getScriptFetchRecoveryNextFetchTimestamp(): string
    {
        $stored_timestamp = get_option(SettingKey::SCRIPT_FETCH_RECOVERY_NEXT_FETCH_TIMESTAMP);

        if (! is_string($stored_timestamp)) {
            return '';
        }

        return trim($stored_timestamp);
    }

    public function setScriptFetchRecoveryNextFetchTimestamp(string $script_fetch_recovery_next_fetch_timestamp): void
    {
        update_option(
            SettingKey::SCRIPT_FETCH_RECOVERY_NEXT_FETCH_TIMESTAMP,
            trim($script_fetch_recovery_next_fetch_timestamp),
            false
        );
    }

    public function clearScriptFetchRecoveryNextFetchTimestamp(): void
    {
        $this->setScriptFetchRecoveryNextFetchTimestamp('');
    }

    public function getChannelDetails(): ChannelDetails
    {
        $stored_channel_details = get_option(SettingKey::CHANNEL_DETAILS);

        if (! is_array($stored_channel_details)) {
            return ChannelDetails::createInitial();
        }

        return ChannelDetails::fromArray($stored_channel_details);
    }

    public function setChannelDetails(ChannelDetails $channel_details): void
    {
        update_option(SettingKey::CHANNEL_DETAILS, $channel_details->toArray(), false);
    }

    public function getChannelDisplayName(): string
    {
        return $this->getChannelDetails()->getDisplayName();
    }

    public function setChannelDisplayName(string $channel_display_name): void
    {
        $current_channel_details = $this->getChannelDetails();

        $this->setChannelDetails(
            new ChannelDetails(
                trim($channel_display_name),
                $current_channel_details->getPaths()
            )
        );
    }

    /**
     * The full "View in vera[tag] Dashboard" link, or '' if either half is
     * missing (no channel synced yet, or this build has no dashboard base
     * URL configured). Always derived — never stored directly — by joining
     * {@see HttpConfig::getDashboardBaseUrl} (this site's own vera[tag]
     * dashboard origin) with {@see ChannelPaths::getChannelDashboardPath}
     * (backend-owned, relative, persisted verbatim from the most recent
     * pull synchronization). Mirrors the join the admin UI itself does for
     * configuration/placement/text-template deep links
     * (see ContentOverviewSection.tsx's configurationDashboardUrl).
     */
    public function getChannelDashboardUrl(): string
    {
        $dashboard_base_url = rtrim($this->http_config->getDashboardBaseUrl(), '/');
        $dashboard_path     = trim($this->getChannelPaths()->getChannelDashboardPath(), '/');

        if ('' === $dashboard_base_url || '' === $dashboard_path) {
            return '';
        }

        return $dashboard_base_url . '/' . $dashboard_path;
    }

    public function getChannelPaths(): ChannelPaths
    {
        return $this->getChannelDetails()->getPaths();
    }

    public function hasConnectedSuccessfullyAtLeastOnce(): bool
    {
        $stored_connection_success = get_option(SettingKey::HAS_CONNECTED_SUCCESSFULLY_AT_LEAST_ONCE);

        return true === $stored_connection_success || '1' === $stored_connection_success || 1 === $stored_connection_success;
    }

    public function setHasConnectedSuccessfullyAtLeastOnce(bool $has_connected_successfully_at_least_once): void
    {
        update_option(
            SettingKey::HAS_CONNECTED_SUCCESSFULLY_AT_LEAST_ONCE,
            $has_connected_successfully_at_least_once,
            false
        );
    }

    public function getPushApiSettings(): PushApiSettings
    {
        return PushApiSettings::fromArray(
            array(
                'enabled'        => get_option(SettingKey::PUSH_API_ENABLED, false),
                'secret'         => get_option(SettingKey::PUSH_API_SECRET, ''),
                'lastReceivedAt' => get_option(SettingKey::PUSH_API_LAST_RECEIVED_AT, ''),
            )
        );
    }

    public function setPushApiSettings(PushApiSettings $settings): void
    {
        $data = $settings->toArray();
        update_option(SettingKey::PUSH_API_ENABLED, $data['enabled'], false);
        update_option(SettingKey::PUSH_API_SECRET, $data['secret'], false);
        update_option(SettingKey::PUSH_API_LAST_RECEIVED_AT, $data['lastReceivedAt'] ?? '', false);
    }

    /**
     * Generates a fresh push API secret WITHOUT storing it — the caller must
     * only persist it (via {@see storePushApiSecret}) once the backend has
     * actually confirmed it accepted this secret. Storing it unconditionally
     * before that confirmation would leave this site unable to receive any
     * pushes at all if the backend call then failed: the backend would still
     * expect the OLD secret (never told about the new one), while this site
     * would have already overwritten its own copy of it.
     *
     * Uses the same scheme the backend uses for its own pull API keys
     * ({@code PublicIdGenerator.generate32byte()}): 32 cryptographically
     * random bytes, URL-safe base64 encoded without padding.
     */
    public function generatePushApiSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Persists a push API secret produced by {@see generatePushApiSecret},
     * replacing whatever was stored before — call only after the backend has
     * confirmed it accepted this exact secret (see
     * {@code PushApiConfigurationBridgeController::configure}), so every
     * (re)configuration gets its own secret rather than ever reusing one (an
     * admin who previously removed/suspected a leaked secret must be able to
     * rotate it just by reconfiguring), while a failed backend call leaves
     * this site's stored secret untouched and therefore still valid.
     */
    public function storePushApiSecret(string $secret): void
    {
        $settings = $this->getPushApiSettings();
        $this->setPushApiSettings(new PushApiSettings($settings->isEnabled(), $secret, $settings->getLastReceivedAt()));
    }

    public function setPushApiEnabled(bool $enabled): void
    {
        $current = $this->getPushApiSettings();
        $this->setPushApiSettings(new PushApiSettings($enabled, $current->getSecret(), $current->getLastReceivedAt()));
    }

    public function setPushApiLastReceivedAt(?string $last_received_at): void
    {
        $current = $this->getPushApiSettings();
        $this->setPushApiSettings(new PushApiSettings($current->isEnabled(), $current->getSecret(), $last_received_at));
    }
}
