<?php

namespace VeraTag\Persistence\Disclosures;

use Throwable;
use VeraTag\Logging\Logger;
use WP_Error;

/** Serializes and atomically publishes one full pull-synchronization snapshot. */
final class DisclosureSnapshotTransactionService
{
    public function __construct(private Logger $logger)
    {
    }

    /** @param callable(): int|WP_Error $write_snapshot */
    public function execute(callable $write_snapshot): int|WP_Error
    {
        global $wpdb;
        $lock_name     = 'veratag_snapshot_' . substr(hash('sha256', (string) $wpdb->prefix), 0, 40);
        $lock_acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock_name));
        if (1 !== (int) $lock_acquired) {
            return new WP_Error('veratag_pull_synchronization_locked', 'Another pull synchronization is already publishing a snapshot.', array( 'status' => 409 ));
        }

        $transaction_active = false;
        try {
            if (false === $wpdb->query('START TRANSACTION')) {
                return $this->databaseError('Unable to start disclosure snapshot transaction.');
            }
            $transaction_active = true;
            $result             = $write_snapshot();
            if (is_wp_error($result)) {
                return $result;
            }
            if (false === $wpdb->query('COMMIT')) {
                return $this->databaseError('Unable to commit disclosure snapshot transaction.');
            }
            $transaction_active = false;
            return $result;
        } catch (Throwable $throwable) {
            return new WP_Error('veratag_disclosure_persistence_exception', $throwable->getMessage(), array( 'status' => 500 ));
        } finally {
            if ($transaction_active) {
                $wpdb->query('ROLLBACK');
            }
            if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name))) {
                $this->logger->error('vera[tag] failed to release the pull snapshot lock.');
            }
        }
    }

    private function databaseError(string $message): WP_Error
    {
        global $wpdb;
        return new WP_Error(
            'veratag_disclosure_persistence_db_error',
            $message,
            array(
                'status'   => 500,
                'db_error' => $wpdb->last_error,
            )
        );
    }
}
