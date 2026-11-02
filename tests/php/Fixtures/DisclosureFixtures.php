<?php

declare(strict_types=1);

use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataQueryService;
use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataLoadingService;
use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresLanguageDeciderService;
use VeraTag\Persistence\Disclosures\Repositories\{BatchesRepository,PagesRepository,ContentItemsRepository,ConfigurationsRepository,PlacementsRepository,TextTemplatesRepository};
use VeraTag\Persistence\Disclosures\Schema\{BatchesTable,PagesTable,ContentItemsTable,ConfigurationsTable,PlacementsTable,TextTemplatesTable};
use VeraTag\Settings\Services\GlobalSettings;
use VeraTag\VeraTagBackend\HttpConfig;
use VeraTag\Settings\OptionKeys\SettingKey;

function fixture_slots(string $language): array
{
    $words = [
        'de' => ['KI', 'KI-Hinweis', 'Dieser Inhalt enthält KI.'],
        'en' => ['AI', 'AI disclosure', 'This content uses AI.'],
        'fr' => ['IA', 'Avis IA', 'Ce contenu utilise IA.'],
        'es' => ['IA', 'Aviso IA', 'Este contenido usa IA.'],
    ];
    $values = $words[$language] ?? [$language, 'Label ' . $language, 'Message ' . $language];
    return ['disclosure_acronym' => $values[0], 'disclosure_label' => $values[1], 'disclosure_message' => $values[2], 'placement_note' => "Note {$language}"];
}
function fixture_entry(string $page = '/article/', string $id = 'entry-1', array $languages = ['de', 'en']): array
{
    $by_language = [];
    foreach ($languages as $lang) {
        $by_language[$lang] = ['slots' => fixture_slots($lang)];
    }
    return [
        'contentId' => $id, 'page' => $page, 'contentType' => 'image', 'contentHtmlTag' => 'img', 'disclosureType' => 'ai_generated',
        'byLanguage' => $by_language,
        'placements' => [[
            'placementName' => 'SyntheticBadge', 'schemaName' => 'test-schema', 'schemaVersion' => '1',
            'params' => ['nested' => ['label' => 'placeholder', 'other' => 'constant'], 'content' => []],
            'slotBindings' => ['nested.label' => 'placement_note'], 'emptyObjectPaths' => ['content'],
        ]],
    ];
}
function fixture_loader(): DisclosuresDataLoadingService
{
    $query = new DisclosuresDataQueryService(
        new BatchesRepository(new BatchesTable()),
        new PagesRepository(new PagesTable()),
        new ContentItemsRepository(new ContentItemsTable()),
        new ConfigurationsRepository(new ConfigurationsTable()),
        new PlacementsRepository(new PlacementsTable()),
        new TextTemplatesRepository(new TextTemplatesTable())
    );
    return new DisclosuresDataLoadingService($query, new DisclosuresLanguageDeciderService(), new GlobalSettings(new HttpConfig()));
}
function fixture_add_entry(array $entry, int $page_id = 1): void
{
    global $wpdb;
    if (!$wpdb instanceof FixtureWpdb) {
        throw new RuntimeException('Test fixture DB missing');
    }
    $tablePrefix = $wpdb->prefix;
    $pagesTable = $tablePrefix . 'veratag_pages';
    $contentsTable = $tablePrefix . 'veratag_content_items';
    $configsTable = $tablePrefix . 'veratag_configurations';
    $placementsTable = $tablePrefix . 'veratag_placements';
    $batchesTable = $tablePrefix . 'veratag_disclosure_fetches';
    $templatesTable = $tablePrefix . 'veratag_text_templates';
    foreach ([$pagesTable,$contentsTable,$configsTable,$placementsTable,$batchesTable,$templatesTable] as $name) {
        $wpdb->tables[$name] ??= [];
    }
    $wpdb->tables[$batchesTable] = [['id' => 1, 'fetched_at' => '2026-01-01 00:00:00', 'received_content_count' => 1]];
    // Real veratag_pages has UNIQUE(page_url). Reuse the same page ID for all
    // content on a page rather than manufacturing duplicate rows.
    $existing_page_ids = array_column($wpdb->tables[$pagesTable], 'id', 'page_url');
    if (isset($existing_page_ids[$entry['page']])) {
        $page_id = (int) $existing_page_ids[$entry['page']];
    } else {
        $page_id = max(array_merge([0], array_map('intval', array_column($wpdb->tables[$pagesTable], 'id')))) + 1;
        $wpdb->tables[$pagesTable][] = ['id' => $page_id, 'page_url' => $entry['page']];
    }
    $configRef = 'cfg-' . $entry['contentId'];
    $placementRef = 'placement-' . $entry['contentId'];
    $slots = [];
    foreach ($entry['byLanguage'] ?? [] as $lang => $data) {
        foreach ($data['slots'] as $key => $text) {
            $slots[$key][$lang] = ['custom' => $text];
        }
    }
    $wpdb->tables[$contentsTable][] = [
        'id' => count($wpdb->tables[$contentsTable]) + 1, 'page_id' => $page_id,
        'content_id' => $entry['contentId'], 'content_type' => $entry['contentType'], 'content_html_tag' => $entry['contentHtmlTag'],
        'disclosure_type' => $entry['disclosureType'], 'configuration_ref' => $configRef, 'is_disclosed' => 1,
    ];
    $placement_refs = [];
    foreach ($entry['placements'] ?? [] as $index => $placement) {
        $current_ref = $placementRef . '-' . $index;
        $placement_refs[] = $current_ref;
        $wpdb->tables[$placementsTable][] = [
            'ref' => $current_ref, 'name' => $placement['placementName'], 'schema_name' => $placement['schemaName'],
            'schema_version' => $placement['schemaVersion'], 'raw_params' => json_encode($placement['params']),
            'slot_bindings' => json_encode($placement['slotBindings']),
            'render_script_file_name' => $placement['renderScriptFileName'] ?? '',
            'render_script_version' => $placement['renderScriptVersion'] ?? '',
        ];
    }
    $wpdb->tables[$configsTable][] = [
        'ref' => $configRef, 'placement_refs' => json_encode($placement_refs), 'slots' => json_encode($slots),
    ];
}

function fixture_settings(string $mode = 'visitor_preferred', string $fallback = 'en'): void
{
    $GLOBALS['test_options'][SettingKey::CHANNEL_VISITOR_DISCLOSURE_LANGUAGE_SETTINGS] = ['preferredMode' => $mode, 'fallbackLanguageBcp47' => $fallback];
}
function fixture_request(string $language = 'de-DE', string $url = '/article/', string $home = 'https://site.example.test'): void
{
    $_SERVER['REQUEST_URI'] = $url;
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $language;
    $GLOBALS['test_home_url'] = $home;
}
function fixture_render(array $entries, string $visitorLanguage = 'de-DE', string $mode = 'visitor_preferred', string $fallback = 'en', string $requestPath = '/article/', string $home = 'https://site.example.test', string $serverLocale = 'en_US'): array|WP_Error
{
    reset_fixtures();
    fixture_settings($mode, $fallback);
    fixture_request($visitorLanguage, $requestPath, $home);
    $GLOBALS['test_locale'] = $serverLocale;
    foreach ($entries as $idx => $entry) {
        fixture_add_entry($entry, $idx + 1);
    }
    return fixture_loader()->loadForCurrentPage();
}
/** @return list<array{file_name: string, required_version: string}> */
function fixture_required_render_scripts(array $entries, string $requestPath = '/article/'): array
{
    reset_fixtures();
    fixture_settings();
    fixture_request('de-DE', $requestPath);
    foreach ($entries as $idx => $entry) {
        fixture_add_entry($entry, $idx + 1);
    }
    return fixture_loader()->loadCurrentPage()['render_scripts'];
}
function fixture_selected(array $payload): array
{
    return $payload['contentRecords'][0]['disclosure']['disclosureInformation'] ?? [];
}

/** Real plugin entry point -> real query -> real language resolution -> real HTML script. */
function fixture_actual_page_html(array $entries, string $acceptLanguage = 'de-DE', string $fallback = 'en', string $path = '/article/', string $home = 'https://site.example.test', string $mode = 'visitor_preferred'): string
{
    reset_fixtures();
    fixture_settings($mode, $fallback);
    fixture_request($acceptLanguage, $path, $home);
    foreach ($entries as $idx => $entry) {
        fixture_add_entry($entry, $idx + 1);
    }
    $enqueue = (new ReflectionClass(\VeraTag\Features\FrontendServing\DisclosuresRendering\DisclosuresRenderScriptEnqueuingService::class))->newInstanceWithoutConstructor();
    $service = new \VeraTag\Features\FrontendServing\FrontendServingService(fixture_loader(), $enqueue, new \VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataInjectionService());
    ob_start();
    try {
        $service->injectTaggerData();
        return (string) ob_get_clean();
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
}
function fixture_extract_disclosure_script(string $html): array
{
    truth((bool) preg_match('~<script type="application/json" id="disclosure-data">(.*?)</script>~s', $html, $m), 'Expected generated disclosure-data script');
    return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
}
