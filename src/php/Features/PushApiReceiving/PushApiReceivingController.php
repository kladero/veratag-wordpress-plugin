<?php

namespace VeraTag\Features\PushApiReceiving;

use Throwable;
use VeraTag\Features\FrontendServing\DisclosuresRendering\RenderScriptFileWriter;
use VeraTag\Features\PullSynchronization\PullSynchronizationApiContentItemDto;
use VeraTag\Features\PluginReset\PluginResetService;
use VeraTag\Features\RenderScriptSync\RenderScriptReconciliationService;
use VeraTag\Persistence\Disclosures\ContentItemIssueAggregationService;
use VeraTag\Persistence\Disclosures\DictionaryGarbageCollector;
use VeraTag\Persistence\Disclosures\Repositories\BatchesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ConfigurationsRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemIssuesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository;
use VeraTag\Persistence\Disclosures\Repositories\PagesRepository;
use VeraTag\Persistence\Disclosures\Repositories\PlacementsRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptFetchStatusesRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptsRepository;
use VeraTag\Persistence\Disclosures\Repositories\TextTemplatesRepository;
use VeraTag\Persistence\PushApi\PushInstructionTransactionService;
use VeraTag\Persistence\PushApi\Repositories\PushReceivedLogRepository;
use VeraTag\Settings\OptionValues\ChannelDetails;
use VeraTag\Settings\OptionValues\ChannelVisitorDisclosureLanguageSettings;
use VeraTag\Settings\Services\GlobalSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PushApiReceivingController
{
    public function __construct(
        private readonly BatchesRepository $batches_repository,
        private readonly PagesRepository $pages_repository,
        private readonly ContentItemsRepository $content_items_repository,
        private readonly ContentItemIssuesRepository $content_item_issues_repository,
        private readonly ConfigurationsRepository $configurations_repository,
        private readonly PlacementsRepository $placements_repository,
        private readonly TextTemplatesRepository $text_templates_repository,
        private readonly RenderScriptsRepository $render_scripts_repository,
        private readonly RenderScriptFetchStatusesRepository $render_script_statuses_repository,
        private readonly DictionaryGarbageCollector $garbage_collector,
        private readonly RenderScriptReconciliationService $render_script_reconciliation_service,
        private readonly RenderScriptFileWriter $render_script_file_writer,
        private readonly PushReceivedLogRepository $push_log_repository,
        private readonly GlobalSettings $global_settings,
        private readonly ContentItemIssueAggregationService $content_item_issue_aggregation_service,
        private readonly PushInstructionTransactionService $push_instruction_transaction_service
    ) {
    }

    public function receivePush(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = $request->get_json_params();
        if (! is_array($payload) || ! isset($payload['instruction']) || ! is_string($payload['instruction']) || ! isset($payload['data']) || ! is_array($payload['data'])) {
            return new WP_Error('veratag_push_api_invalid_payload', 'Push payload must contain string instruction and object data.', array( 'status' => 400 ));
        }
        $payload     = $this->restoreEmptyObjectShapes($payload, $request->get_body());
        $instruction = trim($payload['instruction']);
        if ('' === $instruction) {
            return new WP_Error('veratag_push_api_invalid_instruction', 'Push instruction is required.', array( 'status' => 400 ));
        }
        $data = $payload['data'];
        if (! is_array($data)) {
            return new WP_Error('veratag_push_api_invalid_payload', 'Push payload data must be an object.', array( 'status' => 400 ));
        }
        $ref = $this->deriveReference($instruction, $data);

        try {
            // CHANNEL_DELETE performs schema DDL through PluginResetService; MySQL
            // implicitly commits around DDL, so only incremental DML instructions
            // can be made atomic by PushInstructionTransactionService.
            $result = 'CHANNEL_DELETE' === $instruction
                ? $this->applyInstruction($instruction, $data)
                : $this->push_instruction_transaction_service->execute(
                    fn (): bool|WP_Error => $this->applyInstruction($instruction, $data)
                );
            if (is_wp_error($result)) {
                return $this->failedPushResponse($instruction, $ref, $result->get_error_message(), $this->statusFromError($result));
            }
            $this->global_settings->setPushApiLastReceivedAt(gmdate('c'));
            $this->push_log_repository->insert($instruction, $ref, true, null);
            return new WP_REST_Response(
                array(
                    'ok'          => true,
                    'instruction' => $instruction,
                    'ref'         => $ref,
                ),
                200
            );
        } catch (Throwable $throwable) {
            return $this->failedPushResponse($instruction, $ref, $throwable->getMessage(), 500);
        }
    }

    private function applyInstruction(string $instruction, array $data): bool|WP_Error
    {
        return match ($instruction) {
            'WEB_CONTENT_UPSERT' => $this->applyContentUpsert($data),
            'WEB_CONTENT_DELETE' => $this->applyContentDelete($data),
            'WEB_CONFIGURATION_UPSERT' => $this->applyConfigurationUpsert($data),
            'WEB_CONFIGURATION_DELETE' => $this->applyConfigurationDelete($data),
            'WEB_PLACEMENT_UPSERT' => $this->applyPlacementUpsert($data),
            'WEB_PLACEMENT_DELETE' => $this->applyPlacementDelete($data),
            'WEB_TEXT_TEMPLATE_UPSERT' => $this->applyTextTemplateUpsert($data),
            'WEB_TEXT_TEMPLATE_DELETE' => $this->applyTextTemplateDelete($data),
            'CHANNEL_UPSERT' => $this->applyChannelUpsert($data),
            'CHANNEL_DELETE' => $this->applyChannelDelete(),
            default => new WP_Error('veratag_push_api_unknown_instruction', "Unsupported push instruction: {$instruction}.", array( 'status' => 400 )),
        };
    }

    private function applyContentUpsert(array $data): bool|WP_Error
    {
        $page_url = $this->requiredString($data, 'pageUrl');
        if (is_wp_error($page_url)) {
            return $page_url;
        }
        if (! isset($data['content']) || ! is_array($data['content'])) {
            return $this->invalid('WEB_CONTENT_UPSERT requires content.');
        }

        $result = $this->upsertDictionaryMaps($data);
        if (is_wp_error($result)) {
            return $result;
        }

        $content = PullSynchronizationApiContentItemDto::fromArray($data['content']);
        if ('' === $content->getContentId()) {
            return $this->invalid('WEB_CONTENT_UPSERT contentId is required.');
        }
        $latest  = $this->batches_repository->getLatest();
        $page_id = $this->pages_repository->getOrCreate($page_url, is_array($latest) && isset($latest['id']) ? (int) $latest['id'] : 0);
        if (is_wp_error($page_id)) {
            return $page_id;
        }
        $existing              = $this->content_items_repository->findByPageAndContentId($page_id, $content->getContentId());
        $old_configuration_ref = is_array($existing) && is_string($existing['configuration_ref'] ?? null) ? (string) $existing['configuration_ref'] : null;

        $content_item_id = $this->content_items_repository->upsert(
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
        $result = $this->content_item_issue_aggregation_service->reconcileOne(
            $content_item_id,
            $content->getConfigurationRef(),
            array_map(
                static fn ($issue): array => array(
                    'reason'     => $issue->getReason(),
                    'impact'     => $issue->getImpact(),
                    'message'    => $issue->getMessage(),
                    'detectedAt' => $issue->getDetectedAt(),
                ),
                $content->getIssues()
            )
        );
        if (is_wp_error($result)) {
            return $result;
        }
        $new_configuration_ref = $content->getConfigurationRef();
        if (null !== $old_configuration_ref && $old_configuration_ref !== $new_configuration_ref) {
            $this->garbage_collector->collectGarbageForConfigurationRef($old_configuration_ref);
        }
        return true;
    }

    private function applyContentDelete(array $data): bool|WP_Error
    {
        $page_url   = $this->requiredString($data, 'pageUrl');
        $content_id = $this->requiredString($data, 'contentId');
        if (is_wp_error($page_url)) {
            return $page_url;
        }
        if (is_wp_error($content_id)) {
            return $content_id;
        }
        $row = $this->content_items_repository->findByPageUrlAndContentId($page_url, $content_id);
        if (! is_array($row)) {
            return true;
        }
        $configuration_ref = is_string($row['configuration_ref'] ?? null) ? (string) $row['configuration_ref'] : null;
        $id                = (int) $row['id'];
        $this->content_item_issues_repository->deleteByContentItemIds(array( $id ));
        $this->content_items_repository->deleteById($id);
        if (null !== $configuration_ref && '' !== trim($configuration_ref)) {
            $this->garbage_collector->collectGarbageForConfigurationRef($configuration_ref);
        }
        return true;
    }

    private function applyConfigurationUpsert(array $data): bool|WP_Error
    {
        $ref = $this->requiredString($data, 'configurationRef');
        if (is_wp_error($ref)) {
            return $ref;
        }
        if (! isset($data['configuration']) || ! is_array($data['configuration'])) {
            return $this->invalid('WEB_CONFIGURATION_UPSERT requires configuration.');
        }
        $result = $this->upsertDictionaryMaps($data);
        if (is_wp_error($result)) {
            return $result;
        }
        $result = $this->configurations_repository->upsertWithGc($ref, $data['configuration'], $this->garbage_collector);
        if (is_wp_error($result)) {
            return $result;
        }
        $result = $this->content_item_issue_aggregation_service->reconcileAllByConfigurationRef($ref);
        if (is_wp_error($result)) {
            return $result;
        }
        return true;
    }

    private function applyConfigurationDelete(array $data): bool|WP_Error
    {
        $ref = $this->requiredString($data, 'configurationRef');
        if (is_wp_error($ref)) {
            return $ref;
        }
        $row = $this->configurations_repository->findByRef($ref);
        if (! is_array($row)) {
            return true;
        }
        $placements = $this->decodeStringArray($row['placement_refs'] ?? '[]');
        $templates  = ConfigurationsRepository::extractTemplateRefs($this->decodeAssoc($row['slots'] ?? '{}'));
        $this->configurations_repository->deleteByRef($ref);
        $this->garbage_collector->collectGarbageForDetachedRefs($placements, $templates);
        return true;
    }

    private function applyPlacementUpsert(array $data): bool|WP_Error
    {
        $ref = $this->requiredString($data, 'placementRef');
        if (is_wp_error($ref)) {
            return $ref;
        }
        if (! isset($data['placement']) || ! is_array($data['placement'])) {
            return $this->invalid('WEB_PLACEMENT_UPSERT requires placement.');
        }
        $result = $this->placements_repository->upsert($ref, $data['placement']);
        if (is_wp_error($result)) {
            return $result;
        }
        $render  = isset($data['placement']['renderScript']) && is_array($data['placement']['renderScript']) ? $data['placement']['renderScript'] : array();
        $file    = is_string($render['fileName'] ?? null) ? trim($render['fileName']) : '';
        $version = is_string($render['scriptReleaseVersion'] ?? null) ? trim($render['scriptReleaseVersion']) : '';
        if ('' !== $file && '' !== $version) {
            $this->render_script_reconciliation_service->reconcile($file, $version);
        }
        $result = $this->content_item_issue_aggregation_service->reconcileAllByPlacementRef($ref);
        if (is_wp_error($result)) {
            return $result;
        }
        return true;
    }

    private function applyPlacementDelete(array $data): bool|WP_Error
    {
        $ref = $this->requiredString($data, 'placementRef');
        if (is_wp_error($ref)) {
            return $ref;
        }
        $row  = $this->placements_repository->findByRef($ref);
        $file = is_array($row) && is_string($row['render_script_file_name'] ?? null) ? (string) $row['render_script_file_name'] : '';
        $this->placements_repository->deleteByRef($ref);
        if ('' !== $file && $this->placements_repository->countByRenderScriptFileName($file) < 1) {
            $this->render_scripts_repository->deleteByFileName($file);
            $this->render_script_statuses_repository->deleteByFileName($file);
            $this->render_script_file_writer->delete($file);
        }
        return true;
    }

    private function applyTextTemplateUpsert(array $data): bool|WP_Error
    {
        $ref = $this->requiredString($data, 'textTemplateRef');
        if (is_wp_error($ref)) {
            return $ref;
        }
        if (! isset($data['textTemplate']) || ! is_array($data['textTemplate'])) {
            return $this->invalid('WEB_TEXT_TEMPLATE_UPSERT requires textTemplate.');
        }
        $result = $this->text_templates_repository->upsert($ref, $data['textTemplate']);
        if (is_wp_error($result)) {
            return $result;
        }
        $result = $this->content_item_issue_aggregation_service->reconcileAllByTemplateRef($ref);
        if (is_wp_error($result)) {
            return $result;
        }
        return true;
    }
    private function applyTextTemplateDelete(array $data): bool|WP_Error
    {
        $ref = $this->requiredString($data, 'textTemplateRef');
        if (is_wp_error($ref)) {
            return $ref;
        }
        $this->text_templates_repository->deleteByRef($ref);
        return true;
    }
    /**
     * The channel this site was connected to no longer exists on the
     * backend at all — there is nothing left to patch incrementally, so
     * the entire local plugin state is reset back to how it was on a
     * fresh install (see PluginResetService) rather than attempting to
     * selectively clear just this one channel's data.
     */
    private function applyChannelDelete(): bool
    {
        ( new PluginResetService() )->reset();
        return true;
    }

    private function applyChannelUpsert(array $data): bool
    {
        $display_name = is_string($data['displayName'] ?? null) ? trim($data['displayName']) : '';
        $current      = $this->global_settings->getChannelDetails();
        $this->global_settings->setChannelDetails(new ChannelDetails($display_name, $current->getPaths()));

        $preferred_mode          = is_string($data['visitorPreferredDisclosureLanguageMode'] ?? null)
            ? trim($data['visitorPreferredDisclosureLanguageMode'])
            : '';
        $fallback_language_bcp47 = is_string($data['visitorFallbackDisclosureLanguageBcp47'] ?? null)
            ? trim($data['visitorFallbackDisclosureLanguageBcp47'])
            : '';
        if ('' !== $preferred_mode && '' !== $fallback_language_bcp47) {
            $this->global_settings->setChannelVisitorDisclosureLanguageSettings(
                new ChannelVisitorDisclosureLanguageSettings($preferred_mode, $fallback_language_bcp47)
            );
        }

        return true;
    }

    /** Upsert any partial dictionaries bundled by content/configuration pushes. */
    /**
     * Upserts every bundled dictionary entry a {@code WEB_CONTENT_UPSERT}/
     * {@code WEB_CONFIGURATION_UPSERT} payload carries as a dependency of
     * its own primary ref (e.g. a content item's configuration, and that
     * configuration's placements/templates). Each bundled configuration ref
     * is re-aggregated here too — not just the caller's own primary ref —
     * since a bundled configuration can be one shared by other content
     * items the caller doesn't itself know about.
     */
    private function upsertDictionaryMaps(array $data): bool|WP_Error
    {
        $text_templates = isset($data['textTemplates']) && is_array($data['textTemplates']) ? $data['textTemplates'] : array();
        foreach ($text_templates as $ref => $entry) {
            if (is_string($ref) && is_array($entry)) {
                $r = $this->text_templates_repository->upsert($ref, $entry);
                if (is_wp_error($r)) {
                    return $r;
                }
            }
        }
        $placements = isset($data['placements']) && is_array($data['placements']) ? $data['placements'] : array();
        foreach ($placements as $ref => $entry) {
            if (! is_string($ref) || ! is_array($entry)) {
                continue;
            }
            $r = $this->placements_repository->upsert($ref, $entry);
            if (is_wp_error($r)) {
                return $r;
            }
            $render  = isset($entry['renderScript']) && is_array($entry['renderScript']) ? $entry['renderScript'] : array();
            $file    = is_string($render['fileName'] ?? null) ? trim($render['fileName']) : '';
            $version = is_string($render['scriptReleaseVersion'] ?? null) ? trim($render['scriptReleaseVersion']) : '';
            if ('' !== $file && '' !== $version) {
                $this->render_script_reconciliation_service->reconcile($file, $version);
            }
        }
        $configurations = isset($data['configurations']) && is_array($data['configurations']) ? $data['configurations'] : array();
        foreach ($configurations as $ref => $entry) {
            if (! is_string($ref) || ! is_array($entry)) {
                continue;
            }
            $r = $this->configurations_repository->upsertWithGc($ref, $entry, $this->garbage_collector);
            if (is_wp_error($r)) {
                return $r;
            }
        }
        foreach ($placements as $ref => $entry) {
            if (is_string($ref)) {
                $r = $this->content_item_issue_aggregation_service->reconcileAllByPlacementRef($ref);
                if (is_wp_error($r)) {
                    return $r;
                }
            }
        }
        foreach ($text_templates as $ref => $entry) {
            if (is_string($ref)) {
                $r = $this->content_item_issue_aggregation_service->reconcileAllByTemplateRef($ref);
                if (is_wp_error($r)) {
                    return $r;
                }
            }
        }
        foreach ($configurations as $ref => $entry) {
            if (is_string($ref)) {
                $r = $this->content_item_issue_aggregation_service->reconcileAllByConfigurationRef($ref);
                if (is_wp_error($r)) {
                    return $r;
                }
            }
        }
        return true;
    }

    private function requiredString(array $data, string $key): string|WP_Error
    {
        if (! isset($data[ $key ]) || ! is_string($data[ $key ]) || '' === trim($data[ $key ])) {
            return $this->invalid("Missing required {$key}.");
        }
        return trim($data[ $key ]);
    }
    private function invalid(string $message): WP_Error
    {
        return new WP_Error('veratag_push_api_invalid_data', $message, array( 'status' => 400 ));
    }
    private function failedPushResponse(string $instruction, ?string $ref, string $message, int $status): WP_REST_Response
    {
        $this->global_settings->setPushApiLastReceivedAt(gmdate('c'));
        $this->push_log_repository->insert($instruction, $ref, false, $message);
        return new WP_REST_Response(
            array(
                'ok'          => false,
                'instruction' => $instruction,
                'ref'         => $ref,
                'message'     => $message,
            ),
            $status
        );
    }
    private function statusFromError(WP_Error $error): int
    {
        $error_data = $error->get_error_data();
        if (is_array($error_data) && isset($error_data['status']) && is_int($error_data['status']) && $error_data['status'] >= 400 && $error_data['status'] <= 599) {
            return $error_data['status'];
        }
        return 500;
    }
    private function deriveReference(string $instruction, array $data): ?string
    {
        foreach (array( 'configurationRef', 'placementRef', 'textTemplateRef', 'contentId', 'channelPublicId' ) as $key) {
            if (isset($data[ $key ]) && is_string($data[ $key ]) && '' !== trim($data[ $key ])) {
                return trim($data[ $key ]);
            }
        }
        if ('WEB_CONTENT_UPSERT' === $instruction && isset($data['content']['contentId']) && is_string($data['content']['contentId'])) {
            return trim($data['content']['contentId']);
        }
        return null;
    }
    private function encodeJson(mixed $v): string
    {
        $encoded = wp_json_encode($v);
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

    private function restoreEmptyObjectShapes(array $payload, string $raw_body): array
    {
        $object_form = json_decode($raw_body);

        if (! ( $object_form instanceof \stdClass )) {
            return $payload;
        }

        $merged = $this->mergeEmptyObjectShapes($payload, $object_form, true);

        return is_array($merged) ? $merged : $payload;
    }

    private function mergeEmptyObjectShapes(mixed $array_node, mixed $object_node, bool $preserve_array_shape = false): mixed
    {
        if (is_array($array_node) && array() === $array_node && $object_node instanceof \stdClass) {
            return $preserve_array_shape ? array() : new \stdClass();
        }

        if (is_array($array_node) && $object_node instanceof \stdClass) {
            $object_properties = get_object_vars($object_node);
            foreach ($array_node as $key => $value) {
                if (array_key_exists($key, $object_properties)) {
                    // The request envelope's data member is always consumed as a PHP array.
                    // Nested empty objects still retain their JSON object shape for persistence.
                    $array_node[ $key ] = $this->mergeEmptyObjectShapes($value, $object_properties[ $key ], $preserve_array_shape && 'data' === $key);
                }
            }
            return $array_node;
        }

        if (is_array($array_node) && is_array($object_node)) {
            foreach ($array_node as $index => $value) {
                if (array_key_exists($index, $object_node)) {
                    $array_node[ $index ] = $this->mergeEmptyObjectShapes($value, $object_node[ $index ]);
                }
            }
            return $array_node;
        }

        return $array_node;
    }
}
