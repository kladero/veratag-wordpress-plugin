<?php

declare(strict_types=1);

$matchCases = [
    'same path' => ['/article/','/article/', 'https://site.example.test', 1],
    'different path' => ['/different/','/article/', 'https://site.example.test', 0],
    'absolute matching origin' => ['https://site.example.test/article/','/article/', 'https://site.example.test', 1],
    'absolute different origin not matched' => ['https://other.example.test/article/','/article/', 'https://site.example.test', 0],
    'path-only matches alternate origin (current policy)' => ['/article/','/article/', 'https://other.example.test', 1],
    'different domain and path' => ['https://other.example.test/different/','/article/', 'https://site.example.test', 0],
    'trailing slash normalized' => ['/article','/article/', 'https://site.example.test', 1],
    'root path' => ['/','/', 'https://site.example.test', 1],
    'case sensitive path' => ['/ARTICLE/','/article/', 'https://site.example.test', 0],
    'host capitalization normalization' => ['https://SITE.EXAMPLE.TEST/article','/article/', 'https://site.example.test', 1],
    'query in request omitted for page lookup' => ['/article/','/article/?utm=x', 'https://site.example.test', 1],
    'explicit protocol mismatch' => ['http://site.example.test/article/','/article/', 'https://site.example.test', 0],
    'custom port exact' => ['https://site.example.test:8443/article/','/article/', 'https://site.example.test:8443', 1],
    'custom port mismatch' => ['https://site.example.test/article/','/article/', 'https://site.example.test:8443', 0],
];
foreach ($matchCases as $name => [$configuredPage,$request,$home,$expected]) {
    test("page/domain: {$name}", static function () use ($configuredPage, $request, $home, $expected): void {
        $result = fixture_render([fixture_entry($configuredPage)], 'de-DE', 'visitor_preferred', 'en', $request, $home);
        truth(!is_wp_error($result));
        same($expected, count($result['contentRecords'] ?? []));
    });
}
test('page/domain: only current page records served', static function (): void {
    $result = fixture_render([fixture_entry('/article/', 'a'),fixture_entry('/other/', 'b')]);
    same(1, count($result['contentRecords'] ?? []));
    same('a', $result['contentRecords'][0]['contentIdentification']['contentId']);
});
test('page/domain: unrelated page gets other disclosure', static function (): void {
    $result = fixture_render([fixture_entry('/article/', 'a'),fixture_entry('/other/', 'b')], 'en', 'visitor_preferred', 'en', '/other/');
    same('b', $result['contentRecords'][0]['contentIdentification']['contentId']);
});
test('page/domain: WordPress subdirectory does not duplicate path', static function (): void {
    $result = fixture_render([fixture_entry('/blog/article/')], 'en', 'visitor_preferred', 'en', '/blog/article/', 'https://site.example.test/blog');
    same('https://site.example.test/blog/article/', $result['page']);
});

test('pages repository: exact URL lookup must select second page, never first', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_pages'] = [
        ['id' => 1, 'page_url' => 'https://site.example.test/first/'],
        ['id' => 2, 'page_url' => 'https://site.example.test/second/'],
    ];
    $repo = new \VeraTag\Persistence\Disclosures\Repositories\PagesRepository(new \VeraTag\Persistence\Disclosures\Schema\PagesTable());
    same(2, $repo->findByUrl('https://site.example.test/second/')['id']);
    same(null, $repo->findByUrl('https://site.example.test/missing/'));
    same(2, $repo->getOrCreate('https://site.example.test/second/'));
    same(2, count($GLOBALS['wpdb']->tables['test_veratag_pages']));
});

test('fixture SQL: standalone unsupported WHERE predicate fails closed', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_pages'] = [
        ['id' => 1, 'page_url' => 'https://site.example.test/first/'],
    ];
    try {
        $GLOBALS['wpdb']->get_results('SELECT * FROM test_veratag_pages WHERE unknown_column = 42');
        throw new RuntimeException('Expected unsupported SQL to fail');
    } catch (RuntimeException $e) {
        truth(str_contains($e->getMessage(), 'Unsupported fixture SQL predicate'));
    }
});

test('page fixture: two content items on same page reuse exactly one page row', static function (): void {
    $result = fixture_render([fixture_entry('/article/', 'one'), fixture_entry('/article/', 'two')]);
    same(1, count($GLOBALS['wpdb']->tables['test_veratag_pages']));
    same(2, count($result['contentRecords']));
    same(['one', 'two'], array_column(array_column($result['contentRecords'], 'contentIdentification'), 'contentId'));
});
