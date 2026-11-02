<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\RenderScriptFetchStatusesTable;
use WP_Error;

final class RenderScriptFetchStatusesRepository
{
    public function __construct(private readonly RenderScriptFetchStatusesTable $table)
    {
    }
    public function upsert(string $file_name, string $required_version, bool $ok, int $http_code, string $http_text, string $status_source, string $message, string $last_fetch_at, bool $http_call_made): bool|WP_Error
    {
        global $wpdb;
        $result = $wpdb->replace(
            $this->table->getTableName(),
            array(
                'file_name'          => $file_name,
                'required_version'   => $required_version,
                'ok'                 => $ok ? 1 : 0,
                'http_response_code' => $http_code,
                'http_status_text'   => $http_text,
                'status_source'      => $status_source,
                'message'            => $message,
                'last_fetch_at'      => $last_fetch_at,
                'http_call_made'     => $http_call_made ? 1 : 0,
            ),
            array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d' )
        );
        if (false === $result) {
            return new WP_Error(
                'veratag_render_script_status_db_error',
                'Unable to store render script fetch status.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return true;
    }
    public function findByFileName(string $file_name): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->table->getTableName() . ' WHERE file_name = %s', $file_name), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($row) ? $row : null;
    }
    public function deleteByFileName(string $file_name): void
    {
        global $wpdb;
        $wpdb->delete($this->table->getTableName(), array( 'file_name' => $file_name ), array( '%s' ));
    }
}
