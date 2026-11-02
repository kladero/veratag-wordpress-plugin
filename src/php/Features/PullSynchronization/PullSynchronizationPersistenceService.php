<?php

namespace VeraTag\Features\PullSynchronization;

use VeraTag\Features\RenderScriptSync\RenderScriptReconciliationService;
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
use VeraTag\Persistence\Disclosures\Repositories\TextTemplatesRepository;
use WP_Error;

/** Stores the authoritative pull snapshot while preserving shared ref-keyed dictionaries for incremental pushes. */
final class PullSynchronizationPersistenceService
{
    public function __construct(
        private readonly BatchesRepository $batches_repository,
        private readonly ContentOverviewRepository $content_overview_repository,
        private readonly PagesRepository $pages_repository,
        private readonly ContentItemsRepository $content_items_repository,
        private readonly ContentItemIssuesRepository $content_item_issues_repository,
        private readonly ConfigurationsRepository $configurations_repository,
        private readonly PlacementsRepository $placements_repository,
        private readonly TextTemplatesRepository $text_templates_repository,
        private readonly DictionaryGarbageCollector $garbage_collector,
        private readonly RenderScriptReconciliationService $render_script_reconciliation_service,
        private readonly ContentItemIssueAggregationService $content_item_issue_aggregation_service,
        private readonly DisclosureSnapshotTransactionService $snapshot_transaction_service
    ) {
    }

    public function storeApiResponse(PullSynchronizationApiResponseDto $api_response): int|WP_Error
    {
        $outgoing_configuration_refs = array();
        $detached_placement_refs     = array();
        $detached_template_refs      = array();
        $batch_id                    = $this->snapshot_transaction_service->execute(
            function () use ($api_response, &$outgoing_configuration_refs, &$detached_placement_refs, &$detached_template_refs): int|WP_Error {
                $outgoing_configuration_refs = $this->content_items_repository->findDistinctConfigurationRefs();
                $dictionaries_result         = $this->persistDictionaries($api_response, $detached_placement_refs, $detached_template_refs);
                if (is_wp_error($dictionaries_result)) {
                    return $dictionaries_result;
                }
                $cleared = $this->clearSnapshotRows();
                if (is_wp_error($cleared)) {
                    return $cleared;
                }
                return $this->persistSnapshotRows($api_response);
            }
        );
        if (is_wp_error($batch_id)) {
            return $batch_id;
        }

        $this->garbage_collector->collectGarbageForDetachedRefs($detached_placement_refs, $detached_template_refs);
        foreach ($outgoing_configuration_refs as $ref) {
            $this->garbage_collector->collectGarbageForConfigurationRef($ref);
        }
        $this->render_script_reconciliation_service->reconcileAllRequired();

        return $batch_id;
    }

    private function persistSnapshotRows(PullSynchronizationApiResponseDto $api_response): int|WP_Error
    {
        $fetched_at = current_time('mysql', true);
        $batch_id   = $this->batches_repository->insert($fetched_at, $api_response->countRegisteredContentItems(), null);
        if (is_wp_error($batch_id)) {
            return $batch_id;
        }

        $overview = $this->content_overview_repository->insert(
            $batch_id,
            $api_response->countRegisteredContentItems(),
            $api_response->countConfiguredContentItems(),
            $api_response->countUnconfiguredContentItems()
        );
        if (is_wp_error($overview)) {
            return $overview;
        }

        foreach ($api_response->getPages() as $page) {
            $result = $this->persistPage($batch_id, $page);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        return $batch_id;
    }

    /** @param string[] $detached_placement_refs @param string[] $detached_template_refs */
    private function persistDictionaries(PullSynchronizationApiResponseDto $api_response, array &$detached_placement_refs, array &$detached_template_refs): bool|WP_Error
    {
        foreach ($api_response->getPlacements() as $ref => $entry) {
            $result = $this->placements_repository->upsert($ref, $entry->toArray());
            if (is_wp_error($result)) {
                return $result;
            }
        }
        foreach ($api_response->getTextTemplates() as $ref => $entry) {
            $result = $this->text_templates_repository->upsert($ref, $entry->toArray());
            if (is_wp_error($result)) {
                return $result;
            }
        }
        foreach ($api_response->getConfigurations() as $ref => $entry) {
            $configuration_data = $entry->toArray();
            $existing           = $this->configurations_repository->findByRef($ref);
            $old_placements     = is_array($existing) ? $this->decodeStringArray($existing['placement_refs'] ?? '[]') : array();
            $old_templates      = is_array($existing) ? ConfigurationsRepository::extractTemplateRefs($this->decodeAssoc($existing['slots'] ?? '{}')) : array();
            $new_placements     = isset($configuration_data['placementRefs']) && is_array($configuration_data['placementRefs']) ? array_values(array_filter($configuration_data['placementRefs'], 'is_string')) : array();
            $new_slots          = isset($configuration_data['slots']) && is_array($configuration_data['slots']) ? $configuration_data['slots'] : array();
            $new_templates      = ConfigurationsRepository::extractTemplateRefs($new_slots);
            array_push($detached_placement_refs, ...array_values(array_diff($old_placements, $new_placements)));
            array_push($detached_template_refs, ...array_values(array_diff($old_templates, $new_templates)));
            $result = $this->configurations_repository->upsert(
                $ref,
                isset($configuration_data['name']) && is_string($configuration_data['name']) ? $configuration_data['name'] : '',
                $new_placements,
                $new_slots,
                isset($configuration_data['issues']) && is_array($configuration_data['issues']) ? $configuration_data['issues'] : array()
            );
            if (is_wp_error($result)) {
                return $result;
            }
        }
        return true;
    }

    private function persistPage(int $batch_id, PullSynchronizationApiPageDto $page): bool|WP_Error
    {
        $page_id = $this->pages_repository->insert($batch_id, $page->getPageUrl());
        if (is_wp_error($page_id)) {
            return $page_id;
        }
        foreach ($page->getContent() as $content) {
            $content_item_id = $this->content_items_repository->insert(
                $page_id,
                $content->getContentId(),
                $content->getContentType(),
                $content->getContentHtmlTag(),
                $this->encodeJson($content->getContentDisplay()),
                true === $content->getShouldBeDisclosed(),
                $content->getDisclosureType(),
                $content->getConfigurationRef()
            );
            if (is_wp_error($content_item_id)) {
                return $content_item_id;
            }
            $issues_result = $this->content_item_issue_aggregation_service->reconcileOne(
                $content_item_id,
                $content->getConfigurationRef(),
                $this->toRawIssues($content->getIssues())
            );
            if (is_wp_error($issues_result)) {
                return $issues_result;
            }
        }
        return true;
    }

    /** @param PullSynchronizationApiIssueDto[] $issues */
    private function toRawIssues(array $issues): array
    {
        return array_map(
            static fn (PullSynchronizationApiIssueDto $issue): array => array(
                'reason'     => $issue->getReason(),
                'impact'     => $issue->getImpact(),
                'message'    => $issue->getMessage(),
                'detectedAt' => $issue->getDetectedAt(),
            ),
            $issues
        );
    }

    private function clearSnapshotRows(): bool|WP_Error
    {
        $page_ids         = array_map(static fn (array $row): int => (int) $row['id'], $this->pages_repository->findAll());
        $content_item_ids = $this->content_items_repository->findIdsByPageIds($page_ids);
        $result           = $this->content_item_issues_repository->deleteByContentItemIds($content_item_ids);
        if (is_wp_error($result)) {
            return $result;
        }
        $result = $this->content_items_repository->deleteAll();
        if (is_wp_error($result)) {
            return $result;
        }
        $result = $this->pages_repository->deleteAll();
        if (is_wp_error($result)) {
            return $result;
        }
        $result = $this->content_overview_repository->deleteAll();
        if (is_wp_error($result)) {
            return $result;
        }
        return $this->batches_repository->deleteAll();
    }

    private function encodeJson(mixed $value): string
    {
        $encoded = wp_json_encode($value);
        return false === $encoded ? 'null' : $encoded;
    }
    private function decodeStringArray(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : array();
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : array();
    }
    private function decodeAssoc(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : array();
        return is_array($decoded) ? $decoded : array();
    }
}
