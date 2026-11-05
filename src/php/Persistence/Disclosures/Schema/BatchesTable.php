<?php

namespace VeraTag\Persistence\Disclosures\Schema;

final class BatchesTable
{
    public function getTableName(): string
    {

        global $wpdb;

        return $wpdb->prefix . 'veratag_disclosure_fetches';
    }

    public function exists(): bool
    {
        global $wpdb;

        $table_name = $this->getTableName();

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );
    }

    public function create(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name       = $this->getTableName();
        $charset_collate  = $wpdb->get_charset_collate();
        $create_table_sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fetched_at datetime NOT NULL,
			received_content_count int unsigned NOT NULL DEFAULT 0,
			payload_hash varchar(64) DEFAULT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";
        dbDelta($create_table_sql);
    }

    public function drop(): void
    {
        global $wpdb;

        $table_name = $this->getTableName();

        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table_name));
    }
}
