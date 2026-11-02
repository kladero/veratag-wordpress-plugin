<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\TextTemplatesTable;
use WP_Error;

final class TextTemplatesRepository
{
    public function __construct(private readonly TextTemplatesTable $text_templates_table)
    {
    }
    public function upsert(string $ref, array $template): bool|WP_Error
    {
        global $wpdb;
        $result = $wpdb->replace(
            $this->text_templates_table->getTableName(),
            array(
                'ref'        => trim($ref),
                'name'       => isset($template['name']) && is_string($template['name']) ? $template['name'] : '',
                'texts'      => $this->encodeJson(isset($template['texts']) && is_array($template['texts']) ? $template['texts'] : array()),
                'issues'     => $this->encodeJson(isset($template['issues']) && is_array($template['issues']) ? array_values($template['issues']) : array()),
                'updated_at' => current_time('mysql', true),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
        if (false === $result) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to upsert text template.',
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
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->text_templates_table->getTableName() . ' WHERE ref = %s', $ref), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
            $wpdb->prepare('SELECT * FROM ' . $this->text_templates_table->getTableName() . " WHERE ref IN ({$placeholders}) ORDER BY ref ASC", $refs), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The generated placeholder list and values are both controlled here.
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }
    public function findAll(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . $this->text_templates_table->getTableName() . ' ORDER BY ref ASC', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($rows) ? $rows : array();
    }
    public function deleteByRef(string $ref): void
    {
        global $wpdb;
        $wpdb->delete($this->text_templates_table->getTableName(), array( 'ref' => $ref ), array( '%s' ));
    }
    private function encodeJson(mixed $value): string
    {
        $encoded = wp_json_encode($value);
        return false === $encoded ? 'null' : $encoded;
    }
}
