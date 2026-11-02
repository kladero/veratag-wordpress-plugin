<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\PlacementsTable;
use WP_Error;

final class PlacementsRepository
{
    public function __construct(private readonly PlacementsTable $placements_table)
    {
    }

    public function upsert(string $ref, array $placement): bool|WP_Error
    {
        global $wpdb;
        $schema        = isset($placement['schema']) && is_array($placement['schema']) ? $placement['schema'] : array();
        $render_script = isset($placement['renderScript']) && is_array($placement['renderScript']) ? $placement['renderScript'] : array();
        $result        = $wpdb->replace(
            $this->placements_table->getTableName(),
            array(
                'ref'                     => trim($ref),
                'name'                    => isset($placement['name']) && is_string($placement['name']) ? $placement['name'] : '',
                'schema_name'             => isset($schema['schemaName']) && is_string($schema['schemaName']) ? $schema['schemaName'] : '',
                'schema_version'          => isset($schema['schemaVersion']) && is_string($schema['schemaVersion']) ? $schema['schemaVersion'] : '',
                'render_script_file_name' => isset($render_script['fileName']) && is_string($render_script['fileName']) ? trim($render_script['fileName']) : '',
                'render_script_version'   => isset($render_script['scriptReleaseVersion']) && is_string($render_script['scriptReleaseVersion']) ? trim($render_script['scriptReleaseVersion']) : '',
                'raw_params'              => $this->encodeJson($placement['rawParams'] ?? new \stdClass()),
                'slot_bindings'           => $this->encodeJson(isset($placement['slotBindings']) && is_array($placement['slotBindings']) ? $placement['slotBindings'] : array()),
                'issues'                  => $this->encodeJson(isset($placement['issues']) && is_array($placement['issues']) ? array_values($placement['issues']) : array()),
                'updated_at'              => current_time('mysql', true),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if (false === $result) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to upsert placement.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return true;
    }

    public function findByRef(string $ref): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->placements_table->getTableName() . ' WHERE ref = %s', $ref), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($row) ? $row : null;
    }
    /** @param string[] $refs */
    public function findByRefs(array $refs): array
    {
        global $wpdb;
        $refs = array_values(array_unique(array_filter($refs, static fn (mixed $ref): bool => is_string($ref) && '' !== trim($ref))));
        if (array() === $refs) {
            return array();
        }
        $placeholders = implode(', ', array_fill(0, count($refs), '%s'));
        $rows         = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . $this->placements_table->getTableName() . " WHERE ref IN ({$placeholders}) ORDER BY ref ASC", $refs), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The generated placeholder list and values are both controlled here.
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }
    public function findAll(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . $this->placements_table->getTableName() . ' ORDER BY ref ASC', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($rows) ? $rows : array();
    }
    public function findDistinctRequiredScripts(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT render_script_file_name AS file_name, render_script_version AS required_version FROM %i WHERE render_script_file_name <> '' AND render_script_version <> '' GROUP BY render_script_file_name, render_script_version ORDER BY render_script_file_name ASC", $this->placements_table->getTableName()),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($rows) ? $rows : array();
    }
    public function countByRenderScriptFileName(string $file_name): int
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $this->placements_table->getTableName() . ' WHERE render_script_file_name = %s', $file_name)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return null === $count ? 0 : (int) $count;
    }
    public function deleteByRef(string $ref): void
    {
        global $wpdb;
        $wpdb->delete($this->placements_table->getTableName(), array( 'ref' => $ref ), array( '%s' ));
    }
    private function encodeJson(mixed $value): string
    {
        $encoded = wp_json_encode($value);
        return false === $encoded ? 'null' : $encoded;
    }
}
