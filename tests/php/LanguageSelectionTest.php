<?php

declare(strict_types=1);

use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresLanguageDeciderService;
use VeraTag\Settings\OptionValues\ChannelVisitorDisclosureLanguageMode;

$languageCases = [
    'German exact' => [['de'], ['de','en'], 'en', 'de'],
    'German regional de-AT' => [['de-AT'], ['de','en'], 'en', 'de'],
    'German regional de_DE' => [['de_DE'], ['de','en'], 'en', 'de'],
    'English regional' => [['en-US'], ['en','de'], 'de', 'en'],
    'French missing uses fallback' => [['fr-FR'], ['de','en'], 'en', 'en'],
    'Spanish missing uses German fallback' => [['es'], ['de','en'], 'de', 'de'],
    'Multiple choices priority' => [['fr-FR','de-DE','en-US'], ['de','en'], 'en', 'de'],
    'Multiple choices English priority' => [['fr-FR','en-GB','de-DE'], ['de','en'], 'de', 'en'],
    'Exact beats base' => [['de-DE'], ['de','de-DE','en'], 'en', 'de-DE'],
    'Case-insensitive exact' => [['de-de'], ['de-DE','de','en'], 'en', 'de-DE'],
    'Separator normalization' => [['de-AT'], ['de_AT','de','en'], 'en', 'de_AT'],
    'Unknown requested selects fallback' => [['xy-ZZ'], ['en','de'], 'de', 'de'],
    'Empty requested selects fallback' => [[], ['en','de'], 'de', 'de'],
    'Duplicate preferred candidates' => [['de','de','en'], ['en','de'], 'en', 'de'],
    'Whitespace candidates' => [[' de ','en'], ['en','de'], 'en', 'de'],
    'Only fallback provided' => [['en-US'], ['en'], 'en', 'en'],
    'Region fallback resolved to base' => [['fr'], ['de','en'], 'en-US', 'en'],
];
foreach ($languageCases as $name => [$preferred, $available, $fallback, $expected]) {
    test("language: {$name}", static function () use ($preferred, $available, $fallback, $expected): void {
        $data = array_fill_keys($available, ['slots' => ['ok' => 'yes']]);
        $selected = (new DisclosuresLanguageDeciderService())->selectDisclosureLanguageFromCandidates($data, $preferred, $fallback);
        same($expected, $selected);
    });
}
test('language: absent configured fallback rejects preferred translation', static function (): void {
    $result = (new DisclosuresLanguageDeciderService())->selectDisclosureLanguageFromCandidates(['de' => ['slots' => ['ok' => 'yes']]], ['de'], 'en');
    error_code('veratag_frontend_serving_missing_fallback_language', $result);
});
test('language: empty language map returns missing languages error', static function (): void {
    error_code('veratag_frontend_serving_missing_disclosure_languages', (new DisclosuresLanguageDeciderService())->selectDisclosureLanguageFromCandidates([], ['en'], 'en'));
});
test('language: unusable preferred yields usable fallback', static function (): void {
    $map = ['de' => ['slots' => ['ok' => '']], 'en' => ['slots' => ['ok' => 'value']]];
    $choose = (new DisclosuresLanguageDeciderService())->selectDisclosureLanguageFromCandidates($map, ['de'], 'en', static fn ($entry) => '' !== $entry['slots']['ok']);
    same('en', $choose);
});
test('language: preferred exact unusable but base usable', static function (): void {
    $map = ['de-AT' => ['slots' => ['ok' => '']], 'de' => ['slots' => ['ok' => 'value']], 'en' => ['slots' => ['ok' => 'value']]];
    same('de', (new DisclosuresLanguageDeciderService())->selectDisclosureLanguageFromCandidates($map, ['de-AT'], 'en', static fn ($entry) => '' !== $entry['slots']['ok']));
});
test('language: unusable fallback rejects even if preferred valid', static function (): void {
    $map = ['de' => ['slots' => ['ok' => 'value']], 'en' => ['slots' => ['ok' => '']]];
    error_code('veratag_frontend_serving_missing_fallback_language', (new DisclosuresLanguageDeciderService())->selectDisclosureLanguageFromCandidates($map, ['de'], 'en', static fn ($entry) => '' !== $entry['slots']['ok']));
});
$headerCases = [
    'q weights reorder' => ['de;q=0.2,en;q=0.9', ['en', 'de']],
    'q zero omitted' => ['de;q=0,en;q=1', ['en']],
    'equal order stable' => ['fr,en,de', ['fr','en','de']],
    'underscore normalized' => ['de-AT,en;q=0.1', ['de_AT','en']],
    'wildcard ignored' => ['*,de;q=0.3', ['de']],
    'empty header' => ['', []],
    'duplicate tags' => ['de,de,en', ['de','en']],
];
foreach ($headerCases as $name => [$header,$expected]) {
    test("language header: {$name}", static function () use ($header, $expected): void {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $header;
        $actual = (new DisclosuresLanguageDeciderService())->resolveCurrentPageLanguageCandidates(ChannelVisitorDisclosureLanguageMode::VISITOR_PREFERRED);
        same($expected, $actual);
    });
}
test('language server: WordPress locale', static function (): void {
    $GLOBALS['test_locale'] = 'de_DE';
    same(['de_DE'], (new DisclosuresLanguageDeciderService())->resolveCurrentPageLanguageCandidates(ChannelVisitorDisclosureLanguageMode::SERVER_PREFERRED));
});
test('language server: ignores browser header', static function (): void {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR';
    $GLOBALS['test_locale'] = 'en_US';
    same(['en_US'], (new DisclosuresLanguageDeciderService())->resolveCurrentPageLanguageCandidates(ChannelVisitorDisclosureLanguageMode::SERVER_PREFERRED));
});
