<?php

namespace VeraTag\Features\PullSynchronization;

use VeraTag\Settings\OptionValues\ChannelDetails;
use VeraTag\Settings\OptionValues\ChannelPaths;
use VeraTag\Settings\OptionValues\ChannelVisitorDisclosureLanguageSettings;
use VeraTag\Settings\OptionValues\PullSynchronizationAttemptStatus;
use VeraTag\Settings\OptionValues\SynchronizationStatusSource;
use VeraTag\Settings\Services\GlobalSettings;
use WP_Error;

final class PullSynchronizationScheduler
{
    private const CRON_HOOK              = 'veratag_periodic_disclosure_sync_event';
    private const CRON_SCHEDULE          = 'veratag_every_45_minutes';
    private const FETCH_INTERVAL_SECONDS = 2700;

    public function __construct(
        private readonly PullSynchronizationApiService $api_service,
        private readonly PullSynchronizationPersistenceService $persistence_service,
        private readonly GlobalSettings $global_settings
    ) {
    }

    public function extendCronSchedules(array $schedules): array
    {
        $schedules[ self::CRON_SCHEDULE ] = array(
            'interval' => self::FETCH_INTERVAL_SECONDS,
            'display'  => 'Every 45 minutes',
        );

        return $schedules;
    }

    public function ensureSchedule(): void
    {
        $next_scheduled_timestamp = wp_next_scheduled(self::CRON_HOOK);
        $expected_next_timestamp  = time() + self::FETCH_INTERVAL_SECONDS;

        if (false === $next_scheduled_timestamp) {
            $this->scheduleFromTimestamp($expected_next_timestamp);
            return;
        }

        if ($this->shouldRefreshExistingSchedule((int) $next_scheduled_timestamp, $expected_next_timestamp)) {
            $this->clearSchedule();
            $this->scheduleFromTimestamp($expected_next_timestamp);
            return;
        }

        $this->global_settings->setNextPullSynchronizationAt(gmdate('c', (int) $next_scheduled_timestamp));
    }

    public function clearSchedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        $this->global_settings->setNextPullSynchronizationAt('');
    }

    public function resetLatestAttemptStatus(): void
    {
        $this->global_settings->setLastPullSynchronizationStatus(PullSynchronizationAttemptStatus::createInitial());
    }

    public function scheduleImmediate(): void
    {
        $this->clearSchedule();
        $this->scheduleFromTimestamp(time());
    }

    public function run(): int|WP_Error
    {
        $api_key = $this->global_settings->getApiKey();

        $fetch_result = $this->api_service->fetchDisclosures($api_key);

        if (! $fetch_result->isSuccessful()) {
            $error = new WP_Error(
                'veratag_pull_synchronization_failed',
                $this->resolveApiFailureMessage($fetch_result),
                array(
                    'status' => $fetch_result->getStatusCode(),
                )
            );

            $this->recordFailureStatus(
                $error->get_error_message(),
                $fetch_result->getStatusCode(),
                $fetch_result->getStatusText(),
                $fetch_result->getStatusSource()
            );
            $this->rescheduleFromNow();

            return $error;
        }

        $api_response_dto = $fetch_result->getApiResponseDto();

        if (! ( $api_response_dto instanceof PullSynchronizationApiResponseDto )) {
            $missing_dto_error = new WP_Error(
                'veratag_pull_synchronization_missing_response_dto',
                'Pull synchronization did not yield a backend response DTO.',
                array(
                    'status' => $fetch_result->getStatusCode(),
                )
            );

            $this->recordFailureStatus(
                $missing_dto_error->get_error_message(),
                $fetch_result->getStatusCode(),
                $fetch_result->getStatusText(),
                SynchronizationStatusSource::LOCAL_PLUGIN
            );
            $this->rescheduleFromNow();

            return $missing_dto_error;
        }

        $this->updateChannelDetailsFromApiResponse($api_response_dto);
        $this->global_settings->setHasConnectedSuccessfullyAtLeastOnce(true);

        $persistence_result = $this->persistence_service->storeApiResponse($api_response_dto);

        if (is_wp_error($persistence_result)) {
            $this->recordFailureStatus(
                $persistence_result->get_error_message(),
                $fetch_result->getStatusCode(),
                $fetch_result->getStatusText(),
                SynchronizationStatusSource::LOCAL_PLUGIN
            );
            $this->rescheduleFromNow();

            return $persistence_result;
        }

        $this->recordSuccessStatus(
            $fetch_result->getStatusCode(),
            $fetch_result->getStatusText(),
            $api_response_dto->countRegisteredContentItems()
        );
        $this->rescheduleFromNow();

        return $persistence_result;
    }

    public function getCronHook(): string
    {
        return self::CRON_HOOK;
    }

    private function resolveApiFailureMessage(PullSynchronizationApiFetchResult $fetch_result): string
    {
        $error_message = $fetch_result->getErrorMessage();

        if (is_string($error_message) && '' !== trim($error_message)) {
            return $error_message;
        }

        return 'Pull synchronization failed.';
    }

    private function recordFailureStatus(string $message, int $http_response_code, string $http_status_text, string $status_source): void
    {
        $this->global_settings->setLastPullSynchronizationStatus(
            new PullSynchronizationAttemptStatus(
                false,
                $http_response_code,
                $http_status_text,
                $status_source,
                $message,
                gmdate('c'),
                null
            )
        );
    }

    private function recordSuccessStatus(int $http_response_code, string $http_status_text, int $received_disclosure_entry_count): void
    {
        $this->global_settings->setLastPullSynchronizationStatus(
            new PullSynchronizationAttemptStatus(
                true,
                $http_response_code,
                $http_status_text,
                SynchronizationStatusSource::REMOTE_BACKEND_RESPONSE,
                'Pull synchronization completed successfully.',
                gmdate('c'),
                $received_disclosure_entry_count
            )
        );
    }

    private function rescheduleFromNow(): void
    {
        $this->clearSchedule();
        $this->scheduleFromTimestamp(time() + self::FETCH_INTERVAL_SECONDS);
    }

    private function updateChannelDetailsFromApiResponse(PullSynchronizationApiResponseDto $api_response_dto): void
    {
        $channel_details = $api_response_dto->getChannelDetails();

        $this->global_settings->setChannelDetails(
            new ChannelDetails(
                $channel_details->getDisplayName(),
                ChannelPaths::fromArray($channel_details->getPaths())
            )
        );

        $this->global_settings->setChannelVisitorDisclosureLanguageSettings(
            new ChannelVisitorDisclosureLanguageSettings(
                $channel_details->getVisitorPreferredDisclosureLanguageMode(),
                $channel_details->getVisitorFallbackDisclosureLanguageBcp47()
            )
        );
    }


    private function scheduleFromTimestamp(int $timestamp): void
    {
        wp_schedule_event($timestamp, self::CRON_SCHEDULE, self::CRON_HOOK);
        $this->global_settings->setNextPullSynchronizationAt(gmdate('c', $timestamp));
    }

    private function shouldRefreshExistingSchedule(int $next_scheduled_timestamp, int $expected_next_timestamp): bool
    {
        if ($next_scheduled_timestamp <= $expected_next_timestamp + 5) {
            return false;
        }

        return true;
    }
}
