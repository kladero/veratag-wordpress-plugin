<?php

namespace VeraTag\Persistence\Disclosures\Schema;

/** Last reconciliation result per required render-script file, including failures before any cache row exists. */
final class RenderScriptFetchStatusesTable
{
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'veratag_render_script_fetch_statuses';
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
			required_version varchar(64) NOT NULL,
			ok tinyint(1) NOT NULL DEFAULT 0,
			http_response_code int NOT NULL DEFAULT 0,
			http_status_text varchar(191) NOT NULL DEFAULT '',
			status_source varchar(64) NOT NULL DEFAULT 'local-plugin',
			message longtext NOT NULL,
			last_fetch_at datetime NOT NULL,
			http_call_made tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (file_name)
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
