<?php

namespace VeraTag\Persistence\PushApi;

use Throwable;
use WP_Error;

/** Serializes and atomically applies one incremental push instruction. */
final class PushInstructionTransactionService
{
    /** @param callable(): bool|WP_Error $apply_instruction */
    public function execute(callable $apply_instruction): bool|WP_Error
    {
        global $wpdb;

        // Shares the full-pull lock so a snapshot and an incremental update cannot
        // overwrite each other while either is publishing disclosure data.
        $lock_name     = 'veratag_snapshot_' . substr(hash('sha256', (string) $wpdb->prefix), 0, 40);
        $lock_acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock_name));
        if (1 !== (int) $lock_acquired) {
            return new WP_Error('veratag_push_instruction_locked', 'Another disclosure update is already in progress.', array( 'status' => 409 ));
        }

        $transaction_active = false;
        try {
            if (false === $wpdb->query('START TRANSACTION')) {
                return $this->databaseError('Unable to start push instruction transaction.');
            }
            $transaction_active = true;

            $result = $apply_instruction();
            if (is_wp_error($result)) {
                return $result;
            }

            if (false === $wpdb->query('COMMIT')) {
                return $this->databaseError('Unable to commit push instruction transaction.');
            }
            $transaction_active = false;

            return true;
        } catch (Throwable $throwable) {
            return new WP_Error('veratag_push_instruction_exception', $throwable->getMessage(), array( 'status' => 500 ));
        } finally {
            if ($transaction_active) {
                $wpdb->query('ROLLBACK');
            }
            if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name))) {
                error_log('vera[tag] failed to release the incremental push lock.');
            }
        }
    }

    private function databaseError(string $message): WP_Error
    {
        global $wpdb;
        return new WP_Error(
            'veratag_push_instruction_db_error',
            $message,
            array(
                'status'   => 500,
                'db_error' => $wpdb->last_error,
            )
        );
    }
}
