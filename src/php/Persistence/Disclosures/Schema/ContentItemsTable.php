<?php

namespace VeraTag\Persistence\Disclosures\Schema;

/** Current content items. Shared disclosure data is reached through configuration_ref. */
final class ContentItemsTable
{
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'veratag_content_items';
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
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_id bigint(20) unsigned NOT NULL,
			content_id varchar(191) NOT NULL,
			content_type varchar(64) NOT NULL,
			content_html_tag varchar(64) NOT NULL,
			content_display longtext NOT NULL,
			is_disclosed tinyint(1) NOT NULL DEFAULT 0,
			disclosure_type varchar(191) DEFAULT NULL,
			configuration_ref varchar(191) DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY page_content_unique (page_id, content_id),
			KEY configuration_ref (configuration_ref)
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
