<?php

declare(strict_types=1);

use VeraTag\Persistence\Disclosures\ContentItemIssueAggregationService;
use VeraTag\Persistence\Disclosures\Repositories\{ConfigurationsRepository,ContentItemIssuesRepository,ContentItemsRepository,PlacementsRepository,TextTemplatesRepository};
use VeraTag\Persistence\Disclosures\Schema\{ConfigurationsTable,ContentItemIssuesTable,ContentItemsTable,PlacementsTable,TextTemplatesTable};

function fixture_issue(string $reason, string $impact = 'action_required'): array
{
    return ['reason' => $reason, 'impact' => $impact, 'message' => 'Synthetic ' . $reason, 'detectedAt' => '2026-01-01T12:00:00Z'];
}
function fixture_issue_service(): ContentItemIssueAggregationService
{
    return new ContentItemIssueAggregationService(
        new ContentItemsRepository(new ContentItemsTable()),
        new ContentItemIssuesRepository(new ContentItemIssuesTable()),
        new ConfigurationsRepository(new ConfigurationsTable()),
        new PlacementsRepository(new PlacementsTable()),
        new TextTemplatesRepository(new TextTemplatesTable())
    );
}
function fixture_issues_init(): void
{
    global $wpdb;
    $wpdb->tables['test_veratag_content_items'] = [
        ['id' => 11, 'configuration_ref' => 'cfg-main'], ['id' => 12, 'configuration_ref' => 'cfg-main'], ['id' => 13, 'configuration_ref' => 'cfg-other'],
    ];
    $wpdb->tables['test_veratag_configurations'] = [
        ['ref' => 'cfg-main', 'issues' => json_encode([fixture_issue('disclosure-placement-not-selected')]), 'placement_refs' => '["p-shared"]',
            'slots' => '{"disclosure_message":{"de":{"templateRef":"template-main"}}}'],
        ['ref' => 'cfg-other', 'issues' => '[]', 'placement_refs' => '[]', 'slots' => '{}'],
    ];
    $wpdb->tables['test_veratag_placements'] = [
        ['ref' => 'p-shared', 'issues' => json_encode([fixture_issue('disclosure-placement-config-invalid', 'action_recommended')])],
    ];
    $wpdb->tables['test_veratag_text_templates'] = [
        ['ref' => 'template-main', 'issues' => json_encode([fixture_issue('disclosure-text-template-empty')])],
    ];
}
function fixture_issues_for(int $id): array
{
    return array_values(array_filter($GLOBALS['wpdb']->tables['test_veratag_content_item_issues'], static fn ($row) => $row['content_item_id'] === $id));
}
function fixture_reasons_for(int $id): array
{
    return array_column(fixture_issues_for($id), 'reason');
}
test('issues: aggregates own content, configuration, placement and template', static function (): void {
    fixture_issues_init();
    $own = fixture_issue('web-content-disclosure-violates-regulation');
    same(true, fixture_issue_service()->reconcileOne(11, 'cfg-main', [$own]));
    same(['web-content-disclosure-violates-regulation','disclosure-placement-not-selected','disclosure-placement-config-invalid','disclosure-text-template-empty'], fixture_reasons_for(11));
    same('action_recommended', fixture_issues_for(11)[2]['impact']);
});
test('issues: no configuration leaves own content-tier issue', static function (): void {
    fixture_issues_init();
    same(true, fixture_issue_service()->reconcileOne(11, null, [fixture_issue('web-content-disclosure-violates-regulation')]));
    same(['web-content-disclosure-violates-regulation'], fixture_reasons_for(11));
});
test('issues: missing configuration still records own', static function (): void {
    fixture_issues_init();
    same(true, fixture_issue_service()->reconcileOne(11, 'does-not-exist', [fixture_issue('web-content-disclosure-violates-regulation')]));
    same(['web-content-disclosure-violates-regulation'], fixture_reasons_for(11));
});
test('issues: reconcile twice does not duplicate issues', static function (): void {
    fixture_issues_init();
    $s = fixture_issue_service();
    same(true, $s->reconcileOne(11, 'cfg-main', []));
    same(true, $s->reconcileOne(11, 'cfg-main', []));
    same(3, count(fixture_reasons_for(11)));
});
test('issues: removing template issue clears stale result on reconcile', static function (): void {
    fixture_issues_init();
    $s = fixture_issue_service();
    $s->reconcileOne(11, 'cfg-main', []);
    $GLOBALS['wpdb']->tables['test_veratag_text_templates'][0]['issues'] = '[]';
    $s->reconcileOne(11, 'cfg-main', []);
    same(['disclosure-placement-not-selected', 'disclosure-placement-config-invalid'], fixture_reasons_for(11));
});
test('issues: placement delete removes stale issue after explicit reconcile', static function (): void {
    fixture_issues_init();
    $s = fixture_issue_service();
    $s->reconcileOne(11, 'cfg-main', []);
    $GLOBALS['wpdb']->tables['test_veratag_placements'] = [];
    $s->reconcileOne(11, 'cfg-main', []);
    same(['disclosure-placement-not-selected','disclosure-text-template-empty'], fixture_reasons_for(11));
});
test('issues: configuration issue fanout updates assigned items only', static function (): void {
    fixture_issues_init();
    $s = fixture_issue_service();
    $s->reconcileOne(11, 'cfg-main', [fixture_issue('web-content-disclosure-violates-regulation')]);
    $s->reconcileOne(12, 'cfg-main', []);
    $s->reconcileOne(13, 'cfg-other', [fixture_issue('web-content-disclosure-violates-regulation')]);
    $GLOBALS['wpdb']->tables['test_veratag_configurations'][0]['issues'] = json_encode([fixture_issue('disclosure-placement-semantic-mapping-missing')]);
    same(true, $s->reconcileAllByConfigurationRef('cfg-main'));
    truth(in_array('web-content-disclosure-violates-regulation', fixture_reasons_for(11), true));
    truth(in_array('disclosure-placement-semantic-mapping-missing', fixture_reasons_for(11), true));
    truth(in_array('disclosure-placement-semantic-mapping-missing', fixture_reasons_for(12), true));
    truth(!in_array('disclosure-placement-semantic-mapping-missing', fixture_reasons_for(13), true));
    truth(!in_array('disclosure-placement-not-selected', fixture_reasons_for(11), true));
    truth(!in_array('disclosure-placement-not-selected', fixture_reasons_for(12), true));
    same(4, count(fixture_reasons_for(11)));
    same(3, count(fixture_reasons_for(12)));
    same(1, count(fixture_reasons_for(13)));
});
test('issues: placement fanout updates shared configuration items', static function (): void {
    fixture_issues_init();
    $s = fixture_issue_service();
    $s->reconcileOne(11, 'cfg-main', []);
    $s->reconcileOne(12, 'cfg-main', []);
    $GLOBALS['wpdb']->tables['test_veratag_placements'][0]['issues'] = json_encode([fixture_issue('disclosure-placement-config-schema-mismatch')]);
    same(true, $s->reconcileAllByPlacementRef('p-shared'));
    foreach ([11,12] as $id) {
        truth(in_array('disclosure-placement-config-schema-mismatch', fixture_reasons_for($id), true));
    }
});
test('issues: template fanout updates referencing configuration items', static function (): void {
    fixture_issues_init();
    $s = fixture_issue_service();
    $s->reconcileOne(11, 'cfg-main', []);
    $s->reconcileOne(12, 'cfg-main', []);
    $GLOBALS['wpdb']->tables['test_veratag_text_templates'][0]['issues'] = '[]';
    same(true, $s->reconcileAllByTemplateRef('template-main'));
    foreach ([11,12] as $id) {
        truth(!in_array('disclosure-text-template-empty', fixture_reasons_for($id), true));
    }
    $GLOBALS['wpdb']->tables['test_veratag_text_templates'][0]['issues'] = json_encode([fixture_issue('disclosure-text-template-empty')]);
    same(true, $s->reconcileAllByTemplateRef('template-main'));
    foreach ([11,12] as $id) {
        truth(in_array('disclosure-text-template-empty', fixture_reasons_for($id), true));
    }
});
test('issues: empty own/configuration/placement/template yields no issues', static function (): void {
    fixture_issues_init();
    foreach (['test_veratag_configurations','test_veratag_placements','test_veratag_text_templates'] as $name) {
        $GLOBALS['wpdb']->tables[$name][0]['issues'] = '[]';
    }
    same(true, fixture_issue_service()->reconcileOne(11, 'cfg-main', []));
    same([], fixture_reasons_for(11));
});
