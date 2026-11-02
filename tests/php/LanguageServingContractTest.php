<?php

declare(strict_types=1);

/**
 * Contract-level offline checks for the actual PHP page -> language -> HTML JSON pipeline.
 * Fixtures and assertions are intentionally independent of the language decider's output.
 */
function serving_record_map(string $html): array
{
    $records = fixture_extract_disclosure_script($html)['contentRecords'];
    $indexed = [];
    foreach ($records as $record) {
        $id = $record['contentIdentification']['contentId'];
        truth(!isset($indexed[$id]), "Duplicate shipped content ID: {$id}");
        $indexed[$id] = $record['disclosure'];
    }
    return $indexed;
}

$servingSelectionCases = [
    // name => Accept-Language, configured fallback, available translations, expected chosen language
    'exact regional variant beats base language' => ['de-DE', 'en', ['de','de-DE','en'], 'de-DE'],
    'regional Austrian request uses German base when exact absent' => ['de-AT', 'en', ['de','en'], 'de'],
    'case and separator normalization chooses stored locale spelling' => ['DE-at', 'en', ['de_AT','en'], 'de_AT'],
    'highest weighted valid language outranks header order' => ['en;q=0.2, de;q=0.9', 'en', ['en','de'], 'de'],
    'equal weights preserve user order' => ['fr;q=0.6,en;q=0.6,de;q=0.6', 'de', ['en','de','fr'], 'fr'],
    'first unavailable preference lets second one win' => ['es;q=0.9,fr;q=0.8,de;q=0.1', 'en', ['en','fr','de'], 'fr'],
    'all available preferences disabled with zero q use fallback' => ['de;q=0,fr;q=0', 'en', ['en','de','fr'], 'en'],
    'unrecognized preference uses channel fallback' => ['it-IT', 'de', ['de','en'], 'de'],
    'missing browser header uses channel fallback' => ['', 'en', ['en','de'], 'en'],
    'configured regional fallback can resolve to stored base language' => ['it', 'en-GB', ['en','de'], 'en'],
    'exact configured fallback preferred over base when preferences unavailable' => ['it', 'en-GB', ['en','en-GB','de'], 'en-GB'],
];
foreach ($servingSelectionCases as $name => [$header, $fallback, $languages, $expected]) {
    test("language shipping: {$name}", static function () use ($header, $fallback, $languages, $expected): void {
        $html = fixture_actual_page_html([fixture_entry('/article/', 'specific-record', $languages)], $header, $fallback);
        same(1, substr_count($html, 'id="disclosure-data"'));
        $records = serving_record_map($html);
        same(['specific-record'], array_keys($records));
        $information = $records['specific-record']['disclosureInformation'];
        same($expected, $information['disclosureLanguage']);
        same(fixture_slots($expected)['disclosure_acronym'], $information['disclosureAcronym']);
        same(fixture_slots($expected)['disclosure_label'], $information['disclosureLabel']);
        same(fixture_slots($expected)['disclosure_message'], $information['disclosureGeneralMessage']);
        same('Note ' . $expected, $records['specific-record']['disclosurePlacements'][0]['params']['nested']['label']);
    });
}

foreach (['disclosure_acronym', 'disclosure_label', 'disclosure_message'] as $mandatory) {
    test("language shipping: incomplete preferred {$mandatory} uses next preferred before fallback", static function () use ($mandatory): void {
        $entry = fixture_entry('/article/', 'multi-candidate', ['de','fr','en']);
        $entry['byLanguage']['de']['slots'][$mandatory] = "  \t";
        $html = fixture_actual_page_html([$entry], 'de;q=1,fr;q=0.8,en;q=0.5', 'en');
        $record = serving_record_map($html)['multi-candidate'];
        same('fr', $record['disclosureInformation']['disclosureLanguage']);
        same('Ce contenu utilise IA.', $record['disclosureInformation']['disclosureGeneralMessage']);
        same('Note fr', $record['disclosurePlacements'][0]['params']['nested']['label']);
    });
    test("language shipping: incomplete fallback {$mandatory} suppresses even complete preferred text", static function () use ($mandatory): void {
        $entry = fixture_entry('/article/', 'must-have-fallback');
        unset($entry['byLanguage']['en']['slots'][$mandatory]);
        same('', fixture_actual_page_html([$entry], 'de-DE', 'en'));
    });
}

test('language shipping: all three disclosure information fields must originate from the same selected language', static function (): void {
    $entry = fixture_entry('/article/', 'distinct-messages', ['de', 'en']);
    $entry['byLanguage']['de']['slots']['disclosure_acronym'] = 'DE-ACRONYM-UNIQUE';
    $entry['byLanguage']['de']['slots']['disclosure_label'] = 'DE-LABEL-UNIQUE';
    $entry['byLanguage']['de']['slots']['disclosure_message'] = 'DE-MESSAGE-UNIQUE';
    $entry['byLanguage']['de']['slots']['placement_note'] = 'DE-PLACEMENT-UNIQUE';
    $entry['byLanguage']['en']['slots']['disclosure_acronym'] = 'EN-ACRONYM-UNIQUE';
    $entry['byLanguage']['en']['slots']['disclosure_label'] = 'EN-LABEL-UNIQUE';
    $entry['byLanguage']['en']['slots']['disclosure_message'] = 'EN-MESSAGE-UNIQUE';
    $entry['byLanguage']['en']['slots']['placement_note'] = 'EN-PLACEMENT-UNIQUE';
    $html = fixture_actual_page_html([$entry], 'de-AT', 'en');
    $info = serving_record_map($html)['distinct-messages'];
    same([
        'disclosureAcronym' => 'DE-ACRONYM-UNIQUE',
        'disclosureLabel' => 'DE-LABEL-UNIQUE',
        'disclosureGeneralMessage' => 'DE-MESSAGE-UNIQUE',
        'disclosureLanguage' => 'de',
    ], $info['disclosureInformation']);
    same('DE-PLACEMENT-UNIQUE', $info['disclosurePlacements'][0]['params']['nested']['label']);
    truth(!str_contains($html, 'EN-MESSAGE-UNIQUE'), 'No English translation must leak into the German payload');
    truth(!str_contains($html, 'EN-PLACEMENT-UNIQUE'), 'No English placement must leak into the German payload');
});

test('language shipping: PHP produces expected serialized field structure with synthetic placement data', static function (): void {
    // This verifies the PHP payload contract only; it does not validate the
    // synthetic placement against the external JavaScript renderer schema.
    $html = fixture_actual_page_html([fixture_entry('/article/', 'exact-contract')], 'de-DE', 'en');
    $payload = fixture_extract_disclosure_script($html);
    same(['site', 'page', 'contentRecords'], array_keys($payload));
    same('https://site.example.test', $payload['site']);
    same('https://site.example.test/article/', $payload['page']);
    same(1, count($payload['contentRecords']));
    same([
        'contentIdentification' => [
            'contentId' => 'exact-contract',
            'contentType' => 'image',
            'contentHtmlTag' => 'img',
        ],
        'disclosure' => [
            'type' => 'ai_generated',
            'disclosureInformation' => [
                'disclosureAcronym' => 'KI',
                'disclosureLabel' => 'KI-Hinweis',
                'disclosureGeneralMessage' => 'Dieser Inhalt enthält KI.',
                'disclosureLanguage' => 'de',
            ],
            'disclosurePlacements' => [[
                'placementName' => 'SyntheticBadge',
                'placementConfigSchema' => ['schemaName' => 'test-schema', 'schemaVersion' => '1'],
                'params' => [
                    'nested' => ['label' => 'Note de', 'other' => 'constant'],
                    'content' => [], // json_decode(...,true) loses {} shape; verify that separately below.
                ],
            ]],
        ],
    ], $payload['contentRecords'][0]);
    $match = [];
    truth((bool) preg_match('~<script type="application/json" id="disclosure-data">(.*?)</script>~s', $html, $match));
    $object = json_decode($match[1], false, 512, JSON_THROW_ON_ERROR);
    truth($object->contentRecords[0]->disclosure->disclosurePlacements[0]->params->content instanceof stdClass);
});

test('language shipping: server preference ignores higher priority browser language in final HTML', static function (): void {
    $payload = fixture_render([fixture_entry()], 'en-US,en;q=1', 'server_preferred', 'en', '/article/', 'https://site.example.test', 'de_DE');
    truth(is_array($payload) && $payload !== []);
    ob_start();
    (new \VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataInjectionService())->injectDisclosureDataScript($payload);
    $html = (string) ob_get_clean();
    $record = serving_record_map($html)['entry-1'];
    same('de', $record['disclosureInformation']['disclosureLanguage']);
    same('Dieser Inhalt enthält KI.', $record['disclosureInformation']['disclosureGeneralMessage']);
});

test('language shipping: one page may ship different selected languages for different content items', static function (): void {
    $entries = [
        fixture_entry('/article/', 'german-and-english', ['de','en']),
        fixture_entry('/article/', 'french-and-english', ['fr','en']),
        fixture_entry('/article/', 'english-only', ['en']),
    ];
    $byId = serving_record_map(fixture_actual_page_html($entries, 'de;q=1,fr;q=0.8,en;q=0.1', 'en'));
    same(['german-and-english', 'french-and-english', 'english-only'], array_keys($byId));
    same('de', $byId['german-and-english']['disclosureInformation']['disclosureLanguage']);
    same('fr', $byId['french-and-english']['disclosureInformation']['disclosureLanguage']);
    same('en', $byId['english-only']['disclosureInformation']['disclosureLanguage']);
    same('Note de', $byId['german-and-english']['disclosurePlacements'][0]['params']['nested']['label']);
    same('Note fr', $byId['french-and-english']['disclosurePlacements'][0]['params']['nested']['label']);
    same('Note en', $byId['english-only']['disclosurePlacements'][0]['params']['nested']['label']);
});

test('language shipping: a skipped entry before valid entries does not suppress their HTML output', static function (): void {
    $bad = fixture_entry('/article/', 'invalid-before', ['de']);
    $valid = fixture_entry('/article/', 'valid-after', ['de','en']);
    same(['valid-after'], array_keys(serving_record_map(fixture_actual_page_html([$bad, $valid], 'de-DE', 'en'))));
});

test('language shipping: a skipped entry after valid entries does not corrupt prior HTML output', static function (): void {
    $valid = fixture_entry('/article/', 'valid-before', ['de','en']);
    $bad = fixture_entry('/article/', 'invalid-after', ['de']);
    same(['valid-before'], array_keys(serving_record_map(fixture_actual_page_html([$valid, $bad], 'de-DE', 'en'))));
});

test('language shipping: when no record is renderable no disclosure-data element is emitted', static function (): void {
    $missing_fallback = fixture_entry('/article/', 'not-renderable', ['de']);
    $different_page = fixture_entry('/unrelated/', 'not-on-page', ['de','en']);
    same('', fixture_actual_page_html([$missing_fallback, $different_page], 'de-DE', 'en'));
});

test('language shipping: two placement binding paths receive the chosen language and preserve static params', static function (): void {
    $entry = fixture_entry('/article/', 'two-placements');
    $entry['placements'][] = [
        'placementName' => 'OtherBadge', 'schemaName' => 'second-schema', 'schemaVersion' => '2',
        'params' => ['first' => ['text' => 'unresolved'], 'second' => ['acronym' => 'unresolved'], 'static' => 'same'],
        'slotBindings' => ['first.text' => 'disclosure_message', 'second.acronym' => 'disclosure_acronym'],
        'emptyObjectPaths' => [],
    ];
    $records = serving_record_map(fixture_actual_page_html([$entry], 'en-US', 'de'));
    $placements = $records['two-placements']['disclosurePlacements'];
    same(2, count($placements));
    same('Note en', $placements[0]['params']['nested']['label']);
    same('This content uses AI.', $placements[1]['params']['first']['text']);
    same('AI', $placements[1]['params']['second']['acronym']);
    same('same', $placements[1]['params']['static']);
    same('en', $records['two-placements']['disclosureInformation']['disclosureLanguage']);
});

test('language shipping: missing placement-only translation skips that placement but serves its valid sibling', static function (): void {
    $entry = fixture_entry('/article/', 'partial-placements');
    unset($entry['byLanguage']['de']['slots']['placement_note']);
    $entry['placements'][] = [
        'placementName' => 'PlainBadge', 'schemaName' => 'plain', 'schemaVersion' => '1',
        'params' => ['text' => 'unchanged'], 'slotBindings' => [], 'emptyObjectPaths' => [],
    ];
    $record = serving_record_map(fixture_actual_page_html([$entry], 'de-DE', 'en'))['partial-placements'];
    same('de', $record['disclosureInformation']['disclosureLanguage']);
    same(['PlainBadge'], array_column($record['disclosurePlacements'], 'placementName'));
    same('unchanged', $record['disclosurePlacements'][0]['params']['text']);
});

test('language shipping: current placement-only gap prevents serving despite complete fallback translation', static function (): void {
    $entry = fixture_entry('/article/', 'placement-gap');
    // The three fixed disclosure fields are complete, so the decider picks DE;
    // only afterwards does the placement fail its DE-only binding. Current
    // production behavior is to skip the record, not try the EN fallback.
    unset($entry['byLanguage']['de']['slots']['placement_note']);
    same('', fixture_actual_page_html([$entry], 'de-DE', 'en'));
    // Characterization of current behavior, not an endorsement: see tests/TEST_RESULTS.md.
});

test('language shipping: placement-only missing text is the actual cause of omission, not an unrelated page miss', static function (): void {
    $entry = fixture_entry('/article/', 'isolated-placement-missing');
    unset($entry['byLanguage']['de']['slots']['placement_note']);
    // This known current limitation must be reproducible with the SAME
    // page, content ID, configuration, and available fallback translation.
    same('', fixture_actual_page_html([$entry], 'de-DE', 'en'));

    // Requesting a fully usable fallback translation ships this exact record,
    // proving the negative case was not just an invalid fixture/page lookup.
    $fallbackResult = serving_record_map(fixture_actual_page_html([$entry], 'en-US', 'en'));
    same(['isolated-placement-missing'], array_keys($fallbackResult));
    same('en', $fallbackResult['isolated-placement-missing']['disclosureInformation']['disclosureLanguage']);
    same('Note en', $fallbackResult['isolated-placement-missing']['disclosurePlacements'][0]['params']['nested']['label']);
});

test('language shipping: missing mandatory fallback field is the actual cause of omission', static function (): void {
    $entry = fixture_entry('/article/', 'fallback-required-control');
    unset($entry['byLanguage']['en']['slots']['disclosure_message']);
    same('', fixture_actual_page_html([$entry], 'de-DE', 'en'));

    $entry['byLanguage']['en']['slots']['disclosure_message'] = 'Recovered English fallback';
    $restored = serving_record_map(fixture_actual_page_html([$entry], 'de-DE', 'en'));
    same(['fallback-required-control'], array_keys($restored));
    same('de', $restored['fallback-required-control']['disclosureInformation']['disclosureLanguage']);
    same('Dieser Inhalt enthält KI.', $restored['fallback-required-control']['disclosureInformation']['disclosureGeneralMessage']);
});

test('language shipping: missing fallback placement translation does not block otherwise renderable preferred translation', static function (): void {
    $entry = fixture_entry('/article/', 'fallback-placement-gap');
    unset($entry['byLanguage']['en']['slots']['placement_note']);
    $record = serving_record_map(fixture_actual_page_html([$entry], 'de-DE', 'en'))['fallback-placement-gap'];
    same('de', $record['disclosureInformation']['disclosureLanguage']);
    same('Note de', $record['disclosurePlacements'][0]['params']['nested']['label']);
});

test('language shipping: configured fallback must exist even when preferred translation and placement are usable', static function (): void {
    $entry = fixture_entry('/article/', 'preferred-only', ['de']);
    same('', fixture_actual_page_html([$entry], 'de-DE', 'en'));
});

test('language shipping: malformed or empty channel fallback produces no HTML', static function (): void {
    foreach (['', ' ', 'unknown'] as $fallback) {
        same('', fixture_actual_page_html([fixture_entry()], 'de-DE', $fallback), "fallback {$fallback}");
    }
});

test('language shipping: no disclosure from unrelated page or different absolute hostname', static function (): void {
    $entries = [
        fixture_entry('/unrelated/', 'other-path'),
        fixture_entry('https://other.example.test/article/', 'other-host'),
        fixture_entry('/article/', 'correct'),
    ];
    same(['correct'], array_keys(serving_record_map(fixture_actual_page_html($entries, 'de-DE', 'en'))));
});

test('language shipping: URL query parameters retain the correct page context without leaking other content', static function (): void {
    $entries = [fixture_entry('/article/', 'article'), fixture_entry('/different/', 'different')];
    $html = fixture_actual_page_html($entries, 'de-DE', 'en', '/article/?ref=synthetic');
    $data = fixture_extract_disclosure_script($html);
    same('https://site.example.test/article/?ref=synthetic', $data['page']);
    same(['article'], array_keys(serving_record_map($html)));
});

test('language shipping: undisclosed records are not emitted even when configuration exists', static function (): void {
    reset_fixtures();
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry('/article/', 'undisclosed'));
    $GLOBALS['wpdb']->tables['test_veratag_content_items'][0]['is_disclosed'] = 0;
    same([], fixture_loader()->loadForCurrentPage());
});

test('language shipping: a stored content record whose configuration is missing is omitted', static function (): void {
    reset_fixtures();
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry('/article/', 'missing-config'));
    $GLOBALS['wpdb']->tables['test_veratag_configurations'] = [];
    same([], fixture_loader()->loadForCurrentPage());
});

test('language shipping: server locale regional key is resolved in the assembled PHP payload', static function (): void {
    $result = fixture_render([fixture_entry('/article/', 'server-regional')], 'fr-FR', 'server_preferred', 'en', '/article/', 'https://site.example.test', 'de_AT');
    same('de', $result['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureLanguage']);
    same('Dieser Inhalt enthält KI.', $result['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureGeneralMessage']);
});

test('language shipping: actual markup safely serializes the selected translated text', static function (): void {
    $entry = fixture_entry('/article/', 'escaping');
    $entry['byLanguage']['de']['slots']['disclosure_message'] = '</script><script>alert("synthetic")</script>';
    $html = fixture_actual_page_html([$entry], 'de-DE', 'en');
    same(1, substr_count($html, '<script '));
    truth(!str_contains($html, '</script><script>alert'));
    same('</script><script>alert("synthetic")</script>', serving_record_map($html)['escaping']['disclosureInformation']['disclosureGeneralMessage']);
});

test('language shipping: later visitor request cannot reuse the previous request language', static function (): void {
    $entries = [fixture_entry('/article/', 'switching-visitor')];
    $german = serving_record_map(fixture_actual_page_html($entries, 'de-DE', 'en'))['switching-visitor'];
    $english = serving_record_map(fixture_actual_page_html($entries, 'en-US', 'en'))['switching-visitor'];
    same('de', $german['disclosureInformation']['disclosureLanguage']);
    same('en', $english['disclosureInformation']['disclosureLanguage']);
    same('Dieser Inhalt enthält KI.', $german['disclosureInformation']['disclosureGeneralMessage']);
    same('This content uses AI.', $english['disclosureInformation']['disclosureGeneralMessage']);
});

test('language shipping: non-string mandatory field is unusable and falls back for that content', static function (): void {
    $entry = fixture_entry('/article/', 'non-string-message');
    $entry['byLanguage']['de']['slots']['disclosure_message'] = 123;
    $record = serving_record_map(fixture_actual_page_html([$entry], 'de-DE', 'en'))['non-string-message'];
    same('en', $record['disclosureInformation']['disclosureLanguage']);
    same('This content uses AI.', $record['disclosureInformation']['disclosureGeneralMessage']);
    same('Note en', $record['disclosurePlacements'][0]['params']['nested']['label']);
});

test('language shipping: mandatory text trims its own whitespace without changing the language', static function (): void {
    $entry = fixture_entry('/article/', 'trimmed-message');
    $entry['byLanguage']['de']['slots']['disclosure_message'] = '  Deutsche Nachricht  ';
    $record = serving_record_map(fixture_actual_page_html([$entry], 'de-DE', 'en'))['trimmed-message'];
    same('de', $record['disclosureInformation']['disclosureLanguage']);
    same('Deutsche Nachricht', $record['disclosureInformation']['disclosureGeneralMessage']);
});

test('language shipping: unusable exact regional match proceeds to usable base key', static function (): void {
    $entry = fixture_entry('/article/', 'regional-fallback', ['de-AT','de','en']);
    $entry['byLanguage']['de-AT']['slots']['disclosure_message'] = '';
    $record = serving_record_map(fixture_actual_page_html([$entry], 'de-AT', 'en'))['regional-fallback'];
    same('de', $record['disclosureInformation']['disclosureLanguage']);
    same('Dieser Inhalt enthält KI.', $record['disclosureInformation']['disclosureGeneralMessage']);
});

test('language shipping characterization: a regional request can select a different regional translation of the same base', static function (): void {
    // Current selector behavior: de-AT may match de-DE through its base 'de'.
    // This is not a claim that the two regional variants are equivalent.
    $entry = fixture_entry('/article/', 'regional-peer', ['de-DE', 'en']);
    $record = serving_record_map(fixture_actual_page_html([$entry], 'de-AT', 'en'))['regional-peer'];
    same('de-DE', $record['disclosureInformation']['disclosureLanguage']);
    same('Message de-DE', $record['disclosureInformation']['disclosureGeneralMessage']);
    same('Note de-DE', $record['disclosurePlacements'][0]['params']['nested']['label']);
});

test('language shipping characterization: regional fallback can select a different regional key sharing its base', static function (): void {
    $entry = fixture_entry('/article/', 'fallback-region-peer', ['de', 'en-US']);
    $record = serving_record_map(fixture_actual_page_html([$entry], 'it', 'en-GB'))['fallback-region-peer'];
    same('en-US', $record['disclosureInformation']['disclosureLanguage']);
    same('Message en-US', $record['disclosureInformation']['disclosureGeneralMessage']);
    same('Note en-US', $record['disclosurePlacements'][0]['params']['nested']['label']);
});

test('language shipping: a generic language request does not adopt an unrelated regional translation', static function (): void {
    $entry = fixture_entry('/article/', 'region-only', ['de-AT', 'en']);
    $record = serving_record_map(fixture_actual_page_html([$entry], 'de', 'en'))['region-only'];
    same('en', $record['disclosureInformation']['disclosureLanguage']);
    same('This content uses AI.', $record['disclosureInformation']['disclosureGeneralMessage']);
});

test('language shipping: a stored URL with one query string currently matches another query on the same path', static function (): void {
    $entry = fixture_entry('https://site.example.test/article/?variant=one', 'query-specific');
    $record = serving_record_map(fixture_actual_page_html([$entry], 'de-DE', 'en', '/article/?variant=two'))['query-specific'];
    same('de', $record['disclosureInformation']['disclosureLanguage']);
    // The current matching policy discards query parameters. This is a
    // characterization, not proof that cross-variant matches are desirable.
});

test('language shipping: malformed middle record is skipped while valid German and English records ship', static function (): void {
    $first = fixture_entry('/article/', 'valid-german', ['de', 'en']);
    $invalid = fixture_entry('/article/', 'bad-identification');
    $invalid['contentHtmlTag'] = '';
    $last = fixture_entry('/article/', 'valid-english', ['en']);

    $html = fixture_actual_page_html([$first, $invalid, $last], 'de-DE', 'en');
    same(1, substr_count($html, 'id="disclosure-data"'));
    $payload = fixture_extract_disclosure_script($html);
    same('https://site.example.test/article/', $payload['page']);
    same(['valid-german', 'valid-english'], array_column(array_column($payload['contentRecords'], 'contentIdentification'), 'contentId'));

    $by_id = serving_record_map($html);
    same('de', $by_id['valid-german']['disclosureInformation']['disclosureLanguage']);
    same('Dieser Inhalt enthält KI.', $by_id['valid-german']['disclosureInformation']['disclosureGeneralMessage']);
    same('Note de', $by_id['valid-german']['disclosurePlacements'][0]['params']['nested']['label']);
    same('en', $by_id['valid-english']['disclosureInformation']['disclosureLanguage']);
    same('This content uses AI.', $by_id['valid-english']['disclosureInformation']['disclosureGeneralMessage']);
    same('Note en', $by_id['valid-english']['disclosurePlacements'][0]['params']['nested']['label']);
});

foreach (['before' => 0, 'between' => 1, 'after' => 2] as $position => $bad_index) {
    test("language shipping: malformed identification {$position} valid records does not block their delivery", static function () use ($bad_index): void {
        $invalid = fixture_entry('/article/', 'bad-identification');
        $invalid['contentHtmlTag'] = '   ';
        $entries = [fixture_entry('/article/', 'valid-a'), fixture_entry('/article/', 'valid-c')];
        array_splice($entries, $bad_index, 0, [$invalid]);

        $html = fixture_actual_page_html($entries, 'de-DE', 'en');
        same(1, substr_count($html, 'id="disclosure-data"'));
        same(['valid-a', 'valid-c'], array_keys(serving_record_map($html)));
    });
}

foreach (['contentId', 'contentType', 'contentHtmlTag', 'disclosureType'] as $required_field) {
    test("language shipping: blank {$required_field} skips only its record", static function () use ($required_field): void {
        $invalid = fixture_entry('/article/', 'bad-record');
        $invalid[$required_field] = '';
        $valid = fixture_entry('/article/', 'good-record');
        $html = fixture_actual_page_html([$invalid, $valid], 'de-DE', 'en');
        same(['good-record'], array_keys(serving_record_map($html)));
    });
}

test('language shipping: entirely malformed page emits no disclosure script', static function (): void {
    $bad_a = fixture_entry('/article/', 'bad-a');
    $bad_a['contentHtmlTag'] = '';
    $bad_b = fixture_entry('/article/', 'bad-b');
    $bad_b['disclosureType'] = '';
    same('', fixture_actual_page_html([$bad_a, $bad_b], 'de-DE', 'en'));
});

test('language shipping: a valid entry is served when an unrelated undisclosed record exists on the page', static function (): void {
    reset_fixtures();
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry('/article/', 'should-be-hidden'));
    fixture_add_entry(fixture_entry('/article/', 'should-be-served'));
    $GLOBALS['wpdb']->tables['test_veratag_content_items'][0]['is_disclosed'] = 0;
    $result = fixture_loader()->loadForCurrentPage();
    truth(is_array($result) && isset($result['contentRecords']));
    same(['should-be-served'], array_column(array_column($result['contentRecords'], 'contentIdentification'), 'contentId'));
});
