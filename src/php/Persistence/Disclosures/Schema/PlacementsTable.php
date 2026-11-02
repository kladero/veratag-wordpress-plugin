<?php

namespace VeraTag\Persistence\Disclosures\Schema;

final class PlacementsTable
{
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'veratag_placements';
    }

    public function exists(): bool
    {
        global $wpdb;
        $table_name = $this->getTableName();
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
    }

    public function create(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table_name      = $this->getTableName();
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE {$table_name} (
			ref varchar(191) NOT NULL,
			name varchar(191) NOT NULL,
			schema_name varchar(191) NOT NULL,
			schema_version varchar(64) NOT NULL,
			render_script_file_name varchar(191) NOT NULL,
			render_script_version varchar(64) NOT NULL,
			raw_params longtext NOT NULL,
			slot_bindings longtext NOT NULL,
			issues longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (ref),
			KEY render_script_file_name (render_script_file_name)
		) {$charset_collate};";
        dbDelta($sql);
    }

    public function drop(): void
    {
        global $wpdb;
        $table_name = $this->getTableName();
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
