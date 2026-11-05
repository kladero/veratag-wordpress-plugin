<?php

namespace VeraTag\Persistence\Disclosures\Repositories;

use VeraTag\Persistence\Disclosures\DictionaryGarbageCollector;
use VeraTag\Persistence\Disclosures\Schema\ConfigurationsTable;
use WP_Error;

final class ConfigurationsRepository
{
    public function __construct(private readonly ConfigurationsTable $configurations_table)
    {
    }

    public function upsert(string $ref, string $name, array $placement_refs, array $slots, array $issues): bool|WP_Error
    {
        global $wpdb;
        $result = $wpdb->replace(
            $this->configurations_table->getTableName(),
            array(
                'ref'            => trim($ref),
                'name'           => $name,
                'placement_refs' => $this->encodeJson(array_values($placement_refs)),
                'slots'          => $this->encodeJson($slots),
                'issues'         => $this->encodeJson(array_values($issues)),
                'updated_at'     => current_time('mysql', true),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if (false === $result) {
            return new WP_Error(
                'veratag_disclosure_persistence_db_error',
                'Unable to upsert configuration.',
                array(
                    'status'   => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }
        return true;
    }

    /** Trigger-B-aware canonical configuration write path shared by pull and push. */
    public function upsertWithGc(string $configuration_ref, array $configuration_data, DictionaryGarbageCollector $gc): bool|WP_Error
    {
        $existing           = $this->findByRef($configuration_ref);
        $old_placements     = $existing ? $this->decodeStringArray($existing['placement_refs'] ?? '[]') : array();
        $old_templates      = $existing ? self::extractTemplateRefs($this->decodeAssoc($existing['slots'] ?? '{}')) : array();
        $new_placements     = isset($configuration_data['placementRefs']) && is_array($configuration_data['placementRefs'])
            ? array_values(array_filter($configuration_data['placementRefs'], 'is_string')) : array();
        $new_slots          = isset($configuration_data['slots']) && is_array($configuration_data['slots']) ? $configuration_data['slots'] : array();
        $new_templates      = self::extractTemplateRefs($new_slots);
        $dropped_placements = array_values(array_diff($old_placements, $new_placements));
        $dropped_templates  = array_values(array_diff($old_templates, $new_templates));

        // Diff is captured before overwrite, but cleanup runs after overwrite so the owner row
        // no longer falsely counts as a reference to a just-detached ref.
        $result = $this->upsert(
            $configuration_ref,
            isset($configuration_data['name']) && is_string($configuration_data['name']) ? $configuration_data['name'] : '',
            $new_placements,
            $new_slots,
            isset($configuration_data['issues']) && is_array($configuration_data['issues']) ? $configuration_data['issues'] : array()
        );
        if (is_wp_error($result)) {
            return $result;
        }
        $gc->collectGarbageForDetachedRefs($dropped_placements, $dropped_templates);
        return true;
    }

    public function findByRef(string $ref): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE ref = %s', $this->configurations_table->getTableName(), $ref),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }
    /** @param string[] $refs */
    public function findByRefs(array $refs): array
    {
        global $wpdb;
        $refs = array_values(array_unique(array_filter($refs, static fn (mixed $ref): bool => is_string($ref) && '' !== trim($ref))));
        if (array() === $refs) {
            return array();
        }
        $placeholders = implode(', ', array_fill(0, count($refs), '%s'));
        $rows         = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $placeholders contains only fixed %s tokens; all values are passed to prepare().
            $wpdb->prepare('SELECT * FROM %i WHERE ref IN (' . $placeholders . ') ORDER BY ref ASC', array_merge(array( $this->configurations_table->getTableName() ), $refs)),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }

    public function findAll(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY ref ASC', $this->configurations_table->getTableName()),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }

    public function countReferencingPlacementRef(string $placement_ref): int
    {
        global $wpdb;
        // placement_refs is a flat JSON array; JSON_CONTAINS performs exact element matching.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE JSON_CONTAINS(placement_refs, JSON_QUOTE(%s))',
                $this->configurations_table->getTableName(),
                $placement_ref
            )
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return null === $count ? 0 : (int) $count;
    }

    public function countReferencingTemplateRef(string $template_ref): int
    {
        global $wpdb;
        // slots is nested; JSON_SEARCH is required because JSON_CONTAINS cannot wildcard-search nested object values.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE JSON_SEARCH(slots, \'one\', %s) IS NOT NULL',
                $this->configurations_table->getTableName(),
                $template_ref
            )
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return null === $count ? 0 : (int) $count;
    }

    /**
     * Every configuration ref whose {@code placement_refs} currently
     * contains {@code placement_ref} — resolved in PHP over {@see findAll}
     * rather than SQL JSON matching, since the local mirror's configuration
     * count is always small (one WordPress site's own content) and this
     * keeps the lookup trivially correct without MySQL/MariaDB JSON
     * version quirks. Used to fan a placement push's issue changes out to
     * every content item that is affected transitively through a
     * configuration that uses it.
     *
     * @return string[]
     */
    public function findRefsReferencingPlacementRef(string $placement_ref): array
    {
        $refs = array();
        foreach ($this->findAll() as $row) {
            if (in_array($placement_ref, $this->decodeStringArray($row['placement_refs'] ?? '[]'), true)) {
                $refs[] = (string) $row['ref'];
            }
        }
        return $refs;
    }

    /** Same as {@see findRefsReferencingPlacementRef}, for a template ref appearing anywhere in {@code slots}. */
    public function findRefsReferencingTemplateRef(string $template_ref): array
    {
        $refs = array();
        foreach ($this->findAll() as $row) {
            if (in_array($template_ref, self::extractTemplateRefs($this->decodeAssoc($row['slots'] ?? '{}')), true)) {
                $refs[] = (string) $row['ref'];
            }
        }
        return $refs;
    }

    public function deleteByRef(string $ref): void
    {
        global $wpdb;
        $wpdb->delete($this->configurations_table->getTableName(), array( 'ref' => $ref ), array( '%s' ));
    }

    public static function extractTemplateRefs(array $slots): array
    {
        $refs = array();
        foreach ($slots as $by_language) {
            if (! is_array($by_language)) {
                continue;
            }
            foreach ($by_language as $slot_value) {
                if (is_array($slot_value) && isset($slot_value['templateRef']) && is_string($slot_value['templateRef']) && '' !== trim($slot_value['templateRef'])) {
                    $refs[] = trim($slot_value['templateRef']);
                }
            }
        }
        return array_values(array_unique($refs));
    }

    private function encodeJson(mixed $value): string
    {
        $encoded = wp_json_encode($value);
        return false === $encoded ? 'null' : $encoded;
    }
    private function decodeStringArray(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : array();
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : array();
    }
    private function decodeAssoc(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : array();
        return is_array($decoded) ? $decoded : array();
    }
}
