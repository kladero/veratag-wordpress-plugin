<?php

/**
 * Guard the SQL fixture against "helping" production code pass.
 * These cases deliberately distinguish predicates in the SQL from extra
 * behavior previously hard-coded by the mock database.
 */

declare(strict_types=1);

use VeraTag\Persistence\Disclosures\Repositories\PagesRepository;
use VeraTag\Persistence\Disclosures\Schema\PagesTable;

test('SQL fixture: page URL exact-IN does not invent an unrequested query-suffix LIKE', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_pages'] = [
        ['id' => 1, 'page_url' => '/article/?utm=example'],
    ];
    same([], $GLOBALS['wpdb']->get_results("SELECT * FROM test_veratag_pages WHERE page_url IN ('/article/')"));
});

test('pages repository: actual LIKE query includes a URL with query parameters', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_pages'] = [
        ['id' => 1, 'page_url' => '/article/?utm=example'],
        ['id' => 2, 'page_url' => '/other/?utm=example'],
        ['id' => 3, 'page_url' => '/article/extra/?utm=example'],
    ];
    $repo = new PagesRepository(new PagesTable());
    $matched = $repo->findByUrls(['/article/']);
    same([1], array_column($matched, 'id'));
    truth((bool) preg_grep('/\\bpage_url\\s+LIKE\\b/i', $GLOBALS['wpdb']->queries), 'Production must emit explicit suffix LIKE');
});

test('SQL fixture: page-ID IN without disclosure predicates does not silently discard rows', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_content_items'] = [
        ['id' => 1, 'page_id' => 1, 'is_disclosed' => 1, 'configuration_ref' => 'cfg-valid'],
        ['id' => 2, 'page_id' => 1, 'is_disclosed' => 0, 'configuration_ref' => 'cfg-hidden'],
        ['id' => 3, 'page_id' => 1, 'is_disclosed' => 1, 'configuration_ref' => ''],
        ['id' => 4, 'page_id' => 1, 'is_disclosed' => 1, 'configuration_ref' => null],
    ];
    $rows = $GLOBALS['wpdb']->get_results('SELECT * FROM test_veratag_content_items WHERE page_id IN (1)');
    same([1, 2, 3, 4], array_column($rows, 'id'));
});

test('real content repository: page query excludes undisclosed and unconfigured content', static function (): void {
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry('/article/', 'visible'));
    fixture_add_entry(fixture_entry('/article/', 'hidden'));
    fixture_add_entry(fixture_entry('/article/', 'empty-configuration'));
    fixture_add_entry(fixture_entry('/article/', 'null-configuration'));
    $rows = &$GLOBALS['wpdb']->tables['test_veratag_content_items'];
    $rows[1]['is_disclosed'] = 0;
    $rows[2]['configuration_ref'] = '';
    $rows[3]['configuration_ref'] = null;
    $payload = fixture_loader()->loadForCurrentPage();
    truth(!is_wp_error($payload));
    same(['visible'], array_column(array_column($payload['contentRecords'], 'contentIdentification'), 'contentId'));
    truth((bool) preg_grep('/\\bis_disclosed\\s*=\\s*1\\b/i', $GLOBALS['wpdb']->queries), 'Production query must enforce disclosure flag');
    truth((bool) preg_grep('/\\bconfiguration_ref\\s+IS\\s+NOT\\s+NULL\\b/i', $GLOBALS['wpdb']->queries), 'Production query must exclude NULL configuration');
    truth((bool) preg_grep("/\\bconfiguration_ref\\s*<>\\s*''/i", $GLOBALS['wpdb']->queries), 'Production query must exclude empty configuration');
});

test('real content repository: only undisclosed records produce no payload', static function (): void {
    fixture_settings();
    fixture_request();
    fixture_add_entry(fixture_entry('/article/', 'hidden-only'));
    $GLOBALS['wpdb']->tables['test_veratag_content_items'][0]['is_disclosed'] = 0;
    same([], fixture_loader()->loadForCurrentPage());
});

// The mock must reject predicates it cannot evaluate, even if an earlier
// supported WHERE condition would have matched a fixture row.
foreach (
    [
    "SELECT * FROM test_veratag_pages WHERE page_url = '/article/' AND active = 0",
    "SELECT * FROM test_veratag_pages WHERE page_url = '/article/' OR page_url = '/other/'",
    "SELECT * FROM test_veratag_content_items WHERE page_id IN (1) AND unknown_filter = 1",
    "SELECT * FROM test_veratag_configurations WHERE ref IN ('cfg-a') AND enabled = 0",
    ] as $sql
) {
    test('SQL fixture: rejects unsupported extra condition ' . substr(md5($sql), 0, 8), static function () use ($sql): void {
        try {
            $GLOBALS['wpdb']->get_results($sql);
            throw new RuntimeException('Unexpectedly accepted unsupported SQL');
        } catch (RuntimeException $error) {
            truth(str_contains($error->getMessage(), 'Unsupported fixture SQL predicate'), 'Mock must reject rather than ignore unsupported SQL');
        }
    });
}

test('SQL fixture: ORDER BY id DESC is actually evaluated', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_pages'] = [
        ['id' => 2, 'page_url' => '/second/'],
        ['id' => 9, 'page_url' => '/ninth/'],
        ['id' => 3, 'page_url' => '/third/'],
    ];
    same([9, 3, 2], array_column($GLOBALS['wpdb']->get_results('SELECT * FROM test_veratag_pages ORDER BY id DESC'), 'id'));
});

test('SQL fixture: ORDER BY ref ASC is actually evaluated', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_configurations'] = [
        ['ref' => 'z'], ['ref' => 'a'], ['ref' => 'm'],
    ];
    same(['a', 'm', 'z'], array_column($GLOBALS['wpdb']->get_results('SELECT * FROM test_veratag_configurations ORDER BY ref ASC'), 'ref'));
});

test('SQL fixture: LIMIT is applied after sorting', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_disclosure_fetches'] = [
        ['id' => 4], ['id' => 20], ['id' => 7],
    ];
    same([20], array_column($GLOBALS['wpdb']->get_results('SELECT * FROM test_veratag_disclosure_fetches ORDER BY id DESC LIMIT 1'), 'id'));
});

test('SQL fixture: insert does not reuse an existing high AUTO_INCREMENT ID', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_pages'] = [['id' => 10, 'page_url' => '/old/']];
    same(1, $GLOBALS['wpdb']->insert('test_veratag_pages', ['page_url' => '/new/']));
    same(11, $GLOBALS['wpdb']->insert_id);
    same([10, 11], array_column($GLOBALS['wpdb']->tables['test_veratag_pages'], 'id'));
});


test('SQL fixture: deleted most-recent ID is not reused by later insert', static function (): void {
    $database = $GLOBALS['wpdb'];
    same(1, $database->insert('test_veratag_pages', ['page_url' => '/one/']));
    same(1, $database->insert_id);
    $database->tables['test_veratag_pages'] = []; // Simulate deleting row 1.
    same(1, $database->insert('test_veratag_pages', ['page_url' => '/two/']));
    same(2, $database->insert_id);
});

test('SQL fixture: unsupported joins fail closed rather than returning a single table', static function (): void {
    try {
        $GLOBALS['wpdb']->get_results('SELECT * FROM test_veratag_pages JOIN test_veratag_content_items ON 1 = 1');
        throw new RuntimeException('Unexpectedly accepted unsupported JOIN');
    } catch (RuntimeException $error) {
        truth(str_contains($error->getMessage(), 'Unsupported fixture SQL structure'));
    }
});

test('SQL fixture: invalid SQL appended after LIMIT is rejected', static function (): void {
    try {
        $GLOBALS['wpdb']->get_results('SELECT * FROM test_veratag_pages LIMIT 1 AND invalid = 1');
        throw new RuntimeException('Unexpectedly accepted an invalid LIMIT');
    } catch (RuntimeException $error) {
        truth(str_contains($error->getMessage(), 'Unsupported fixture SQL limit'));
    }
});


test('real batches repository: latest snapshot depends on ID order, not fixture insertion order', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_disclosure_fetches'] = [
        ['id' => 4, 'fetched_at' => '2026-01-04'],
        ['id' => 20, 'fetched_at' => '2026-01-20'],
        ['id' => 7, 'fetched_at' => '2026-01-07'],
    ];
    $repository = new \VeraTag\Persistence\Disclosures\Repositories\BatchesRepository(
        new \VeraTag\Persistence\Disclosures\Schema\BatchesTable()
    );
    same(20, $repository->getLatest()['id']);
});

test('real content repository: sorts disclosed content by page and content row ID', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_content_items'] = [
        ['id' => 7, 'page_id' => 2, 'is_disclosed' => 1, 'configuration_ref' => 'cfg-c'],
        ['id' => 4, 'page_id' => 1, 'is_disclosed' => 1, 'configuration_ref' => 'cfg-b'],
        ['id' => 3, 'page_id' => 2, 'is_disclosed' => 1, 'configuration_ref' => 'cfg-a'],
        ['id' => 1, 'page_id' => 1, 'is_disclosed' => 1, 'configuration_ref' => 'cfg-d'],
    ];
    $repository = new \VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository(
        new \VeraTag\Persistence\Disclosures\Schema\ContentItemsTable()
    );
    same([1, 4, 3, 7], array_column($repository->findDisclosedByPageIds([2, 1]), 'id'));
});
