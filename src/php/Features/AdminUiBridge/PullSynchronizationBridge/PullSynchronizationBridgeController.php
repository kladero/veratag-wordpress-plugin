<?php

namespace VeraTag\Features\AdminUiBridge\PullSynchronizationBridge;

use VeraTag\Features\PullSynchronization\PullSynchronizationScheduler;
use VeraTag\Persistence\Disclosures\Repositories\BatchesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository;
use VeraTag\Settings\Services\GlobalSettings;
use WP_REST_Request;
use WP_REST_Response;

final class PullSynchronizationBridgeController
{
    public function __construct(
        private readonly GlobalSettings $global_settings,
        private readonly PullSynchronizationScheduler $scheduler,
        private readonly ContentItemsRepository $content_items_repository,
        private readonly BatchesRepository $batches_repository
    ) {
    }

    public function getStatus(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        $this->runDueScheduledSynchronizationIfNeeded();
        return new WP_REST_Response($this->buildResponse(true, 'Pull synchronization status loaded successfully.', null)->toArray(), 200);
    }
    public function runSynchronization(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        $result = $this->scheduler->run();
        if (is_wp_error($result)) {
            return new WP_REST_Response($this->buildResponse(false, $result->get_error_message(), null)->toArray(), 500);
        }
        return new WP_REST_Response($this->buildResponse(true, 'Pull synchronization completed successfully.', $result)->toArray(), 200);
    }
    private function buildResponse(bool $ok, string $message, ?int $synchronization_result): PullSynchronizationBridgeResponse
    {
        $last  = $this->global_settings->getLastPullSynchronizationStatus()->toArray();
        $batch = $this->batches_repository->getLatest();
        return new PullSynchronizationBridgeResponse(
            $ok,
            $message,
            $last,
            isset($last['receivedDisclosureEntryCount']) && is_int($last['receivedDisclosureEntryCount']) ? $last['receivedDisclosureEntryCount'] : null,
            $this->content_items_repository->countAll(),
            $this->resolveStoredContentSynchronizedAt($batch),
            $this->resolveNextPullSynchronizationAt(),
            $synchronization_result
        );
    }
    private function runDueScheduledSynchronizationIfNeeded(): void
    {
        $next = wp_next_scheduled($this->scheduler->getCronHook());
        if (false !== $next && (int) $next <= time()) {
            $this->scheduler->run();
        }
    }
    private function resolveStoredContentSynchronizedAt(?array $batch): ?string
    {
        if (! is_array($batch) || ! is_string($batch['fetched_at'] ?? null)) {
            return null;
        }
        $timestamp = strtotime($batch['fetched_at'] . ' UTC');
        return false === $timestamp ? null : gmdate('c', $timestamp);
    }
    private function resolveNextPullSynchronizationAt(): ?string
    {
        $next = $this->global_settings->getNextPullSynchronizationAt();
        return '' === $next ? null : $next;
    }
}
