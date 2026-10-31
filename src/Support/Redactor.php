<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Support;

/**
 * One place that decides what never reaches profile storage.
 * Everything is driven by config('digdeep.redact').
 */
final class Redactor
{
    public const string MASK = '[redacted]';

    public static function isSensitiveKey(string $key): bool
    {
        $key = mb_strtolower($key);

        foreach ((array) config('digdeep.redact.keys', []) as $needle) {
            if ($needle !== '' && str_contains($key, mb_strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively mask values whose key looks sensitive.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function keys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = self::keys($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public static function headers(array $headers): array
    {
        $sensitive = array_map('mb_strtolower', (array) config('digdeep.redact.headers', []));

        foreach ($headers as $name => $value) {
            if (in_array(mb_strtolower((string) $name), $sensitive, true)) {
                $headers[$name] = is_array($value) ? [self::MASK] : self::MASK;
            }
        }

        return $headers;
    }

    /**
     * Scrub a JSON or form-encoded body. Other content types are returned unchanged;
     * callers decide whether an unscrubbable body is stored at all.
     */
    public static function body(string $content, string $contentType): string
    {
        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($content, true);

            return is_array($decoded)
                ? (string) json_encode(self::keys($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : $content;
        }

        if (str_contains($contentType, 'x-www-form-urlencoded')) {
            parse_str($content, $fields);

            return http_build_query(self::keys($fields));
        }

        return $content;
    }

    /**
     * Mask bindings bound to a sensitive column (`password = ?`, insert column lists)
     * or whose value matches a sensitive pattern (password hashes by default).
     *
     * @param  array<array-key, mixed>  $bindings
     * @return array<array-key, mixed>
     */
    public static function bindings(string $sql, array $bindings): array
    {
        if (! config('digdeep.redact.bindings', true) || $bindings === []) {
            return $bindings;
        }

        $columns = self::bindingColumns($sql);
        $patterns = (array) config('digdeep.redact.binding_patterns', []);
        $position = 0;

        foreach ($bindings as $key => $value) {
            $column = is_string($key) ? $key : ($columns[$position] ?? null);
            $position++;

            if ($column !== null && self::isSensitiveKey($column)) {
                $bindings[$key] = self::MASK;

                continue;
            }

            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match((string) $pattern, $value) === 1) {
                        $bindings[$key] = self::MASK;

                        break;
                    }
                }
            }
        }

        return $bindings;
    }

    /**
     * Best-effort column name for each positional `?` in the SQL.
     * ponytail: regex, not a parser; a `?` inside a string literal shifts positions. The value patterns still apply.
     *
     * @return array<int, string|null>
     */
    private static function bindingColumns(string $sql): array
    {
        // insert into t (a, b) values (?, ?), (?, ?) — repeat the column list per row
        if (preg_match('/^\s*insert\s+(?:ignore\s+)?into\s+\S+\s*\(([^)]*)\)\s*values/i', $sql, $m) === 1) {
            $cols = array_map(fn (string $c): string => trim($c, " `\"[]\t\n"), explode(',', $m[1]));
            $count = substr_count($sql, '?');

            return array_map(fn (int $i): string => $cols[$i % count($cols)], range(0, max(0, $count - 1)));
        }

        $columns = [];
        $segments = explode('?', $sql);
        array_pop($segments);

        foreach ($segments as $before) {
            $columns[] = preg_match('/([\w.`"\[\]]+)\s*(?:=|<>|!=|<=|>=|<|>|\blike|\bin\s*\()\s*$/i', $before, $m) === 1
                ? trim((string) preg_replace('/^.*\./', '', $m[1]), '`"[]')
                : null;
        }

        return $columns;
    }
}
