<?php

namespace VeraTag\Features\FrontendServing\DisclosuresDataLoading;

use VeraTag\Persistence\Disclosures\Repositories\BatchesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ConfigurationsRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository;
use VeraTag\Persistence\Disclosures\Repositories\PagesRepository;
use VeraTag\Persistence\Disclosures\Repositories\PlacementsRepository;
use VeraTag\Persistence\Disclosures\Repositories\TextTemplatesRepository;

/**
 * Resolves the latest ref-keyed local disclosure snapshot into the renderer's
 * existing intermediate shape. Shared configuration/placement/template state
 * is read live by ref, so an incremental push is visible without another pull.
 */
final class DisclosuresDataQueryService
{
    public function __construct(
        private readonly BatchesRepository $batches_repository,
        private readonly PagesRepository $pages_repository,
        private readonly ContentItemsRepository $content_items_repository,
        private readonly ConfigurationsRepository $configurations_repository,
        private readonly PlacementsRepository $placements_repository,
        private readonly TextTemplatesRepository $text_templates_repository
    ) {
    }

    /** @param string[] $page_candidates */
    public function loadLatestPayloadForPageCandidates(array $page_candidates): array
    {
        $fetch      = $this->batches_repository->getLatest();
        $fetched_at = is_array($fetch) ? ( $fetch['fetched_at'] ?? null ) : null;
        $pages      = $this->pages_repository->findByUrls($this->buildPageLookupValues($page_candidates));

        if (array() === $pages) {
            return $this->emptyPayload($fetched_at);
        }

        $pages_by_id = array();
        foreach ($pages as $page) {
            if (! isset($page['id']) || ! isset($page['page_url']) || ! is_string($page['page_url'])) {
                continue;
            }
            $page_id = (int) $page['id'];
            if ($page_id <= 0) {
                continue;
            }
            $pages_by_id[ $page_id ] = $page['page_url'];
        }

        if (array() === $pages_by_id) {
            return $this->emptyPayload($fetched_at);
        }

        $content_items_by_page_id = array();
        $configuration_refs       = array();
        foreach ($this->content_items_repository->findDisclosedByPageIds(array_keys($pages_by_id)) as $content_item) {
            $page_id           = isset($content_item['page_id']) ? (int) $content_item['page_id'] : 0;
            $configuration_ref = $this->configurationRefFromContentItem($content_item);
            if (! isset($pages_by_id[ $page_id ]) || '' === $configuration_ref) {
                continue;
            }
            $content_items_by_page_id[ $page_id ][]   = $content_item;
            $configuration_refs[ $configuration_ref ] = true;
        }

        if (array() === $content_items_by_page_id) {
            return $this->payload($fetch, array());
        }

        $resolved_configurations = $this->resolveConfigurations(array_keys($configuration_refs));
        $entries                 = array();
        foreach ($pages_by_id as $page_id => $page_url) {
            foreach ($content_items_by_page_id[ $page_id ] ?? array() as $content_item) {
                $configuration_ref      = $this->configurationRefFromContentItem($content_item);
                $resolved_configuration = $resolved_configurations[ $configuration_ref ] ?? null;
                if (! is_array($resolved_configuration)) {
                    continue;
                }

                $entries[] = $this->buildEntry($page_url, $content_item, $resolved_configuration);
            }
        }

        return $this->payload($fetch, $entries);
    }

    private function buildEntry(string $page_url, array $content_item, array $resolved_configuration): array
    {
        return array(
            'contentId'      => isset($content_item['content_id']) ? (string) $content_item['content_id'] : '',
            'page'           => $page_url,
            'contentType'    => isset($content_item['content_type']) ? (string) $content_item['content_type'] : '',
            'contentHtmlTag' => isset($content_item['content_html_tag']) ? (string) $content_item['content_html_tag'] : '',
            'disclosureType' => isset($content_item['disclosure_type']) ? (string) $content_item['disclosure_type'] : '',
            'placements'     => $resolved_configuration['placements'],
            'byLanguage'     => $resolved_configuration['byLanguage'],
        );
    }

    /** @param string[] $page_candidates @return string[] */
    private function buildPageLookupValues(array $page_candidates): array
    {
        $lookup_values = array();
        foreach ($page_candidates as $page_candidate) {
            if (! is_string($page_candidate) || '' === trim($page_candidate)) {
                continue;
            }
            $normalized_candidate                   = trim($page_candidate);
            $lookup_values[ $normalized_candidate ] = true;
            if ('/' !== $normalized_candidate) {
                $lookup_values[ trailingslashit($normalized_candidate) ] = true;
            }
        }
        return array_keys($lookup_values);
    }

    /** @param string[] $configuration_refs */
    private function resolveConfigurations(array $configuration_refs): array
    {
        $configurations = array();
        $placement_refs = array();
        $template_refs  = array();

        foreach ($this->indexRowsByRef($this->configurations_repository->findByRefs($configuration_refs)) as $configuration_ref => $configuration) {
            $configuration_placement_refs         = $this->decodeStringArray($configuration['placement_refs'] ?? '[]');
            $configuration_slots                  = $this->decodeAssoc($configuration['slots'] ?? '{}');
            $configurations[ $configuration_ref ] = array(
                'placementRefs' => $configuration_placement_refs,
                'slots'         => $configuration_slots,
            );
            foreach ($configuration_placement_refs as $placement_ref) {
                $placement_refs[ $placement_ref ] = true;
            }
            foreach ($this->extractTemplateRefs($configuration_slots) as $template_ref) {
                $template_refs[ $template_ref ] = true;
            }
        }

        $placements_by_ref     = $this->indexRowsByRef($this->placements_repository->findByRefs(array_keys($placement_refs)));
        $template_texts_by_ref = array();
        foreach ($this->indexRowsByRef($this->text_templates_repository->findByRefs(array_keys($template_refs))) as $template_ref => $template) {
            $template_texts_by_ref[ $template_ref ] = $this->decodeAssoc($template['texts'] ?? '{}');
        }

        $resolved_configurations = array();
        foreach ($configurations as $configuration_ref => $configuration) {
            $resolved_configurations[ $configuration_ref ] = array(
                'placements' => $this->loadPlacements($configuration['placementRefs'], $placements_by_ref),
                'byLanguage' => $this->resolveSlotsByLanguage($configuration['slots'], $template_texts_by_ref),
            );
        }

        return $resolved_configurations;
    }

    private function loadPlacements(array $placement_refs, array $placements_by_ref): array
    {
        $placements = array();
        foreach ($placement_refs as $placement_ref) {
            $row = $placements_by_ref[ $placement_ref ] ?? null;
            if (! is_array($row)) {
                continue;
            }
            $raw_params_json = isset($row['raw_params']) ? (string) $row['raw_params'] : '{}';
            $placements[]    = array(
                'placementName'    => isset($row['name']) ? (string) $row['name'] : '',
                'schemaName'       => isset($row['schema_name']) ? (string) $row['schema_name'] : '',
                'schemaVersion'    => isset($row['schema_version']) ? (string) $row['schema_version'] : '',
                'params'           => $this->decodeAssoc($raw_params_json),
                'emptyObjectPaths' => $this->findEmptyObjectPaths($raw_params_json),
                'slotBindings'     => $this->decodeAssoc($row['slot_bindings'] ?? '{}'),
                'renderScript'     => array(
                    'fileName' => isset($row['render_script_file_name']) ? trim((string) $row['render_script_file_name']) : '',
                    'version'  => isset($row['render_script_version']) ? trim((string) $row['render_script_version']) : '',
                ),
            );
        }
        return $placements;
    }

    /** Resolve configuration slot unions into the legacy {language:{slots:{...}}} renderer-input shape. */
    private function resolveSlotsByLanguage(array $slots, array $template_texts_by_ref): array
    {
        $resolved = array();

        foreach ($slots as $slot_key => $by_language) {
            if (! is_string($slot_key) || ! is_array($by_language)) {
                continue;
            }

            foreach ($by_language as $bcp47 => $slot_value) {
                if (! is_string($bcp47) || ! is_array($slot_value)) {
                    continue;
                }

                $text = null;
                if (array_key_exists('custom', $slot_value) && is_string($slot_value['custom'])) {
                    $text = $slot_value['custom'];
                } elseif (isset($slot_value['templateRef']) && is_string($slot_value['templateRef'])) {
                    $template_ref = trim($slot_value['templateRef']);
                    if ('' !== $template_ref) {
                        $candidate = $template_texts_by_ref[ $template_ref ][ $bcp47 ] ?? null;
                        $text      = is_string($candidate) ? $candidate : null;
                    }
                }

                if (is_string($text)) {
                    $resolved[ $bcp47 ]['slots'][ $slot_key ] = $text;
                }
            }
        }

        ksort($resolved);
        return $resolved;
    }

    private function configurationRefFromContentItem(array $content_item): string
    {
        return isset($content_item['configuration_ref']) && is_string($content_item['configuration_ref'])
            ? trim($content_item['configuration_ref'])
            : '';
    }

    private function indexRowsByRef(array $rows): array
    {
        $rows_by_ref = array();
        foreach ($rows as $row) {
            $ref = isset($row['ref']) && is_string($row['ref']) ? trim($row['ref']) : '';
            if ('' !== $ref) {
                $rows_by_ref[ $ref ] = $row;
            }
        }
        return $rows_by_ref;
    }

    private function extractTemplateRefs(array $slots): array
    {
        $template_refs = array();
        foreach ($slots as $by_language) {
            if (! is_array($by_language)) {
                continue;
            }
            foreach ($by_language as $slot_value) {
                if (! is_array($slot_value) || ! isset($slot_value['templateRef']) || ! is_string($slot_value['templateRef'])) {
                    continue;
                }
                $template_ref = trim($slot_value['templateRef']);
                if ('' !== $template_ref) {
                    $template_refs[ $template_ref ] = true;
                }
            }
        }
        return array_keys($template_refs);
    }

    private function payload(mixed $fetch, array $entries): array
    {
        return array(
            'disclosure_fetch_id'    => is_array($fetch) && isset($fetch['id']) ? (int) $fetch['id'] : null,
            'received_content_count' => count($entries),
            'entries'                => $entries,
            'fetched_at'             => is_array($fetch) ? ( $fetch['fetched_at'] ?? null ) : null,
        );
    }

    private function decodeStringArray(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : array();
    }

    private function decodeAssoc(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;
        return is_array($decoded) ? $decoded : array();
    }

    /** @return list<string> */
    private function findEmptyObjectPaths(string $json): array
    {
        if ('' === trim($json)) {
            return array();
        }
        $decoded = json_decode($json);
        if (! ( $decoded instanceof \stdClass )) {
            return array();
        }
        $paths = array();
        $this->collectEmptyObjectPaths($decoded, '', $paths);
        return $paths;
    }

    /** @param list<string> $paths */
    private function collectEmptyObjectPaths(\stdClass $node, string $prefix, array &$paths): void
    {
        $properties = get_object_vars($node);
        if (array() === $properties) {
            if ('' !== $prefix) {
                $paths[] = $prefix;
            }
            return;
        }
        foreach ($properties as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
            if ($value instanceof \stdClass) {
                $this->collectEmptyObjectPaths($value, $path, $paths);
            } elseif (is_array($value)) {
                foreach ($value as $index => $item) {
                    if ($item instanceof \stdClass) {
                        $this->collectEmptyObjectPaths($item, $path . '.' . $index, $paths);
                    }
                }
            }
        }
    }

    private function emptyPayload(?string $fetched_at): array
    {
        return array(
            'disclosure_fetch_id'    => null,
            'received_content_count' => 0,
            'entries'                => array(),
            'fetched_at'             => $fetched_at,
        );
    }
}
