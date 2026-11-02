<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\ContentOverviewTable;
use WP_Error;

final class ContentOverviewRepository
{
    public function __construct(
        private readonly ContentOverviewTable $content_overview_table
    ) {
    }

    public function insert(
        int $disclosure_fetch_id,
        int $registered_content_count,
        int $configured_content_count,
        int $unconfigured_content_count
    ): int|WP_Error {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->content_overview_table->getTableName(),
            array(
                'disclosure_fetch_id'        => $disclosure_fetch_id,
                'registered_content_count'   => $registered_content_count,
                'configured_content_count'   => $configured_content_count,
                'unconfigured_content_count' => $unconfigured_content_count,
            ),
            array(
                '%d',
                '%d',
                '%d',
                '%d',
            )
        );

        if (false === $inserted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to store content overview.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }

        return (int) $wpdb->insert_id;
    }

    public function findByDisclosureFetchId(int $disclosure_fetch_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE disclosure_fetch_id = %d',
                $this->content_overview_table->getTableName(),
                $disclosure_fetch_id
            ),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return is_array($row) ? $row : null;
    }

    public function deleteAll(): bool|WP_Error
    {
        global $wpdb;
        $deleted = $wpdb->query($wpdb->prepare('DELETE FROM %i', $this->content_overview_table->getTableName()));
        if (false === $deleted) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to delete content overview rows.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return true;
    }
}
