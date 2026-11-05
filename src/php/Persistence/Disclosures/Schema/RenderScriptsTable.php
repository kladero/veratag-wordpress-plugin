<?php

namespace VeraTag\Persistence\Disclosures\Schema;

/** Cached renderer files, one current cached version per backend-supplied file name. */
final class RenderScriptsTable
{
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'veratag_render_scripts';
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
			file_name varchar(191) NOT NULL,
			script_version varchar(64) NOT NULL,
			script_content longtext NOT NULL,
			downloaded_at datetime NOT NULL,
			PRIMARY KEY  (file_name)
		) {$charset_collate};";
        dbDelta($sql);
    }

    public function drop(): void
    {
        global $wpdb;
        $table_name = $this->getTableName();
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table_name));
    }
}
