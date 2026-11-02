<?php

declare(strict_types=1);

use VeraTag\Features\PullSynchronization\PullSynchronizationApiIssueDto;

// Public reason identifiers only; no Java backend implementation is included.

$backendIssueReasonKeys = [
    'web-content-disclosure-violates-regulation',
    'web-content-disclosure-configuration-does-not-support-channel-visitor-fallback-language',
    'disclosure-text-template-empty-text-for-mandatory-slot',
    'disclosure-text-template-empty-text-for-semantically-mapped-slot',
    'disclosure-text-template-inconsistent-language-across-slots',
    'disclosure-placement-not-selected',
    'disclosure-placement-semantic-mapping-missing',
    'disclosure-placement-config-invalid',
    'disclosure-placement-config-schema-mismatch',
    'disclosure-text-template-empty',
];
foreach ($backendIssueReasonKeys as $reason) {
    test("backend issue DTO: preserves {$reason}", static function () use ($reason): void {
        $raw = ['id' => 101, 'reason' => $reason, 'impact' => 'action_required', 'message' => 'Synthetic issue: ' . $reason, 'detectedAt' => '2026-10-01T12:00:00Z'];
        $dto = PullSynchronizationApiIssueDto::fromArray($raw);
        same($raw, $dto->toArray());
    });
    test("backend issue aggregator: survives reconciliation {$reason}", static function () use ($reason): void {
        fixture_issues_init();
        $issues = [fixture_issue($reason, 'action_recommended')];
        same(true, fixture_issue_service()->reconcileOne(11, null, $issues));
        same([$reason], fixture_reasons_for(11));
        same('action_recommended', fixture_issues_for(11)[0]['impact']);
    });
}
test('backend issue: absent timestamps do not prevent storing an issue', static function (): void {
    fixture_issues_init();
    $raw = ['reason' => 'web-content-disclosure-violates-regulation', 'impact' => 'action_required', 'message' => 'Synthetic', 'detectedAt' => null];
    same(true, fixture_issue_service()->reconcileOne(11, null, [$raw]));
    same(['web-content-disclosure-violates-regulation'], fixture_reasons_for(11));
});
test('backend issue: malformed dictionary issue JSON is ignored without inventing issues', static function (): void {
    fixture_issues_init();
    $GLOBALS['wpdb']->tables['test_veratag_configurations'][0]['issues'] = '{invalid json';
    same(true, fixture_issue_service()->reconcileOne(11, 'cfg-main', []));
    truth(!in_array('disclosure-placement-not-selected', fixture_reasons_for(11), true));
});
test('backend issue: empty dictionary issue entries are not treated as issues', static function (): void {
    fixture_issues_init();
    $GLOBALS['wpdb']->tables['test_veratag_configurations'][0]['issues'] = '[null,123,"bad",{}]';
    same(true, fixture_issue_service()->reconcileOne(11, 'cfg-main', []));
    same(['disclosure-placement-config-invalid', 'disclosure-text-template-empty'], fixture_reasons_for(11));
    foreach (fixture_issues_for(11) as $issue) {
        truth(trim($issue['reason']) !== '', 'Never persist a blank issue reason');
        truth(in_array($issue['impact'], ['action_required', 'action_recommended'], true));
        truth(trim($issue['message']) !== '', 'Never persist a blank issue message');
    }
});

test('backend issue: six-field backend DTO projects headline out of five-field WordPress mirror', static function (): void {
    // The backend sends a localized headline; the current WordPress issue table/UI
    // instead generates a title from reason. Capture this LOSS EXPLICITLY to keep
    // the contract honest until an intentional schema/UI migration is designed.
    $incoming = [
        'id' => 82,
        'reason' => 'disclosure-text-template-empty',
        'impact' => 'action_required',
        'headline' => 'Synthetic localized headline',
        'message' => 'Synthetic issue message',
        'detectedAt' => '2026-10-29T12:00:00Z',
    ];
    $dto = PullSynchronizationApiIssueDto::fromArray($incoming);
    $expected = $incoming;
    unset($expected['headline']);
    same($expected, $dto->toArray());
});

test('backend issue: malformed own-content issues are rejected before persistence', static function (): void {
    fixture_issues_init();
    same(true, fixture_issue_service()->reconcileOne(11, null, [
        [],
        ['reason' => '', 'impact' => 'action_required', 'message' => 'Blank reason'],
        ['reason' => 'synthetic-valid', 'impact' => 'required', 'message' => 'Bad impact'],
        ['reason' => 'synthetic-valid', 'impact' => 'action_required', 'message' => ''],
        ['reason' => 'web-content-disclosure-violates-regulation', 'impact' => 'action_required', 'message' => 'Valid'],
    ]));
    same(['web-content-disclosure-violates-regulation'], fixture_reasons_for(11));
});

test('backend issue: malformed dictionary impacts and empty fields are rejected', static function (): void {
    fixture_issues_init();
    $GLOBALS['wpdb']->tables['test_veratag_configurations'][0]['issues'] = json_encode([
        ['reason' => 'synthetic-invalid', 'impact' => 'required', 'message' => 'Invalid impact'],
        ['reason' => 'synthetic-invalid', 'impact' => 'action_required', 'message' => ' '],
        ['reason' => 4, 'impact' => 'action_required', 'message' => 'Wrong type'],
        fixture_issue('disclosure-text-template-empty'),
    ]);
    same(true, fixture_issue_service()->reconcileOne(11, 'cfg-main', []));
    same([
        'disclosure-text-template-empty',
        'disclosure-placement-config-invalid',
        'disclosure-text-template-empty',
    ], fixture_reasons_for(11));
});
