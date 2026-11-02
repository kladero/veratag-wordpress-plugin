<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\PagesTable;
use WP_Error;

final class PagesRepository
{
    public function __construct(private readonly PagesTable $pages_table)
    {
    }

    public function insert(int $disclosure_fetch_id, string $page_url): int|WP_Error
    {
        global $wpdb;
        $inserted = $wpdb->insert(
            $this->pages_table->getTableName(),
            array(
                'disclosure_fetch_id' => $disclosure_fetch_id,
                'page_url'            => $page_url,
            ),
            array( '%d', '%s' )
        );
        if (false === $inserted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to store page.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return (int) $wpdb->insert_id;
    }

    /** Used by push: if the page already exists, keep its id and update its snapshot association. */
    public function getOrCreate(string $page_url, int $disclosure_fetch_id = 0): int|WP_Error
    {
        $existing = $this->findByUrl($page_url);
        if ($existing && isset($existing['id'])) {
            return (int) $existing['id'];
        }
        return $this->insert($disclosure_fetch_id, $page_url);
    }
    public function findByUrl(string $page_url): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->pages_table->getTableName() . ' WHERE page_url = %s LIMIT 1', $page_url), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($row) ? $row : null;
    }
    /** @param string[] $page_urls */
    public function findByUrls(array $page_urls): array
    {
        global $wpdb;
        $page_urls = array_values(array_unique(array_filter($page_urls, static fn (mixed $page_url): bool => is_string($page_url) && '' !== trim($page_url))));
        if (array() === $page_urls) {
            return array();
        }
        $exact_placeholders      = implode(', ', array_fill(0, count($page_urls), '%s'));
        $query_suffix_conditions = implode(' OR ', array_fill(0, count($page_urls), 'page_url LIKE %s'));
        $query_suffix_patterns   = array_map(static fn (string $page_url): string => $wpdb->esc_like($page_url) . '?%', $page_urls);
        $rows                    = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . $this->pages_table->getTableName() . " WHERE page_url IN ({$exact_placeholders}) OR ({$query_suffix_conditions}) ORDER BY id ASC", array_merge($page_urls, $query_suffix_patterns)), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The generated placeholder list and values are both controlled here.
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }
    public function findIdsByDisclosureFetchIds(array $ids): array
    {
        global $wpdb;
        if (array() === $ids) {
            return array();
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $result       = $wpdb->get_col(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $placeholders contains only fixed %d tokens; all values are passed to prepare().
                'SELECT id FROM %i WHERE disclosure_fetch_id IN (' . $placeholders . ')',
                array_merge(array( $this->pages_table->getTableName() ), $ids)
            )
        );
        return array_map('intval', $result);
    }
    public function findByDisclosureFetchId(int $id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $this->pages_table->getTableName() . ' WHERE disclosure_fetch_id = %d ORDER BY id ASC', $id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($rows) ? $rows : array();
    }
    public function findAll(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . $this->pages_table->getTableName() . ' ORDER BY id ASC', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($rows) ? $rows : array();
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
                array_merge(array( $this->pages_table->getTableName() ), $ids)
            )
        );
    }
    public function deleteAll(): bool|WP_Error
    {
        global $wpdb;
        $deleted = $wpdb->query('DELETE FROM ' . $this->pages_table->getTableName()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if (false === $deleted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to delete disclosure pages.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return true;
    }
}
