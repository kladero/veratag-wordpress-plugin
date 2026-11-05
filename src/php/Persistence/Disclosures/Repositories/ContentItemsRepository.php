<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\ContentItemsTable;
use WP_Error;

final class ContentItemsRepository
{
    public function __construct(private readonly ContentItemsTable $content_items_table)
    {
    }

    public function insert(int $page_id, string $content_id, string $content_type, string $content_html_tag, string $content_display_json, bool $is_disclosed, ?string $disclosure_type, ?string $configuration_ref): int|WP_Error
    {
        global $wpdb;
        $inserted = $wpdb->insert(
            $this->content_items_table->getTableName(),
            array(
                'page_id'           => $page_id,
                'content_id'        => $content_id,
                'content_type'      => $content_type,
                'content_html_tag'  => $content_html_tag,
                'content_display'   => $content_display_json,
                'is_disclosed'      => $is_disclosed ? 1 : 0,
                'disclosure_type'   => $disclosure_type,
                'configuration_ref' => $configuration_ref,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
        if (false === $inserted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to store content item.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return (int) $wpdb->insert_id;
    }

    public function upsert(int $page_id, string $content_id, string $content_type, string $content_html_tag, string $content_display_json, bool $is_disclosed, ?string $disclosure_type, ?string $configuration_ref): int|WP_Error
    {
        global $wpdb;
        $existing = $this->findByPageAndContentId($page_id, $content_id);
        if (null === $existing) {
            return $this->insert($page_id, $content_id, $content_type, $content_html_tag, $content_display_json, $is_disclosed, $disclosure_type, $configuration_ref);
        }
        $id      = (int) $existing['id'];
        $updated = $wpdb->update(
            $this->content_items_table->getTableName(),
            array(
                'content_type'      => $content_type,
                'content_html_tag'  => $content_html_tag,
                'content_display'   => $content_display_json,
                'is_disclosed'      => $is_disclosed ? 1 : 0,
                'disclosure_type'   => $disclosure_type,
                'configuration_ref' => $configuration_ref,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );
        if (false === $updated) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to update content item.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return $id;
    }
    public function findByPageAndContentId(int $page_id, string $content_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE page_id = %d AND content_id = %s LIMIT 1',
                $this->content_items_table->getTableName(),
                $page_id,
                $content_id
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }
    public function findByPageUrlAndContentId(string $page_url, string $content_id): ?array
    {
        global $wpdb;
        $pages_table = $wpdb->prefix . 'veratag_pages';
        $row         = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ci.* FROM %i ci INNER JOIN %i p ON p.id = ci.page_id WHERE p.page_url = %s AND ci.content_id = %s LIMIT 1',
                $this->content_items_table->getTableName(),
                $pages_table,
                $page_url,
                $content_id
            ),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($row) ? $row : null;
    }
    public function findIdsByPageIds(array $page_ids): array
    {
        global $wpdb;
        if (array() === $page_ids) {
            return array();
        }
        $placeholders = implode(', ', array_fill(0, count($page_ids), '%d'));
        $ids          = $wpdb->get_col(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $placeholders contains only fixed %d tokens; all values are passed to prepare().
                'SELECT id FROM %i WHERE page_id IN (' . $placeholders . ')',
                array_merge(array( $this->content_items_table->getTableName() ), $page_ids)
            )
        );
        return array_map('intval', $ids);
    }
    public function findByPageId(int $page_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i WHERE page_id = %d ORDER BY id ASC', $this->content_items_table->getTableName(), $page_id),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }
    /** @param int[] $page_ids */
    public function findDisclosedByPageIds(array $page_ids): array
    {
        global $wpdb;
        $page_ids = array_values(array_unique(array_filter(array_map('intval', $page_ids), static fn (int $page_id): bool => $page_id > 0)));
        if (array() === $page_ids) {
            return array();
        }
        $placeholders = implode(', ', array_fill(0, count($page_ids), '%d'));
        $rows         = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Generated list contains fixed %d tokens; all arguments are passed to prepare().
                'SELECT * FROM %i WHERE page_id IN (' . $placeholders . ") AND is_disclosed = 1 AND configuration_ref IS NOT NULL AND configuration_ref <> '' ORDER BY page_id ASC, id ASC",
                array_merge(array( $this->content_items_table->getTableName() ), $page_ids)
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }
    public function findAll(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY id ASC', $this->content_items_table->getTableName()),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }
    public function findDistinctConfigurationRefs(): array
    {
        global $wpdb;
        $refs = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT configuration_ref FROM %i WHERE configuration_ref IS NOT NULL AND configuration_ref <> ''", $this->content_items_table->getTableName())
        );
        return array_values(array_filter(array_map('strval', $refs)));
    }
    public function countByConfigurationRef(string $ref): int
    {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM %i WHERE configuration_ref = %s', $this->content_items_table->getTableName(), $ref)
        );
        return null === $count ? 0 : (int) $count;
    }
    /** Every content item row currently pointing at {@code ref} — for fanning a configuration-tier issue change out to each one's aggregated issues. */
    public function findByConfigurationRef(string $ref): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i WHERE configuration_ref = %s', $this->content_items_table->getTableName(), $ref),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }
    public function countAll(): int
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $this->content_items_table->getTableName()));
        return null === $count ? 0 : (int) $count;
    }

    /**
     * One filtered/sorted/paginated page of content items, each annotated
     * with its own page_url (joined from `pages`) and, for sort/filter
     * purposes only, enough info to know whether it has any issues and at
     * what impact (joined from `content_item_issues` via a correlated
     * EXISTS, since a content item's own `issues[]` is otherwise only
     * available per-row via {@see ContentItemIssuesRepository::findByContentItemIds}).
     *
     * `$sort_column` MUST be one of the keys of {@see self::SORTABLE_COLUMNS}
     * — never build the ORDER BY clause from an unvalidated client value.
     *
     * @param string[] $content_types
     * @param string[] $disclosure_types
     * @param string[] $page_urls
     * @param string[] $issue_impacts one or more of 'action_required', 'action_recommended', 'none'
     */
    public function findPage(
        string $search_text,
        array $content_types,
        array $disclosure_types,
        array $page_urls,
        ?bool $is_disclosed,
        array $issue_impacts,
        string $sort_column,
        string $sort_direction,
        int $limit,
        int $offset
    ): array {
        global $wpdb;
        [ $where_sql, $where_params ] = $this->buildWhereClause($search_text, $content_types, $disclosure_types, $page_urls, $is_disclosed, $issue_impacts);
        $order_by_sql                 = $this->resolveOrderByClause($sort_column, $sort_direction);
        $content_items_table          = $this->content_items_table->getTableName();
        $pages_table                  = $this->pagesTableName();
        $sql    = 'SELECT ci.*, p.page_url AS page_url FROM %i ci INNER JOIN %i p ON p.id = ci.page_id WHERE ' . $where_sql . ' ORDER BY ' . $order_by_sql . ' LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WHERE and ORDER BY contain only internal placeholder templates and allowlisted fragments.
        $params = array_merge(array( $content_items_table, $pages_table ), $where_params, array( $limit, $offset ));
        $rows   = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($rows) ? $rows : array();
    }

    /** @param string[] $content_types @param string[] $disclosure_types @param string[] $page_urls @param string[] $issue_impacts */
    public function countMatching(
        string $search_text,
        array $content_types,
        array $disclosure_types,
        array $page_urls,
        ?bool $is_disclosed,
        array $issue_impacts
    ): int {
        global $wpdb;
        [ $where_sql, $where_params ] = $this->buildWhereClause($search_text, $content_types, $disclosure_types, $page_urls, $is_disclosed, $issue_impacts);
        $content_items_table          = $this->content_items_table->getTableName();
        $pages_table                  = $this->pagesTableName();

        $sql   = 'SELECT COUNT(*) FROM %i ci INNER JOIN %i p ON p.id = ci.page_id WHERE ' . $where_sql; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WHERE contains only internal placeholder templates.
        $count = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The query template contains only fixed identifiers and internally generated placeholder fragments.
            $wpdb->prepare($sql, array_merge(array( $content_items_table, $pages_table ), $where_params))
        );
        return null === $count ? 0 : (int) $count;
    }

    /**
     * Distinct page URLs currently referenced by any content item, each with
     * its content-item count — for the page-URL filter's option list.
     *
     * @return array<int, array{page_url: string, content_count: int}>
     */
    public function findDistinctPageUrlsWithCounts(): array
    {
        global $wpdb;
        $content_items_table = $this->content_items_table->getTableName();
        $pages_table         = $this->pagesTableName();
        $rows                = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT p.page_url AS page_url, COUNT(*) AS content_count FROM %i ci INNER JOIN %i p ON p.id = ci.page_id GROUP BY p.page_url ORDER BY p.page_url ASC',
                $content_items_table,
                $pages_table
            ),
            ARRAY_A
        );
        if (! is_array($rows)) {
            return array();
        }
        return array_map(
            static fn (array $row): array => array(
                'page_url'      => (string) $row['page_url'],
                'content_count' => (int) $row['content_count'],
            ),
            $rows
        );
    }

    private const SORTABLE_COLUMNS = array(
        'ADDED_ORDER'     => 'ci.id',
        'CONTENT_TYPE'    => 'ci.content_type',
        'DISCLOSURE_TYPE' => 'ci.disclosure_type',
        'PAGE_URL'        => 'p.page_url',
    );

    private function resolveOrderByClause(string $sort_column, string $sort_direction): string
    {
        $column_sql    = self::SORTABLE_COLUMNS[ $sort_column ] ?? self::SORTABLE_COLUMNS['ADDED_ORDER'];
        $direction_sql = 'asc' === strtolower($sort_direction) ? 'ASC' : 'DESC';
        return "{$column_sql} {$direction_sql}, ci.id ASC";
    }

    /**
     * @param string[] $content_types
     * @param string[] $disclosure_types
     * @param string[] $page_urls
     * @param string[] $issue_impacts
     * @return array{0: string, 1: array<int, string|int>}
     */
    private function buildWhereClause(
        string $search_text,
        array $content_types,
        array $disclosure_types,
        array $page_urls,
        ?bool $is_disclosed,
        array $issue_impacts
    ): array {
        $conditions = array( '1 = 1' );
        $params     = array();

        $trimmed_search_text = trim($search_text);
        if ('' !== $trimmed_search_text) {
            $like_pattern = '%' . $this->escapeLike($trimmed_search_text) . '%';
            // Matches the content's own id, and its displayed text/caption
            // (stored in the content_display JSON column — see the plugin
            // admin UI's contentDisplaySummary(), which reads the same two
            // keys to decide what to show per content item).
            $conditions[] = '(ci.content_id LIKE %s OR JSON_UNQUOTE(JSON_EXTRACT(ci.content_display, \'$.text\')) LIKE %s OR JSON_UNQUOTE(JSON_EXTRACT(ci.content_display, \'$.caption\')) LIKE %s)';
            array_push($params, $like_pattern, $like_pattern, $like_pattern);
        }

        $content_types = array_values(array_filter($content_types, static fn (string $v): bool => '' !== trim($v)));
        if (array() !== $content_types) {
            $conditions[] = 'ci.content_type IN (' . $this->placeholders(count($content_types)) . ')';
            array_push($params, ...$content_types);
        }

        $disclosure_types = array_values(array_filter($disclosure_types, static fn (string $v): bool => '' !== trim($v)));
        if (array() !== $disclosure_types) {
            $conditions[] = 'ci.disclosure_type IN (' . $this->placeholders(count($disclosure_types)) . ')';
            array_push($params, ...$disclosure_types);
        }

        $page_urls = array_values(array_filter($page_urls, static fn (string $v): bool => '' !== trim($v)));
        if (array() !== $page_urls) {
            $conditions[] = 'p.page_url IN (' . $this->placeholders(count($page_urls)) . ')';
            array_push($params, ...$page_urls);
        }

        if (null !== $is_disclosed) {
            $conditions[] = 'ci.is_disclosed = %d';
            $params[]     = $is_disclosed ? 1 : 0;
        }

        $issue_impacts = array_values(array_filter($issue_impacts, static fn (string $v): bool => '' !== trim($v)));
        if (array() !== $issue_impacts) {
            $wants_none       = in_array('none', $issue_impacts, true);
            $wanted_impacts   = array_values(array_filter($issue_impacts, static fn (string $v): bool => 'none' !== $v));
            $issue_conditions = array();
            if (array() !== $wanted_impacts) {
                $issue_conditions[] = 'EXISTS (SELECT 1 FROM %i cii WHERE cii.content_item_id = ci.id AND cii.impact IN (' . $this->placeholders(count($wanted_impacts)) . '))';
                array_push($params, $this->contentItemIssuesTableName(), ...$wanted_impacts);
            }
            if ($wants_none) {
                $issue_conditions[] = 'NOT EXISTS (SELECT 1 FROM %i cii WHERE cii.content_item_id = ci.id)';
                $params[]           = $this->contentItemIssuesTableName();
            }
            if (array() !== $issue_conditions) {
                $conditions[] = '(' . implode(' OR ', $issue_conditions) . ')';
            }
        }

        return array( implode(' AND ', $conditions), $params );
    }

    private function placeholders(int $count): string
    {
        return implode(', ', array_fill(0, $count, '%s'));
    }

    private function escapeLike(string $value): string
    {
        global $wpdb;
        return $wpdb->esc_like($value);
    }

    private function pagesTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'veratag_pages';
    }

    private function contentItemIssuesTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'veratag_content_item_issues';
    }
    public function deleteByIds(array $ids): void
    {
        global $wpdb;
        if (array() === $ids) {
            return;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $placeholders contains only fixed %d tokens; all values are passed to prepare().
                'DELETE FROM %i WHERE id IN (' . $placeholders . ')',
                array_merge(array( $this->content_items_table->getTableName() ), $ids)
            )
        );
    }
    public function deleteById(int $id): void
    {
        global $wpdb;
        $wpdb->delete($this->content_items_table->getTableName(), array( 'id' => $id ), array( '%d' ));
    }
    public function deleteAll(): bool|WP_Error
    {
        global $wpdb;
        $deleted = $wpdb->query($wpdb->prepare('DELETE FROM %i', $this->content_items_table->getTableName()));
        if (false === $deleted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to delete content items.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return true;
    }
}
