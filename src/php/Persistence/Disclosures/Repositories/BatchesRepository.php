<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\BatchesTable;
use WP_Error;

final class BatchesRepository
{
    public function __construct(
        private readonly BatchesTable $batches_table
    ) {
    }

    public function insert(string $fetched_at, int $received_content_count, ?string $payload_hash): int|WP_Error
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->batches_table->getTableName(),
            array(
                'fetched_at'             => $fetched_at,
                'received_content_count' => $received_content_count,
                'payload_hash'           => $payload_hash,
            ),
            array(
                '%s',
                '%d',
                '%s',
            )
        );

        if (false === $inserted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to store disclosure batch.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }

        return (int) $wpdb->insert_id;
    }

    public function getLatest(): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT 1', $this->batches_table->getTableName()),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return is_array($row) ? $row : null;
    }

    public function findAllIds(): array
    {
        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare('SELECT id FROM %i', $this->batches_table->getTableName())
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array_map('intval', $ids);
    }

    public function deleteAll(): bool|WP_Error
    {
        global $wpdb;
        $deleted = $wpdb->query('DELETE FROM ' . $this->batches_table->getTableName()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if (false === $deleted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to delete disclosure batches.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return true;
    }
}
