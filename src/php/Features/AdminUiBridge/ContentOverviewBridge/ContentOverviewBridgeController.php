<?php

namespace VeraTag\Features\AdminUiBridge\ContentOverviewBridge;

use VeraTag\Persistence\Disclosures\Repositories\ConfigurationsRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemIssuesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Search/filter/sort/paginate over the locally stored content items —
 * mirrors the shape of the backend's own content overview query
 * (`CmsChannelContentManagementController`/`WebContentPageResponseDto`),
 * against this plugin's much smaller local schema. Read-only: unlike the
 * backend endpoint it mirrors, there is no write path here (no delete, no
 * assign-configuration) — this plugin only ever mirrors backend-pushed
 * state, it never originates changes to it.
 */
final class ContentOverviewBridgeController
{
    private const DEFAULT_PAGE_SIZE = 15;
    private const MAX_PAGE_SIZE     = 100;

    public function __construct(
        private readonly ContentItemsRepository $content_items_repository,
        private readonly ContentItemIssuesRepository $content_item_issues_repository,
        private readonly ConfigurationsRepository $configurations_repository
    ) {
    }

    public function getContentPage(WP_REST_Request $request): WP_REST_Response
    {
        $search_text      = (string) $request->get_param('searchText');
        $content_types    = $this->stringListParam($request, 'contentType');
        $disclosure_types = $this->stringListParam($request, 'disclosureType');
        $page_urls        = $this->stringListParam($request, 'pageUrl');
        $is_disclosed     = $this->nullableBoolParam($request, 'isDisclosed');
        $issue_impacts    = $this->stringListParam($request, 'issueImpact');
        $sort_by          = (string) ( $request->get_param('sortBy') ?? 'ADDED_ORDER' );
        $sort_dir         = (string) ( $request->get_param('sortDir') ?? 'desc' );
        $page_number      = max(1, (int) ( $request->get_param('pageNumber') ?? 1 ));
        $page_size        = $this->clampPageSize((int) ( $request->get_param('pageSize') ?? self::DEFAULT_PAGE_SIZE ));
        $offset           = ( $page_number - 1 ) * $page_size;

        $total_elements = $this->content_items_repository->countMatching(
            $search_text,
            $content_types,
            $disclosure_types,
            $page_urls,
            $is_disclosed,
            $issue_impacts
        );
        $rows           = $this->content_items_repository->findPage(
            $search_text,
            $content_types,
            $disclosure_types,
            $page_urls,
            $is_disclosed,
            $issue_impacts,
            $sort_by,
            $sort_dir,
            $page_size,
            $offset
        );

        $content     = $this->toContentItemResponses($rows);
        $total_pages = 0 === $page_size ? 0 : (int) ceil($total_elements / $page_size);

        return new WP_REST_Response(
            array(
                'content'          => $content,
                'totalPages'       => $total_pages,
                'totalElements'    => $total_elements,
                'pageNumber'       => $page_number - 1,
                'pageSize'         => $page_size,
                'first'            => 1 === $page_number,
                'last'             => $page_number >= $total_pages,
                'numberOfElements' => count($content),
                'empty'            => array() === $content,
            ),
            200
        );
    }

    public function getPageUrls(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        $page_urls = array_map(
            static fn (array $row): array => array(
                'pageUrl'      => $row['page_url'],
                'contentCount' => $row['content_count'],
            ),
            $this->content_items_repository->findDistinctPageUrlsWithCounts()
        );
        return new WP_REST_Response(array( 'pageUrls' => $page_urls ), 200);
    }

    public function canViewContentOverview(): bool
    {
        return current_user_can('manage_options');
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function toContentItemResponses(array $rows): array
    {
        $ids            = array_map(static fn (array $row): int => (int) $row['id'], $rows);
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

        return array_map(
            function (array $row) use ($issues_by_item): array {
                $config_ref         = isset($row['configuration_ref']) && is_string($row['configuration_ref']) && '' !== trim($row['configuration_ref'])
                    ? (string) $row['configuration_ref']
                    : null;
                $disclosure_type    = isset($row['disclosure_type']) && is_string($row['disclosure_type']) && '' !== trim($row['disclosure_type'])
                    ? (string) $row['disclosure_type']
                    : null;
                $configuration_name = null === $config_ref ? null : $this->resolveConfigurationName($config_ref);
                $is_disclosed       = '1' === (string) ( $row['is_disclosed'] ?? '0' );

                return array(
                    'id'                => (string) ( $row['content_id'] ?? '' ),
                    'pageUrl'           => (string) ( $row['page_url'] ?? '' ),
                    'contentType'       => (string) ( $row['content_type'] ?? '' ),
                    'contentHtmlTag'    => (string) ( $row['content_html_tag'] ?? '' ),
                    'contentDisplay'    => $this->decodeMixed($row['content_display'] ?? 'null'),
                    'disclosureType'    => $disclosure_type,
                    'configurationRef'  => $config_ref,
                    'configurationName' => $configuration_name,
                    'isDisclosed'       => null === $config_ref ? null : $is_disclosed,
                    'issues'            => $issues_by_item[ (int) $row['id'] ] ?? array(),
                );
            },
            $rows
        );
    }

    private function resolveConfigurationName(string $ref): ?string
    {
        $configuration = $this->configurations_repository->findByRef($ref);
        return null === $configuration ? null : (string) ( $configuration['name'] ?? '' );
    }

    private function decodeMixed(mixed $json): mixed
    {
        if (! is_string($json)) {
            return $json;
        }
        $decoded = json_decode($json, true);
        return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
    }

    /** @return string[] */
    private function stringListParam(WP_REST_Request $request, string $key): array
    {
        $value = $request->get_param($key);
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), static fn (string $v): bool => '' !== trim($v)));
        }
        if (is_string($value) && '' !== trim($value)) {
            return array( $value );
        }
        return array();
    }

    private function nullableBoolParam(WP_REST_Request $request, string $key): ?bool
    {
        $value = $request->get_param($key);
        if (null === $value || '' === $value) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower((string) $value);
        if ('true' === $normalized || '1' === $normalized) {
            return true;
        }
        if ('false' === $normalized || '0' === $normalized) {
            return false;
        }
        return null;
    }

    private function clampPageSize(int $page_size): int
    {
        if ($page_size <= 0) {
            return self::DEFAULT_PAGE_SIZE;
        }
        return min($page_size, self::MAX_PAGE_SIZE);
    }
}
