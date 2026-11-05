<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\Schema\RenderScriptsTable;
use WP_Error;

final class RenderScriptsRepository
{
    public function __construct(private readonly RenderScriptsTable $render_scripts_table)
    {
    }
    public function upsert(string $file_name, string $script_version, string $script_content, string $downloaded_at): bool|WP_Error
    {
        global $wpdb;
        $result = $wpdb->replace(
            $this->render_scripts_table->getTableName(),
            array(
                'file_name'      => $file_name,
                'script_version' => $script_version,
                'script_content' => $script_content,
                'downloaded_at'  => $downloaded_at,
            ),
            array( '%s', '%s', '%s', '%s' )
        );
        if (false === $result) {
            return new WP_Error(
                'veratag_render_script_db_error',
                'Unable to store render script.',
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
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE file_name = %s', $this->render_scripts_table->getTableName(), $file_name),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }
    public function findAll(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY file_name ASC', $this->render_scripts_table->getTableName()),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }
    public function findAllFileNames(): array
    {
        return array_values(array_map(static fn (array $row): string => (string) $row['file_name'], $this->findAll()));
    }
    public function deleteByFileName(string $file_name): void
    {
        global $wpdb;
        $wpdb->delete($this->render_scripts_table->getTableName(), array( 'file_name' => $file_name ), array( '%s' ));
    }
}
