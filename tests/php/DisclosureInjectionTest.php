<?php

declare(strict_types=1);

use VeraTag\Features\FrontendServing\DisclosuresDataLoading\DisclosuresDataInjectionService;

function injected_markup(array $payload): string
{
    ob_start();
    (new DisclosuresDataInjectionService())->injectDisclosureDataScript($payload);
    return (string) ob_get_clean();
}
test('injection: no payload produces no tag', static function (): void {
    same('', injected_markup([]));
});
test('injection: emits exact element id and JSON type', static function (): void {
    $html = injected_markup(['site' => 'https://site.example.test','contentRecords' => [['disclosure' => ['disclosureInformation' => ['disclosureLanguage' => 'de']]]]]);
    truth(str_contains($html, '<script type="application/json" id="disclosure-data">'));
    truth(str_contains($html, '"disclosureLanguage":"de"'));
    truth(str_ends_with($html, "</script>\n"));
});
test('injection: XSS quote ampersand apostrophe and tag are safely JSON escaped', static function (): void {
    $html = injected_markup(['message' => '<img src=x onerror="attack()"> & \'']);
    foreach (['\\u003C', '\\u003E', '\\u0026', '\\u0022', '\\u0027'] as $escape) {
        truth(str_contains($html, $escape), "Expected hex escape {$escape}");
    }
    truth(!str_contains($html, '<img'));
});
test('injection: JSON round trip recovers original text', static function (): void {
    $value = ['contentRecords' => [['text' => 'Ä & <a> "ü"']]];
    $html = injected_markup($value);
    preg_match('~<script[^>]*>(.*?)</script>~s', $html, $m);
    same($value, json_decode($m[1] ?? '', true, 512, JSON_THROW_ON_ERROR));
});
test('injection: rejects invalid JSON encoding', static function (): void {
    // Recursive data cannot be json encoded.
    $loop = [];
    $loop['self'] = &$loop;
    same('', injected_markup($loop));
});
