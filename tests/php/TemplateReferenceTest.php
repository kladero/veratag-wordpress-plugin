<?php

declare(strict_types=1);

use VeraTag\Persistence\Disclosures\Repositories\ConfigurationsRepository;

test('templates: template referenced in configured slots is resolved by real query service', static function (): void {
    reset_fixtures();
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry());
    $config = &$GLOBALS['wpdb']->tables['test_veratag_configurations'][0];
    $slots = json_decode($config['slots'], true);
    $slots['disclosure_message']['de'] = ['templateRef' => 'message-template'];
    $config['slots'] = json_encode($slots);
    $GLOBALS['wpdb']->tables['test_veratag_text_templates'][] = [
        'ref' => 'message-template', 'texts' => '{"de":"German template disclosure","en":"English template disclosure"}',
    ];
    $result = fixture_loader()->loadForCurrentPage();
    same('German template disclosure', fixture_selected($result)['disclosureGeneralMessage']);
    same('de', fixture_selected($result)['disclosureLanguage']);
});
test('templates: missing template text makes German unusable, fallback English', static function (): void {
    reset_fixtures();
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry());
    $config = &$GLOBALS['wpdb']->tables['test_veratag_configurations'][0];
    $slots = json_decode($config['slots'], true);
    $slots['disclosure_message']['de'] = ['templateRef' => 'missing-template'];
    $config['slots'] = json_encode($slots);
    $result = fixture_loader()->loadForCurrentPage();
    same('en', fixture_selected($result)['disclosureLanguage']);
});
test('templates: changing shared template changes selected disclosure at next request', static function (): void {
    reset_fixtures();
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry());
    $config = &$GLOBALS['wpdb']->tables['test_veratag_configurations'][0];
    $slots = json_decode($config['slots'], true);
    $slots['disclosure_message']['de'] = ['templateRef' => 'message-template'];
    $config['slots'] = json_encode($slots);
    $GLOBALS['wpdb']->tables['test_veratag_text_templates'][] = ['ref' => 'message-template', 'texts' => '{"de":"Version 1"}'];
    same('Version 1', fixture_selected(fixture_loader()->loadForCurrentPage())['disclosureGeneralMessage']);
    $GLOBALS['wpdb']->tables['test_veratag_text_templates'][0]['texts'] = '{"de":"Version 2"}';
    same('Version 2', fixture_selected(fixture_loader()->loadForCurrentPage())['disclosureGeneralMessage']);
});
test('templates: custom text takes priority over template reference in same slot', static function (): void {
    reset_fixtures();
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry());
    $config = &$GLOBALS['wpdb']->tables['test_veratag_configurations'][0];
    $slots = json_decode($config['slots'], true);
    $slots['disclosure_message']['de'] = ['custom' => 'Custom DE', 'templateRef' => 'message-template'];
    $config['slots'] = json_encode($slots);
    $GLOBALS['wpdb']->tables['test_veratag_text_templates'][] = ['ref' => 'message-template', 'texts' => '{"de":"Template DE"}'];
    same('Custom DE', fixture_selected(fixture_loader()->loadForCurrentPage())['disclosureGeneralMessage']);
});
test('templates: extractTemplateRefs deduplicates references and ignores custom-only slots', static function (): void {
    $slots = [
        'a' => ['en' => ['templateRef' => 't1'], 'de' => ['templateRef' => 't1']],
        'b' => ['en' => ['custom' => 'text'], 'de' => ['templateRef' => 't2']],
    ];
    same(['t1', 't2'], ConfigurationsRepository::extractTemplateRefs($slots));
});
