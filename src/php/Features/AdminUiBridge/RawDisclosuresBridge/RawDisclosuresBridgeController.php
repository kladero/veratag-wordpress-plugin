<?php

namespace VeraTag\Features\AdminUiBridge\RawDisclosuresBridge;

use VeraTag\Persistence\Disclosures\Repositories\ConfigurationsRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemIssuesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository;
use VeraTag\Persistence\Disclosures\Repositories\PagesRepository;
use VeraTag\Persistence\Disclosures\Repositories\PlacementsRepository;
use VeraTag\Persistence\Disclosures\Repositories\TextTemplatesRepository;
use VeraTag\Settings\Services\GlobalSettings;
use WP_REST_Request;
use WP_REST_Response;

/** Admin view of the local current ref-keyed state, including incremental pushes not yet followed by a pull. */
final class RawDisclosuresBridgeController
{
    public function __construct(
        private readonly ConfigurationsRepository $configurations_repository,
        private readonly PlacementsRepository $placements_repository,
        private readonly TextTemplatesRepository $text_templates_repository,
        private readonly PagesRepository $pages_repository,
        private readonly ContentItemsRepository $content_items_repository,
        private readonly ContentItemIssuesRepository $content_item_issues_repository,
        private readonly GlobalSettings $settings
    ) {
    }

    public function getRawDisclosures(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        return new WP_REST_Response(
            array(
                'channelDetails' => array(
                    'displayName'   => $this->settings->getChannelDisplayName(),
                    'paths'         => $this->settings->getChannelPaths()->toArray(),
                    'pushApiStatus' => null,
                ),
                'configurations' => $this->configurations(),
                'placements'     => $this->placements(),
                'textTemplates'  => $this->textTemplates(),
                'pages'          => $this->pages(),
            ),
            200
        );
    }

    private function configurations(): array
    {
        $out = array();
        foreach ($this->configurations_repository->findAll() as $row) {
            $ref = (string) ( $row['ref'] ?? '' );
            if ('' === $ref) {
                continue;
            }
            $out[ $ref ] = array(
                'name'          => (string) ( $row['name'] ?? '' ),
                'issues'        => $this->decode($row['issues'] ?? '[]'),
                'placementRefs' => $this->decode($row['placement_refs'] ?? '[]'),
                'slots'         => $this->decode($row['slots'] ?? '{}'),
            );
        }
        return $out;
    }
    private function placements(): array
    {
        $out = array();
        foreach ($this->placements_repository->findAll() as $row) {
            $ref = (string) ( $row['ref'] ?? '' );
            if ('' === $ref) {
                continue;
            }
            $out[ $ref ] = array(
                'name'         => (string) ( $row['name'] ?? '' ),
                'issues'       => $this->decode($row['issues'] ?? '[]'),
                'schema'       => array(
                    'schemaName'    => (string) ( $row['schema_name'] ?? '' ),
                    'schemaVersion' => (string) ( $row['schema_version'] ?? '' ),
                ),
                'renderScript' => array(
                    'fileName'             => (string) ( $row['render_script_file_name'] ?? '' ),
                    'scriptReleaseVersion' => (string) ( $row['render_script_version'] ?? '' ),
                ),
                'rawParams'    => $this->decode($row['raw_params'] ?? '{}'),
                'slotBindings' => $this->decode($row['slot_bindings'] ?? '{}'),
            );
        }
        return $out;
    }
    private function textTemplates(): array
    {
        $out = array();
        foreach ($this->text_templates_repository->findAll() as $row) {
            $ref = (string) ( $row['ref'] ?? '' );
            if ('' === $ref) {
                continue;
            }
            $out[ $ref ] = array(
                'name'   => (string) ( $row['name'] ?? '' ),
                'issues' => $this->decode($row['issues'] ?? '[]'),
                'texts'  => $this->decode($row['texts'] ?? '{}'),
            );
        }
        return $out;
    }
    private function pages(): array
    {
        $out = array();
        foreach ($this->pages_repository->findAll() as $page) {
            if (! isset($page['id'])) {
                continue;
            }
            $content_rows   = $this->content_items_repository->findByPageId((int) $page['id']);
            $ids            = array_map(static fn (array $row): int => (int) $row['id'], $content_rows);
            $issues_by_item = array();
            foreach ($this->content_item_issues_repository->findByContentItemIds($ids) as $issue) {
                $item_id                      = (int) $issue['content_item_id'];
                $issues_by_item[ $item_id ][] = array(
                    'id'         => (int) $issue['id'],
                    'reason'     => (string) $issue['reason'],
                    'impact'     => (string) $issue['impact'],
                    'message'    => (string) $issue['message'],
                    'detectedAt' => (string) $issue['detected_at'],
                );
            }
            $content = array();
            foreach ($content_rows as $row) {
                $config_ref      = isset($row['configuration_ref']) && is_string($row['configuration_ref']) && '' !== trim($row['configuration_ref']) ? (string) $row['configuration_ref'] : null;
                $disclosure_type = isset($row['disclosure_type']) && is_string($row['disclosure_type']) && '' !== trim($row['disclosure_type']) ? (string) $row['disclosure_type'] : null;
                $content[]       = array(
                    'contentId'         => (string) ( $row['content_id'] ?? '' ),
                    'contentType'       => (string) ( $row['content_type'] ?? '' ),
                    'contentHtmlTag'    => (string) ( $row['content_html_tag'] ?? '' ),
                    'contentDisplay'    => $this->decodeMixed($row['content_display'] ?? 'null'),
                    'disclosureType'    => $disclosure_type,
                    'configurationRef'  => $config_ref,
                    'shouldBeDisclosed' => null === $config_ref ? null : '1' === (string) ( $row['is_disclosed'] ?? '0' ),
                    'issues'            => $issues_by_item[ (int) $row['id'] ] ?? array(),
                );
            }
            $out[] = array(
                'pageUrl' => (string) ( $page['page_url'] ?? '' ),
                'content' => $content,
            );
        }
        return $out;
    }
    private function decode(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;
        return is_array($decoded) ? $decoded : array();
    }
    private function decodeMixed(mixed $json): mixed
    {
        if (! is_string($json)) {
            return $json;
        } $decoded = json_decode($json, true);
        return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
    }
}
