<?php

declare(strict_types=1);

$visitorHtmlCases = [
    'German visitor gets German' => ['de-DE','en','de'],
    'English visitor gets English' => ['en-US','de','en'],
    'Austrian visitor gets base German' => ['de-AT','en','de'],
    'French visitor receives English fallback' => ['fr-FR','en','en'],
    'higher-quality language wins' => ['en;q=0.3,de;q=0.8','en','de'],
    'q zero skips German' => ['de;q=0,en;q=1','de','en'],
];
foreach ($visitorHtmlCases as $name => [$header,$fallback,$expected]) {
    test("frontend full pipeline: {$name}", static function () use ($header, $fallback, $expected): void {
        $html = fixture_actual_page_html([fixture_entry()], $header, $fallback);
        same(1, substr_count($html, 'id="disclosure-data"'));
        $data = fixture_extract_disclosure_script($html);
        same('https://site.example.test', $data['site']);
        same('https://site.example.test/article/', $data['page']);
        same($expected, $data['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureLanguage']);
        same(fixture_slots($expected)['disclosure_message'], $data['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureGeneralMessage']);
        same('Note ' . $expected, $data['contentRecords'][0]['disclosure']['disclosurePlacements'][0]['params']['nested']['label']);
        // The renderer requires an empty JSON object, not [] or a missing property.
        truth(str_contains($html, '"content":{}'), 'Empty placement content must serialize as an object');
        truth((bool) preg_match('~<script type="application/json" id="disclosure-data">(.*?)</script>~s', $html, $script_match));
        $decoded_object = json_decode($script_match[1], false, 512, JSON_THROW_ON_ERROR);
        truth(
            $decoded_object->contentRecords[0]->disclosure->disclosurePlacements[0]->params->content instanceof stdClass,
            'Exactly the first disclosure placement content property must be a JSON object'
        );
    });
}
test('frontend full pipeline: missing fallback prevents HTML injection', static function (): void {
    $entry = fixture_entry();
    unset($entry['byLanguage']['en']);
    same('', fixture_actual_page_html([$entry], 'de-DE', 'en'));
});
test('frontend full pipeline: blank mandatory German field uses English before injection', static function (): void {
    $entry = fixture_entry();
    $entry['byLanguage']['de']['slots']['disclosure_label'] = '   ';
    $result = fixture_extract_disclosure_script(fixture_actual_page_html([$entry]));
    same('en', $result['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureLanguage']);
    same('AI disclosure', $result['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureLabel']);
});
test('frontend full pipeline: missing placement produces no HTML', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['slotBindings'] = ['nested.label' => 'not-found'];
    same('', fixture_actual_page_html([$entry]));
});
test('frontend full pipeline: no page match produces no HTML', static function (): void {
    same('', fixture_actual_page_html([fixture_entry('/other/')], 'en-US'));
});
test('frontend full pipeline: only matching page entries embedded', static function (): void {
    $result = fixture_extract_disclosure_script(fixture_actual_page_html([fixture_entry('/article/', 'a'),fixture_entry('/other/', 'b')]));
    same(1, count($result['contentRecords']));
    same('a', $result['contentRecords'][0]['contentIdentification']['contentId']);
});
test('frontend full pipeline: absolute origin mismatch does not leak disclosures', static function (): void {
    same('', fixture_actual_page_html([fixture_entry('https://other.example.test/article/')], 'en-US'));
});
test('frontend full pipeline: path-only entry across origins characterizes current behavior', static function (): void {
    $data = fixture_extract_disclosure_script(fixture_actual_page_html([fixture_entry('/article/')], 'en', 'en', '/article/', 'https://alternate.example.test'));
    same('https://alternate.example.test', $data['site']);
    same(1, count($data['contentRecords']));
});
test('frontend full pipeline: malicious text remains inside JSON, not an HTML script', static function (): void {
    $entry = fixture_entry();
    $entry['byLanguage']['en']['slots']['disclosure_message'] = '</script><script>attack()</script>';
    $html = fixture_actual_page_html([$entry], 'en-US');
    same(1, substr_count($html, '<script '));
    truth(!str_contains($html, '</script><script>attack()'));
    $data = fixture_extract_disclosure_script($html);
    same('</script><script>attack()</script>', $data['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureGeneralMessage']);
});

test('frontend full pipeline: absolute backend page URL on same origin injects payload', static function (): void {
    $html = fixture_actual_page_html([fixture_entry('https://site.example.test/article/', 'absolute-entry')], 'de-DE');
    $data = fixture_extract_disclosure_script($html);
    same('https://site.example.test/article/', $data['page']);
    same('absolute-entry', $data['contentRecords'][0]['contentIdentification']['contentId']);
    same('de', $data['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureLanguage']);
});

test('frontend full pipeline: language is selected independently for each content record', static function (): void {
    $germanAndEnglish = fixture_entry('/article/', 'multilingual', ['de', 'en']);
    $englishOnly = fixture_entry('/article/', 'english-only', ['en']);
    $data = fixture_extract_disclosure_script(fixture_actual_page_html([$germanAndEnglish, $englishOnly], 'de-AT', 'en'));
    same(2, count($data['contentRecords']));
    $byId = [];
    foreach ($data['contentRecords'] as $record) {
        $byId[$record['contentIdentification']['contentId']] = $record['disclosure']['disclosureInformation'];
    }
    same(['multilingual', 'english-only'], array_keys($byId));
    same('de', $byId['multilingual']['disclosureLanguage']);
    same('Dieser Inhalt enthält KI.', $byId['multilingual']['disclosureGeneralMessage']);
    same('en', $byId['english-only']['disclosureLanguage']);
    same('This content uses AI.', $byId['english-only']['disclosureGeneralMessage']);
});

test('frontend full pipeline: incomplete preferred-language record falls back without changing sibling', static function (): void {
    $incomplete = fixture_entry('/article/', 'fallback-record');
    $incomplete['byLanguage']['de']['slots']['disclosure_message'] = '  ';
    $complete = fixture_entry('/article/', 'preferred-record');
    $data = fixture_extract_disclosure_script(fixture_actual_page_html([$incomplete, $complete], 'de-DE', 'en'));
    same(2, count($data['contentRecords']));
    $byId = [];
    foreach ($data['contentRecords'] as $record) {
        $byId[$record['contentIdentification']['contentId']] = $record['disclosure']['disclosureInformation']['disclosureLanguage'];
    }
    same(['fallback-record' => 'en', 'preferred-record' => 'de'], $byId);
});

test('frontend full pipeline: missing configured fallback skips only affected record', static function (): void {
    $invalid = fixture_entry('/article/', 'invalid-fallback', ['de']);
    $valid = fixture_entry('/article/', 'valid-fallback', ['de', 'en']);
    $html = fixture_actual_page_html([$invalid, $valid], 'de-DE', 'en');
    same(1, substr_count($html, 'id="disclosure-data"'));
    $data = fixture_extract_disclosure_script($html);
    same(['valid-fallback'], array_column(array_column($data['contentRecords'], 'contentIdentification'), 'contentId'));
    same('de', $data['contentRecords'][0]['disclosure']['disclosureInformation']['disclosureLanguage']);
});

test('frontend full pipeline: page query parameters do not return records from another path', static function (): void {
    $data = fixture_extract_disclosure_script(fixture_actual_page_html([
        fixture_entry('/article/', 'article-content'),
        fixture_entry('/other/', 'other-content'),
    ], 'en-US', 'en', '/article/?campaign=synthetic'));
    same('https://site.example.test/article/?campaign=synthetic', $data['page']);
    same(['article-content'], array_column(array_column($data['contentRecords'], 'contentIdentification'), 'contentId'));
});
