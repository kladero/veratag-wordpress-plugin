<?php

namespace VeraTag\Persistence\Disclosures\Schema;

/** One row per page in the current disclosure snapshot. */
final class PagesTable
{
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'veratag_pages';
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
        // page_url is varchar(767), not the common varchar(191) — 191 is a
        // legacy workaround for utf8mb4 columns hitting InnoDB's old
        // 767-byte index-key limit (191 * 4 bytes ~= 764), but real page
        // URLs routinely exceed 191 characters (query strings, long slugs),
        // and MariaDB/MySQL have supported large index prefixes (up to 3072
        // bytes, i.e. 767 utf8mb4 characters) by default for years now. A
        // URL that didn't fit silently failed this INSERT, which aborted
        // the entire pull synchronization
        // (see PullSynchronizationPersistenceService::persistPage) — not
        // just that one page — so nothing on this page, or any page after
        // it in that same sync, ever reached the local database.
        $sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			disclosure_fetch_id bigint(20) unsigned NOT NULL,
			page_url varchar(767) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY page_url_unique (page_url),
			KEY disclosure_fetch_id (disclosure_fetch_id)
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
