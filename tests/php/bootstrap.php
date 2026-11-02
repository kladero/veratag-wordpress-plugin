<?php

/** Offline test infrastructure only. Never include this file in the distributed plugin. */

declare(strict_types=1);

error_reporting(E_ALL);
// A PHP warning or notice must fail the test rather than be printed beside PASS.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (! (error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
if (!defined('ABSPATH')) {
    define('ABSPATH', '/synthetic-wordpress/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'VeraTag\\')) {
        return;
    }
    $relative = substr($class, strlen('VeraTag\\'));
    $file = dirname(__DIR__, 2) . '/src/php/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
class WP_Error
{
    public function __construct(private string $code, private string $message = '', private mixed $data = null)
    {
    }
    public function get_error_code(): string
    {
        return $this->code;
    }
    public function get_error_message(): string
    {
        return $this->message;
    }
    public function get_error_data(): mixed
    {
        return $this->data;
    }
}
class WP_REST_Request
{
    public function __construct(private string $authorization = '')
    {
    }
    public function get_header(string $name): string
    {
        return strtolower($name) === 'authorization' ? $this->authorization : '';
    }
}
function is_wp_error(mixed $value): bool
{
    return $value instanceof WP_Error;
}
function get_locale(): string
{
    return $GLOBALS['test_locale'] ?? 'en_US';
}
function has_filter(string $name): bool
{
    return false;
}
function wp_unslash(mixed $v): mixed
{
    return $v;
}
function sanitize_text_field(mixed $v): string
{
    return trim(strip_tags((string) $v));
}
function esc_url_raw(string $v): string
{
    return $v;
}
function home_url(string $path = ''): string
{
    return ($GLOBALS['test_home_url'] ?? 'https://site.example.test') . $path;
}
function wp_parse_url(string $url, int $component = -1): mixed
{
    return parse_url($url, $component);
}
function trailingslashit(string $s): string
{
    return rtrim($s, '/') . '/';
}
function untrailingslashit(string $s): string
{
    return rtrim($s, '/');
}
function wp_json_encode(mixed $data, int $options = 0): string|false
{
    return json_encode($data, $options | JSON_INVALID_UTF8_SUBSTITUTE);
}
function esc_attr(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['test_options'][$name] ?? $default;
}
function update_option(string $name, mixed $value, bool $autoload = false): bool
{
    $GLOBALS['test_options'][$name] = $value;
    return true;
}
function current_time(string $format, bool $gmt = false): string
{
    return '2026-10-29 12:00:00';
}

/** Deterministic in-memory SQL boundary. No WordPress, MySQL, network, or actual integration credentials. */
final class FixtureWpdb
{
    public string $prefix = 'test_';
    /** @var array<string,list<array<string,mixed>>> */
    public array $tables = [];
    public array $queries = [];
    public string $last_error = '';
    public int $insert_id = 0;
    /** @var array<string,int> */
    private array $last_autoincrement_id = [];
    public function insert(string $table, array $row, array $formats = []): int
    {
        $this->tables[$table] ??= [];
        // Preserve a table-specific high-water mark, even if its newest row
        // is deleted before the next insert. This is closer to MySQL's
        // AUTO_INCREMENT behavior than count(rows) + 1.
        $highest_existing_id = max(array_merge([0], array_map('intval', array_column($this->tables[$table], 'id'))));
        $this->insert_id = max($highest_existing_id, $this->last_autoincrement_id[$table] ?? 0) + 1;
        $this->last_autoincrement_id[$table] = $this->insert_id;
        $this->tables[$table][] = ['id' => $this->insert_id] + $row;
        return 1;
    }
    public function query(string $sql): int
    {
        $this->queries[] = $sql;
        if (preg_match('/^DELETE FROM ([a-zA-Z_][\w]*) WHERE content_item_id IN \(([^)]*)\)/i', $sql, $m)) {
            preg_match_all('/\d+/', $m[2], $matches);
            $ids = array_map('intval', $matches[0]);
            $before = count($this->tables[$m[1]] ?? []);
            $this->tables[$m[1]] = array_values(array_filter($this->tables[$m[1]] ?? [], static fn ($r) => !in_array((int) ($r['content_item_id'] ?? 0), $ids, true)));
            return $before - count($this->tables[$m[1]]);
        }
        throw new RuntimeException('Unexpected mutation SQL: ' . $sql);
    }
    public function prepare(string $sql, mixed ...$values): string
    {
        if (count($values) === 1 && is_array($values[0])) {
            $values = $values[0];
        }
        $i = 0;
        return preg_replace_callback('/%[sdi]/', static function ($m) use (&$i, $values): string {
            $v = $values[$i++] ?? null;
            return $m[0] === '%i' ? (string) $v : ($m[0] === '%d' ? (string) (int) $v : "'" . str_replace("'", "''", (string) $v) . "'");
        }, $sql);
    }
    public function esc_like(string $s): string
    {
        return $s;
    }
    public function get_row(string $sql, mixed $format = null): ?array
    {
        $rows = $this->get_results($sql, $format);
        return $rows[0] ?? null;
    }
    public function get_results(string $sql, mixed $format = null): array
    {
        $this->queries[] = $sql;
        if (!preg_match('/\bFROM\s+([a-zA-Z_][\w]*)/', $sql, $match)) {
            throw new RuntimeException('Unrecognized SQL: ' . $sql);
        }
        $table = $match[1];
        if (!array_key_exists($table, $this->tables)) {
            throw new RuntimeException('Missing fixture table: ' . $table);
        }
        if (preg_match('/\bJOIN\b|\bGROUP\s+BY\b|\bHAVING\b/i', $sql)) {
            throw new RuntimeException('Unsupported fixture SQL structure: ' . $sql);
        }
        if (preg_match('/\bLIMIT\b/i', $sql) && ! preg_match('/\bLIMIT\s+\d+\s*$/i', $sql)) {
            throw new RuntimeException('Unsupported fixture SQL limit: ' . $sql);
        }
        $rows = $this->tables[$table];
        // This test double is deliberately NOT a SQL engine. Only the exact
        // predicate families exercised by the plugin are supported. Silently
        // ignoring an additional AND/OR condition can make broken production
        // queries pass, so reject any shape we do not explicitly understand.
        if (preg_match('/\bWHERE\s+(.+?)(?=\s+ORDER\s+BY\b|\s+LIMIT\b|$)/is', $sql, $where_match)) {
            $where = trim($where_match[1]);
            $quoted = "'(?:''|[^'])*'";
            $quoted_list = $quoted . '(?:\s*,\s*' . $quoted . ')*';
            $integer_list = '\d+(?:\s*,\s*\d+)*';
            $allowed_predicates = [
                '~^ref\s*=\s*' . $quoted . '$~i',
                '~^ref\s+IN\s*\(' . $quoted_list . '\)$~i',
                '~^configuration_ref\s*=\s*' . $quoted . '$~i',
                '~^content_item_id\s+IN\s*\(' . $integer_list . '\)$~i',
                '~^page_url\s*=\s*' . $quoted . '$~i',
                '~^page_url\s+IN\s*\(' . $quoted_list . '\)(?:\s+OR\s*\(page_url\s+LIKE\s+' . $quoted . '(?:\s+OR\s+page_url\s+LIKE\s+' . $quoted . ')*\))?$~i',
                '~^page_id\s+IN\s*\(' . $integer_list . '\)(?:\s+AND\s+is_disclosed\s*=\s*1)?(?:\s+AND\s+configuration_ref\s+IS\s+NOT\s+NULL)?(?:\s+AND\s+configuration_ref\s*<>\s*' . $quoted . ')?$~i',
            ];
            if (! array_filter($allowed_predicates, static fn (string $pattern): bool => (bool) preg_match($pattern, $where))) {
                throw new RuntimeException('Unsupported fixture SQL predicate: ' . $sql);
            }
        }
        $handledPredicate = false;
        if (preg_match("/\bWHERE\s+ref\s*=\s*'((?:''|[^'])*)'/i", $sql, $m)) {
            $handledPredicate = true;
            $rows = array_values(array_filter($rows, static fn ($r) => ($r['ref'] ?? null) === str_replace("''", "'", $m[1])));
        }
        if (preg_match('/\bWHERE\s+content_item_id\s+IN\s*\(([^)]*)\)/i', $sql, $m)) {
            $handledPredicate = true;
            preg_match_all('/\d+/', $m[1], $matches);
            $ids = array_map('intval', $matches[0]);
            $rows = array_values(array_filter($rows, static fn ($r) => in_array((int) ($r['content_item_id'] ?? 0), $ids, true)));
        }
        if (preg_match("/\bWHERE\s+configuration_ref\s*=\s*'((?:''|[^'])*)'/i", $sql, $m)) {
            $handledPredicate = true;
            $rows = array_values(array_filter($rows, static fn ($r) => ($r['configuration_ref'] ?? null) === str_replace("''", "'", $m[1])));
        }
        if (preg_match('/\bWHERE\s+ref\s+IN\s*\(([^)]*)\)/i', $sql, $m)) {
            $handledPredicate = true;
            preg_match_all("/'((?:''|[^'])*)'/", $m[1], $matches);
            $refs = array_map(static fn ($x) => str_replace("''", "'", $x), $matches[1]);
            $rows = array_values(array_filter($rows, static fn ($r) => in_array($r['ref'] ?? null, $refs, true)));
        }
        if (preg_match("/\bWHERE\s+page_url\s*=\s*'((?:''|[^'])*)'/i", $sql, $m)) {
            $handledPredicate = true;
            $needle = str_replace("''", "'", $m[1]);
            $rows = array_values(array_filter($rows, static fn ($row) => ($row['page_url'] ?? null) === $needle));
        }
        if (preg_match('/\bWHERE\s+page_url\s+IN\s*\(([^)]*)\)/i', $sql, $m)) {
            $handledPredicate = true;
            preg_match_all("/'((?:''|[^'])*)'/", $m[1], $matches);
            $exact_urls = array_map(static fn ($value) => str_replace("''", "'", $value), $matches[1]);
            $like_prefixes = [];
            // The real repository explicitly emits a LIKE branch to match stored
            // page URLs with query strings. Never invent that condition when
            // the SQL no longer contains it (a previous fixture false positive).
            if (preg_match_all("/\bpage_url\s+LIKE\s+'((?:''|[^'])*)'/i", $sql, $likes)) {
                foreach ($likes[1] as $like) {
                    $pattern = str_replace("''", "'", $like);
                    if (!str_ends_with($pattern, '?%')) {
                        throw new RuntimeException('Unsupported fixture page_url LIKE: ' . $sql);
                    }
                    $like_prefixes[] = substr($pattern, 0, -1); // retain '?' but strip SQL '%'
                }
            }
            $rows = array_values(array_filter($rows, static function ($row) use ($exact_urls, $like_prefixes): bool {
                $url = (string) ($row['page_url'] ?? '');
                foreach ($exact_urls as $candidate) {
                    if (strcasecmp($url, $candidate) === 0) {
                        return true;
                    }
                }
                foreach ($like_prefixes as $prefix) {
                    if (strncasecmp($url, $prefix, strlen($prefix)) === 0) {
                        return true;
                    }
                }
                return false;
            }));
        }
        if (preg_match('/\bWHERE\s+page_id\s+IN\s*\(([^)]*)\)/i', $sql, $m)) {
            $handledPredicate = true;
            preg_match_all('/\d+/', $m[1], $matches);
            $ids = array_map('intval', $matches[0]);
            // Apply exactly the predicates written in the production query.
            // The old mock always filtered out undisclosed / unconfigured rows,
            // so the test would pass even if the real SQL accidentally lost
            // its WHERE conditions.
            $requires_disclosed = (bool) preg_match('/\bAND\s+is_disclosed\s*=\s*1\b/i', $sql);
            $requires_configured = (bool) preg_match('/\bAND\s+configuration_ref\s+IS\s+NOT\s+NULL\b/i', $sql);
            $requires_nonempty_config = (bool) preg_match("/\bAND\s+configuration_ref\s*<>\s*''/i", $sql);
            $rows = array_values(array_filter($rows, static function ($row) use ($ids, $requires_disclosed, $requires_configured, $requires_nonempty_config): bool {
                if (!in_array((int) ($row['page_id'] ?? 0), $ids, true)) {
                    return false;
                }
                if ($requires_disclosed && (int) ($row['is_disclosed'] ?? 0) !== 1) {
                    return false;
                }
                if ($requires_configured && !isset($row['configuration_ref'])) {
                    return false;
                }
                if ($requires_nonempty_config && (string) ($row['configuration_ref'] ?? '') === '') {
                    return false;
                }
                return true;
            }));
        }
        if (preg_match('/\bWHERE\b/i', $sql) && ! $handledPredicate) {
            throw new RuntimeException('Unsupported fixture SQL predicate: ' . $sql);
        }
        if (preg_match('/\bORDER\s+BY\s+(.+?)(?=\s+LIMIT\b|$)/i', $sql, $order)) {
            $columns = array_map('trim', explode(',', trim($order[1])));
            $sort_keys = [];
            foreach ($columns as $column) {
                if (! preg_match('/^(id|ref|page_id|content_item_id)\s+(ASC|DESC)$/i', $column, $parts)) {
                    throw new RuntimeException('Unsupported fixture SQL ordering: ' . $sql);
                }
                $sort_keys[] = [strtolower($parts[1]), strtoupper($parts[2])];
            }
            usort($rows, static function (array $left, array $right) use ($sort_keys): int {
                foreach ($sort_keys as [$field, $direction]) {
                    $a = $left[$field] ?? null;
                    $b = $right[$field] ?? null;
                    $compared = is_numeric($a) && is_numeric($b) ? ($a <=> $b) : strcmp((string) $a, (string) $b);
                    if ($compared !== 0) {
                        return $direction === 'DESC' ? -$compared : $compared;
                    }
                }
                return 0;
            });
        }
        if (preg_match('/\bLIMIT\s+(\d+)\b/i', $sql, $limit)) {
            $rows = array_slice($rows, 0, (int) $limit[1]);
        }
        return array_values($rows);
    }
}

/** @var array<string,Closure> */
$GLOBALS['test_cases'] = [];
function test(string $name, Closure $callback): void
{
    if (isset($GLOBALS['test_cases'][$name])) {
        throw new RuntimeException("Duplicate test: {$name}");
    }
    $GLOBALS['test_cases'][$name] = $callback;
}
function same(mixed $expected, mixed $actual, string $context = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($context ? "$context: " : '') . 'Expected ' . var_export($expected, true) . ', received ' . var_export($actual, true));
    }
}
function truth(bool $actual, string $context = ''): void
{
    same(true, $actual, $context);
}
function has_key(string|int $key, array $array, string $context = ''): void
{
    truth(array_key_exists($key, $array), $context ?: "Missing key {$key}");
}
function error_code(string $expected, mixed $result): void
{
    truth(is_wp_error($result), 'Expected WP_Error');
    same($expected, $result->get_error_code());
}
function reset_fixtures(): void
{
    $GLOBALS['test_options'] = [];
    $GLOBALS['test_locale'] = 'en_US';
    $GLOBALS['test_home_url'] = 'https://site.example.test';
    $_SERVER['REQUEST_URI'] = '/article/';
    unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $GLOBALS['wpdb'] = new FixtureWpdb();
    foreach (['veratag_disclosure_fetches','veratag_pages','veratag_content_items','veratag_configurations','veratag_placements','veratag_text_templates','veratag_content_item_issues'] as $table) {
        $GLOBALS['wpdb']->tables['test_' . $table] = [];
    }
}
reset_fixtures();
