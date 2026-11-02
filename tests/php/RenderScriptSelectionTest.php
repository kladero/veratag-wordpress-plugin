<?php

declare(strict_types=1);

/** A placement's render script is requested only when that placement is embedded on the visited page. */
function fixture_entry_with_script(string $page, string $id, string $file = 'badge.js', string $version = '1.0.0'): array
{
    $entry = fixture_entry($page, $id);
    $entry['placements'][0]['renderScriptFileName'] = $file;
    $entry['placements'][0]['renderScriptVersion'] = $version;
    return $entry;
}
test('render scripts: page with a disclosure requests the script of its placement', static function (): void {
    same(
        [['file_name' => 'badge.js', 'required_version' => '1.0.0']],
        fixture_required_render_scripts([fixture_entry_with_script('/article/', 'a')])
    );
});
test('render scripts: page without disclosures requests no script even if other pages need one', static function (): void {
    same([], fixture_required_render_scripts([fixture_entry_with_script('/other-page/', 'a')], '/article/'));
});
test('render scripts: only scripts of the visited page are requested', static function (): void {
    $entries = [
        fixture_entry_with_script('/article/', 'a', 'badge.js', '1.0.0'),
        fixture_entry_with_script('/other-page/', 'b', 'panel.js', '2.0.0'),
    ];
    same([['file_name' => 'badge.js', 'required_version' => '1.0.0']], fixture_required_render_scripts($entries, '/article/'));
    same([['file_name' => 'panel.js', 'required_version' => '2.0.0']], fixture_required_render_scripts($entries, '/other-page/'));
});
test('render scripts: placement without a render script requests nothing', static function (): void {
    same([], fixture_required_render_scripts([fixture_entry('/article/', 'a')]));
});
test('render scripts: placement dropped for a missing bound slot does not request its script', static function (): void {
    $entry = fixture_entry_with_script('/article/', 'a');
    $entry['placements'][0]['slotBindings'] = ['nested.label' => 'slot_that_does_not_exist'];
    same([], fixture_required_render_scripts([$entry]));
});
test('render scripts: only the kept sibling placement requests its script', static function (): void {
    $kept = fixture_entry_with_script('/article/', 'a', 'kept.js', '1.0.0')['placements'][0];
    $dropped = fixture_entry_with_script('/article/', 'a', 'dropped.js', '1.0.0')['placements'][0];
    $dropped['slotBindings'] = ['nested.label' => 'slot_that_does_not_exist'];
    $entry = fixture_entry('/article/', 'a');
    $entry['placements'] = [$dropped, $kept];
    same([['file_name' => 'kept.js', 'required_version' => '1.0.0']], fixture_required_render_scripts([$entry]));
});
test('render scripts: entry without any usable language requests no script', static function (): void {
    $entry = fixture_entry_with_script('/article/', 'a');
    $entry['byLanguage'] = ['de' => ['slots' => ['disclosure_acronym' => 'KI']]];
    same([], fixture_required_render_scripts([$entry]));
});
test('render scripts: scripts match the data embedded on the page', static function (): void {
    $entries = [fixture_entry_with_script('/article/', 'a'), fixture_entry_with_script('/other-page/', 'b', 'panel.js')];
    reset_fixtures();
    fixture_settings();
    fixture_request('de-DE', '/article/');
    foreach ($entries as $idx => $entry) {
        fixture_add_entry($entry, $idx + 1);
    }
    $page = fixture_loader()->loadCurrentPage();
    same(1, count($page['payload']['contentRecords']));
    same([['file_name' => 'badge.js', 'required_version' => '1.0.0']], $page['render_scripts']);
    same(json_encode($page['payload']), json_encode(fixture_loader()->loadForCurrentPage()));
});
