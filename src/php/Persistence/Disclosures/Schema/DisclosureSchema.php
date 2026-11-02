<?php

namespace VeraTag\Persistence\Disclosures\Schema;

final class DisclosureSchema
{
    // 2.1.0: pages.page_url widened from varchar(191) to varchar(767) — the
    // old width silently failed to store (and so aborted the whole pull
    // synchronization for) any page whose URL exceeded 191 characters.
    private const SCHEMA_VERSION        = '2.1.0';
    private const SCHEMA_VERSION_OPTION = 'veratag_disclosure_sync_schema_version';

    public function __construct(
        private readonly BatchesTable $batches_table,
        private readonly ContentOverviewTable $content_overview_table,
        private readonly PagesTable $pages_table,
        private readonly ContentItemsTable $content_items_table,
        private readonly ContentItemIssuesTable $content_item_issues_table,
        private readonly ConfigurationsTable $configurations_table,
        private readonly PlacementsTable $placements_table,
        private readonly TextTemplatesTable $text_templates_table,
        private readonly RenderScriptsTable $render_scripts_table,
        private readonly RenderScriptFetchStatusesTable $render_script_fetch_statuses_table
    ) {
    }

    public function maybeUpgrade(): void
    {
        $installed_version = get_option(self::SCHEMA_VERSION_OPTION);
        $schema_up_to_date = self::SCHEMA_VERSION === $installed_version;

        if ($schema_up_to_date && $this->tablesExist()) {
            return;
        }

        $this->dropAll();
        $this->createAll();
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION);
    }

    public function tablesExist(): bool
    {
        return $this->batches_table->exists()
            && $this->content_overview_table->exists()
            && $this->pages_table->exists()
            && $this->content_items_table->exists()
            && $this->content_item_issues_table->exists()
            && $this->configurations_table->exists()
            && $this->placements_table->exists()
            && $this->text_templates_table->exists()
            && $this->render_scripts_table->exists()
            && $this->render_script_fetch_statuses_table->exists();
    }

    public function createAll(): void
    {
        $this->batches_table->create();
        $this->content_overview_table->create();
        $this->pages_table->create();
        $this->content_items_table->create();
        $this->content_item_issues_table->create();
        $this->configurations_table->create();
        $this->placements_table->create();
        $this->text_templates_table->create();
        $this->render_scripts_table->create();
        $this->render_script_fetch_statuses_table->create();
    }

    public function dropAll(): void
    {
        // Also drop obsolete v1 tables so an upgrade cannot leave stale duplicated data behind.
        global $wpdb;
        $obsolete = array(
            $wpdb->prefix . 'veratag_content_item_language_slots',
            $wpdb->prefix . 'veratag_content_item_languages',
            $wpdb->prefix . 'veratag_placement_slot_bindings',
            $wpdb->prefix . 'veratag_content_item_placements',
            $wpdb->prefix . 'veratag_fetch_script_information',
        );
        foreach ($obsolete as $table_name) {
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $this->content_item_issues_table->drop();
        $this->content_items_table->drop();
        $this->pages_table->drop();
        $this->content_overview_table->drop();
        $this->batches_table->drop();
        $this->configurations_table->drop();
        $this->placements_table->drop();
        $this->text_templates_table->drop();
        $this->render_scripts_table->drop();
        $this->render_script_fetch_statuses_table->drop();
    }
}
