<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\ContentItemIssuesTable;
use WP_Error;

final class ContentItemIssuesRepository
{
    public function __construct(
        private readonly ContentItemIssuesTable $content_item_issues_table
    ) {
    }

    public function insert(int $content_item_id, string $reason, string $impact, string $message, string $detected_at): int|WP_Error
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->content_item_issues_table->getTableName(),
            array(
                'content_item_id' => $content_item_id,
                'reason'          => $reason,
                'impact'          => $impact,
                'message'         => $message,
                'detected_at'     => $detected_at,
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if (false === $inserted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to store content item issue.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }

        return (int) $wpdb->insert_id;
    }

    public function findByContentItemIds(array $content_item_ids): array
    {
        global $wpdb;

        if (array() === $content_item_ids) {
            return array();
        }

        $ids = array_map('intval', $content_item_ids);
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $placeholders contains only fixed %d tokens; all values are passed to prepare().
                'SELECT * FROM %i WHERE content_item_id IN (' . $placeholders . ') ORDER BY content_item_id ASC, id ASC',
                array_merge(array( $this->content_item_issues_table->getTableName() ), $ids)
            ),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table name, integer values are parameterized.

        return is_array($rows) ? $rows : array();
    }

    public function deleteByContentItemIds(array $content_item_ids): bool|WP_Error
    {
        global $wpdb;

        if (array() === $content_item_ids) {
            return true;
        }

        $ids = array_map('intval', $content_item_ids);
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $placeholders contains only fixed %d tokens; all values are passed to prepare().
                'DELETE FROM %i WHERE content_item_id IN (' . $placeholders . ')',
                array_merge(array( $this->content_item_issues_table->getTableName() ), $ids)
            )
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table name, integer values are parameterized.
        if (false === $deleted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to delete content item issues.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return true;
    }
}
