<?php

namespace VeraTag\Persistence\PushApi\Repositories;

use VeraTag\Persistence\PushApi\Schema\PushReceivedLogTable;
use WP_Error;

final class PushReceivedLogRepository
{
    public function __construct(private readonly PushReceivedLogTable $table)
    {
    }
    public function insert(string $instruction, ?string $ref_value, bool $applied_successfully, ?string $error_message): int|WP_Error
    {
        global $wpdb;
        $result = $wpdb->insert(
            $this->table->getTableName(),
            array(
                'received_at'          => current_time('mysql', true),
                'instruction'          => $instruction,
                'ref_value'            => $ref_value,
                'applied_successfully' => $applied_successfully ? 1 : 0,
                'error_message'        => $error_message,
            ),
            array( '%s', '%s', '%s', '%d', '%s' )
        );
        if (false === $result) {
            return new WP_Error(
                'veratag_push_log_db_error',
                'Unable to record received push.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return (int) $wpdb->insert_id;
    }
    public function findRecent(int $limit = 50): array
    {
        global $wpdb;
        $limit = max(1, min(200, $limit));
        $rows  = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d', $this->table->getTableName(), $limit),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }

    /** One page of the log, newest first. */
    public function findPage(int $limit, int $offset): array
    {
        global $wpdb;
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $rows   = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $this->table->getTableName(), $limit, $offset),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }

    public function countAll(): int
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $this->table->getTableName()));
        return null === $count ? 0 : (int) $count;
    }
}
