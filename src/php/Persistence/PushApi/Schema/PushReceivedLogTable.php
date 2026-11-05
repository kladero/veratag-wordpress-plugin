<?php

namespace VeraTag\Persistence\PushApi\Schema;

final class PushReceivedLogTable
{
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'veratag_push_received_log';
    }
    public function exists(): bool
    {
        global $wpdb;
        $name = $this->getTableName();
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name));
    }
    public function create(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $name    = $this->getTableName();
        $collate = $wpdb->get_charset_collate();
        dbDelta(
            "CREATE TABLE {$name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			received_at datetime NOT NULL,
			instruction varchar(64) NOT NULL,
			ref_value varchar(191) DEFAULT NULL,
			applied_successfully tinyint(1) NOT NULL DEFAULT 0,
			error_message longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY received_at (received_at),
			KEY instruction (instruction)
		) {$collate};"
        );
    }
    public function drop(): void
    {
        global $wpdb;
        $name = $this->getTableName();
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $name));
    }
}
