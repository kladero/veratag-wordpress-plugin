<?php

declare(strict_types=1);

use VeraTag\Persistence\Disclosures\Repositories\PagesRepository;
use VeraTag\Persistence\Disclosures\Repositories\TextTemplatesRepository;
use VeraTag\Persistence\Disclosures\Schema\PagesTable;
use VeraTag\Persistence\Disclosures\Schema\TextTemplatesTable;

test('repository lookups prepare table identifiers and values separately', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_pages'] = [
        ['id' => 1, 'page_url' => '/identifier-policy/'],
    ];
    $repository = new PagesRepository(new PagesTable());
    same(1, $repository->findByUrl('/identifier-policy/')['id']);
    same(
        'SELECT * FROM %i WHERE page_url = %s LIMIT 1',
        $GLOBALS['wpdb']->prepared_query_templates[array_key_last($GLOBALS['wpdb']->prepared_query_templates)]
    );
    same(
        ['test_veratag_pages', '/identifier-policy/'],
        $GLOBALS['wpdb']->prepared_query_arguments[array_key_last($GLOBALS['wpdb']->prepared_query_arguments)]
    );
});

test('generated IN lists keep the identifier and every value in the prepare argument list', static function (): void {
    $GLOBALS['wpdb']->tables['test_veratag_text_templates'] = [
        ['ref' => 'template-a'],
        ['ref' => 'template-b'],
    ];
    $repository = new TextTemplatesRepository(new TextTemplatesTable());
    same(['template-a', 'template-b'], array_column($repository->findByRefs(['template-b', 'template-a']), 'ref'));
    same(
        'SELECT * FROM %i WHERE ref IN (%s, %s) ORDER BY ref ASC',
        $GLOBALS['wpdb']->prepared_query_templates[array_key_last($GLOBALS['wpdb']->prepared_query_templates)]
    );
    same(
        ['test_veratag_text_templates', 'template-b', 'template-a'],
        $GLOBALS['wpdb']->prepared_query_arguments[array_key_last($GLOBALS['wpdb']->prepared_query_arguments)]
    );
});

test('persistence SQL has no raw runtime table interpolation', static function (): void {
    $persistence_root = dirname(__DIR__, 2) . '/src/php/Persistence';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($persistence_root));
    $violations = [];
    foreach ($iterator as $file) {
        if (! $file->isFile() || 'php' !== $file->getExtension()) {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (false === $source) {
            throw new RuntimeException('Unable to read persistence source: ' . $file->getPathname());
        }
        $has_interpolated_table = 1 === preg_match('/\b(?:FROM|JOIN|DELETE FROM|DROP TABLE IF EXISTS)\s+\{\$[A-Za-z_][A-Za-z0-9_]*table[^}]*\}/', $source);
        $has_concatenated_table = 1 === preg_match('/\b(?:FROM|JOIN|DELETE FROM|DROP TABLE IF EXISTS)[^\r\n;]*\.\s*\$this->[A-Za-z_][A-Za-z0-9_]*->getTableName\(\)/', $source);
        if ($has_interpolated_table || $has_concatenated_table) {
            $violations[] = $file->getPathname();
        }
    }
    same([], $violations, 'Runtime table identifiers must use the %i placeholder');
});
