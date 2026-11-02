<?php

namespace VeraTag\Persistence\Disclosures\Schema;

/** One row per entry in a content item's issues[] — applies to both disclosed and not-disclosed items. */
final class ContentItemIssuesTable
{
    public function getTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'veratag_content_item_issues';
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
			content_item_id bigint(20) unsigned NOT NULL,
			reason varchar(191) NOT NULL,
			impact varchar(32) NOT NULL,
			message longtext NOT NULL,
			detected_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY content_item_id (content_item_id)
		) {$charset_collate};";

        dbDelta($create_table_sql);
    }

    public function drop(): void
    {
        global $wpdb;

        $table_name = $this->getTableName();

        $wpdb->query("DROP TABLE IF EXISTS {$table_name}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
