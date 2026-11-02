<?php

declare(strict_types=1);

$payloadCases = [
    'German visit' => ['de-DE', 'visitor_preferred', 'en', 'de'],
    'English visit' => ['en-US', 'visitor_preferred', 'de', 'en'],
    'Unknown language fallback' => ['fr-FR', 'visitor_preferred', 'en', 'en'],
    'Visitor regional variant' => ['de-AT', 'visitor_preferred', 'en', 'de'],
    'Visitor multi-priority' => ['fr;q=0.9,de;q=0.8,en;q=0.1', 'visitor_preferred', 'en', 'de'],
    'Visitor English preferred' => ['en;q=0.9,de;q=0.8', 'visitor_preferred', 'de', 'en'],
    'Visitor zero-q German omitted' => ['de;q=0,en;q=1', 'visitor_preferred', 'de', 'en'],
    'Server German despite visitor English' => ['en', 'server_preferred', 'en', 'de'],
];
foreach ($payloadCases as $name => [$header,$mode,$fallback,$expected]) {
    test("payload: {$name}", static function () use ($header, $mode, $fallback, $expected): void {
        // Supply the WordPress locale before the first and only real pipeline call.
        $result = fixture_render([fixture_entry()], $header, $mode, $fallback, '/article/', 'https://site.example.test', 'de_DE');
        truth(!is_wp_error($result), 'Payload should be successful');
        same($expected, fixture_selected($result)['disclosureLanguage']);
        same(fixture_slots($expected)['disclosure_message'], fixture_selected($result)['disclosureGeneralMessage']);
        same(fixture_slots($expected)['disclosure_label'], fixture_selected($result)['disclosureLabel']);
        same(fixture_slots($expected)['disclosure_acronym'], fixture_selected($result)['disclosureAcronym']);
        same("Note {$expected}", $result['contentRecords'][0]['disclosure']['disclosurePlacements'][0]['params']['nested']['label']);
        same('constant', $result['contentRecords'][0]['disclosure']['disclosurePlacements'][0]['params']['nested']['other']);
        same('https://site.example.test', $result['site']);
    });
}
foreach (['disclosure_acronym', 'disclosure_label', 'disclosure_message'] as $required) {
    test("payload: blank German {$required} falls back to English", static function () use ($required): void {
        $entry = fixture_entry();
        $entry['byLanguage']['de']['slots'][$required] = '  ';
        $result = fixture_render([$entry]);
        same('en', fixture_selected($result)['disclosureLanguage']);
        same('This content uses AI.', fixture_selected($result)['disclosureGeneralMessage']);
    });
    test("payload: absent fallback {$required} skips record", static function () use ($required): void {
        $entry = fixture_entry();
        unset($entry['byLanguage']['en']['slots'][$required]);
        same([], fixture_render([$entry]));
    });
}
test('payload: German chosen but fallback translation missing skips record', static function (): void {
    $entry = fixture_entry();
    unset($entry['byLanguage']['en']);
    same([], fixture_render([$entry], 'de-DE', 'visitor_preferred', 'en'));
});
test('payload: missing placements skips record', static function (): void {
    $entry = fixture_entry();
    $entry['placements'] = [];
    same([], fixture_render([$entry]));
});
test('payload: binding referring to absent language slot drops placement', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['slotBindings'] = ['nested.label' => 'does_not_exist'];
    same([], fixture_render([$entry]));
});
test('payload: missing content id creates WP_Error', static function (): void {
    $entry = fixture_entry();
    $entry['contentId'] = '';
    // Don't test invalid fixture DB reference; directly exercise production entry preparation using reflection.
    $loader = fixture_loader();
    $method = new ReflectionMethod($loader, 'prepareContentRecordForFrontend');
    error_code('veratag_frontend_serving_missing_entry_identification', $method->invoke($loader, $entry, ['de'], 'en'));
});
foreach (['contentType', 'contentHtmlTag', 'disclosureType'] as $field) {
    test("payload: missing {$field} creates WP_Error", static function () use ($field): void {
        $entry = fixture_entry();
        $entry[$field] = '';
        $loader = fixture_loader();
        $result = (new ReflectionMethod($loader, 'prepareContentRecordForFrontend'))->invoke($loader, $entry, ['de'], 'en');
        error_code('veratag_frontend_serving_missing_entry_identification', $result);
    });
}
test('payload: XSS content is represented as data and not executed markup', static function (): void {
    $entry = fixture_entry();
    $entry['byLanguage']['en']['slots']['disclosure_message'] = '<script>alert("TEST")</script> & hi';
    $result = fixture_render([$entry], 'en-US');
    same('<script>alert("TEST")</script> & hi', fixture_selected($result)['disclosureGeneralMessage']);
    $inject = new VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataInjectionService();
    ob_start();
    $inject->injectDisclosureDataScript($result);
    $markup = ob_get_clean();
    truth(!str_contains($markup, '<script>alert'), 'Inline attacker script must not appear unescaped');
    truth(str_contains($markup, '\\u003Cscript\\u003E'), 'JSON hex escaping must be in injected payload');
});
test('payload: empty fixture produces no output', static function (): void {
    same([], fixture_render([]));
});
