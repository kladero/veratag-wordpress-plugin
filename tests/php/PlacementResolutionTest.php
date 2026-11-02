<?php

declare(strict_types=1);

function fixture_prepare(array $entry, array $languages = ['de']): mixed
{
    $loader = fixture_loader();
    $prepared = (new ReflectionMethod($loader, 'prepareContentRecordForFrontend'))->invoke($loader, $entry, $languages, 'en');
    return is_array($prepared) ? $prepared['record'] : $prepared;
}
test('placement: bindings substitute nested text in selected visitor language', static function (): void {
    $entry = fixture_entry();
    $result = fixture_prepare($entry);
    same('Note de', $result['disclosure']['disclosurePlacements'][0]['params']['nested']['label']);
});
test('placement: binding into empty params creates nested dictionary', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['params'] = [];
    $entry['placements'][0]['slotBindings'] = ['composition.box.message.text' => 'disclosure_message'];
    $result = fixture_prepare($entry);
    same('Dieser Inhalt enthält KI.', $result['disclosure']['disclosurePlacements'][0]['params']['composition']['box']['message']['text']);
});
test('placement: nested immutable raw params are not modified', static function (): void {
    $entry = fixture_entry();
    $raw = $entry['placements'][0]['params'];
    fixture_prepare($entry);
    same($raw, $entry['placements'][0]['params']);
});
test('placement: only invalid binding placement is dropped; valid sibling survives', static function (): void {
    $entry = fixture_entry();
    $bad = $entry['placements'][0];
    $bad['slotBindings'] = ['nested.label' => 'missing'];
    $entry['placements'] = [$bad, $entry['placements'][0]];
    $result = fixture_prepare($entry);
    same(1, count($result['disclosure']['disclosurePlacements']));
    same('Note de', $result['disclosure']['disclosurePlacements'][0]['params']['nested']['label']);
});
test('placement: invalid placement name skipped', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['placementName'] = '';
    same(null, fixture_prepare($entry));
});
test('placement: invalid schema name skipped', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['schemaName'] = '';
    same(null, fixture_prepare($entry));
});
test('placement: invalid schema version skipped', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['schemaVersion'] = '';
    same(null, fixture_prepare($entry));
});
test('placement: non-array raw params skipped', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['params'] = 'invalid';
    same(null, fixture_prepare($entry));
});
test('placement: non-array bindings skipped', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['slotBindings'] = 'invalid';
    same(null, fixture_prepare($entry));
});
test('placement: empty object paths preserved when still empty', static function (): void {
    $entry = fixture_entry();
    $result = fixture_prepare($entry);
    truth($result['disclosure']['disclosurePlacements'][0]['params']['content'] instanceof stdClass);
    truth(str_contains(json_encode($result), '"content":{}'));
});
test('placement: known empty renderer object keys preserved as objects', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][0]['params'] = ['compositionAppearance' => [], 'compositionBehaviour' => [], 'children' => []];
    $entry['placements'][0]['slotBindings'] = [];
    $entry['placements'][0]['emptyObjectPaths'] = [];
    $result = fixture_prepare($entry);
    $params = $result['disclosure']['disclosurePlacements'][0]['params'];
    truth($params['compositionAppearance'] instanceof stdClass);
    truth($params['compositionBehaviour'] instanceof stdClass);
    same([], $params['children']);
});
test('placement: slot missing in selected language only drops placement', static function (): void {
    $entry = fixture_entry();
    unset($entry['byLanguage']['de']['slots']['placement_note']);
    $entry['placements'][] = ['placementName' => 'SecondBadge','schemaName' => 'test-schema','schemaVersion' => '1','params' => ['note' => 'fixed'], 'slotBindings' => [],'emptyObjectPaths' => []];
    $result = fixture_prepare($entry);
    same(1, count($result['disclosure']['disclosurePlacements']));
    same('SecondBadge', $result['disclosure']['disclosurePlacements'][0]['placementName']);
});
test('placement: no language data produces no record', static function (): void {
    $entry = fixture_entry();
    $entry['byLanguage'] = [];
    same(null, fixture_prepare($entry));
});

test('placement: two distinct stored placements survive real repository loading', static function (): void {
    $entry = fixture_entry();
    $entry['placements'][] = [
        'placementName' => 'SyntheticSecondaryBadge',
        'schemaName' => 'test-schema', 'schemaVersion' => '1',
        'params' => ['message' => 'literal'],
        'slotBindings' => [], 'emptyObjectPaths' => [],
    ];
    $result = fixture_render([$entry]);
    same(2, count($result['contentRecords'][0]['disclosure']['disclosurePlacements']));
    same(['SyntheticBadge', 'SyntheticSecondaryBadge'], array_column($result['contentRecords'][0]['disclosure']['disclosurePlacements'], 'placementName'));
});
