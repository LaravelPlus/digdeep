<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Analyzers;

use Illuminate\Support\Str;

final class QueryAnalyzer
{
    /**
     * Normalize SQL by replacing literals with placeholders.
     */
    public static function normalize(string $sql): string
    {
        // Replace quoted strings with ?
        $normalized = preg_replace("/'[^']*'/", '?', $sql);
        $normalized = preg_replace('/"[^"]*"/', '?', $normalized);

        // Replace numbers with ?
        $normalized = preg_replace('/\b\d+(\.\d+)?\b/', '?', $normalized);

        // Collapse whitespace
        $normalized = preg_replace('/\s+/', ' ', mb_trim($normalized));

        return $normalized;
    }

    /**
     * Detect N+1 query patterns by grouping normalized queries.
     *
     * @param  array<int, array{sql: string, caller: string, time_ms: float}>  $queries
     * @return array<int, array{pattern: string, count: int, callers: array<int, string>, table: string|null, suggestion: string|null, total_time_ms: float}>
     */
    public static function detectNPlusOne(array $queries): array
    {
        $groups = [];

        foreach ($queries as $q) {
            $normalized = self::normalize($q['sql']);
            $caller = $q['caller'] ?? '';

            $key = $normalized.'|'.$caller;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'pattern' => $normalized,
                    'count' => 0,
                    'callers' => [],
                    'total_time_ms' => 0,
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_time_ms'] += $q['time_ms'] ?? 0;

            if ($caller && !in_array($caller, $groups[$key]['callers'])) {
                $groups[$key]['callers'][] = $caller;
            }
        }

        $nPlusOne = [];

        foreach ($groups as $group) {
            if ($group['count'] <= 1) {
                continue;
            }

            $table = self::extractTable($group['pattern']);
            $suggestion = self::suggestFix($group['pattern']);

            $nPlusOne[] = [
                'pattern' => $group['pattern'],
                'count' => $group['count'],
                'callers' => $group['callers'],
                'table' => $table,
                'suggestion' => $suggestion,
                'total_time_ms' => round($group['total_time_ms'], 2),
            ];
        }

        // Sort by count descending
        usort($nPlusOne, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $nPlusOne;
    }

    /**
     * Suggest eager loading fix for a normalized SQL pattern.
     */
    public static function suggestFix(string $normalizedSql): ?string
    {
        if (preg_match('/select\s+.*?\s+from\s+[`"]?(\w+)[`"]?\s+where\s+[`"]?(\w+)[`"]?\s*=\s*\?/i', $normalizedSql, $m)) {
            $table = $m[1];
            $column = $m[2];

            if (str_ends_with($column, '_id')) {
                $relation = Str::singular(str_replace('_id', '', $column));

                return "Add ->with('{$relation}') to your query on the parent model";
            }

            return "Consider eager loading the relationship that queries '{$table}'";
        }

        return null;
    }

    /**
     * Detect SELECT * queries.
     *
     * @param  array<int, array{sql: string}>  $queries
     * @return array<int, array{sql: string, table: string|null, suggestion: string}>
     */
    public static function detectSelectStar(array $queries): array
    {
        $results = [];

        foreach ($queries as $q) {
            $sql = mb_trim($q['sql']);

            if (preg_match('/^\s*SELECT\s+\*\s+FROM\s+[`"]?(\w+)/i', $sql, $m)) {
                $results[] = [
                    'sql' => $sql,
                    'table' => $m[1],
                    'suggestion' => "Select only needed columns instead of * from '{$m[1]}'",
                ];
            }
        }

        return $results;
    }

    /**
     * Detect queries on columns that likely lack indexes.
     *
     * @param  array<int, array{sql: string}>  $queries
     * @param  array<int, array{name: string, indexes: array<int, array{columns: array<int, string>}>}>  $schema
     * @return array<int, array{sql: string, table: string, column: string, suggestion: string}>
     */
    public static function detectMissingIndexes(array $queries, array $schema): array
    {
        // Build a lookup of indexed columns per table
        $indexedColumns = [];
        foreach ($schema as $table) {
            $indexedColumns[$table['name']] = [];
            foreach ($table['indexes'] ?? [] as $idx) {
                foreach ($idx['columns'] ?? [] as $col) {
                    $indexedColumns[$table['name']][] = $col;
                }
            }
            // Primary keys are always indexed
            foreach ($table['columns'] ?? [] as $col) {
                if ($col['pk'] ?? false) {
                    $indexedColumns[$table['name']][] = $col['name'];
                }
            }
        }

        $results = [];

        foreach ($queries as $q) {
            $sql = mb_trim($q['sql']);

            // Look for WHERE clauses with specific columns
            if (preg_match_all('/\bWHERE\b.*?[`"]?(\w+)[`"]?\s*(?:=|>|<|LIKE|IN)\s/i', $sql, $whereMatches)) {
                // Extract table name
                $table = self::extractTable($sql);
                if (!$table || !isset($indexedColumns[$table])) {
                    continue;
                }

                foreach ($whereMatches[1] as $column) {
                    if (!in_array($column, $indexedColumns[$table]) && $column !== 'id') {
                        $results[] = [
                            'sql' => $sql,
                            'table' => $table,
                            'column' => $column,
                            'suggestion' => "Add an index on '{$table}.{$column}' to improve query performance",
                        ];
                    }
                }
            }
        }

        // Deduplicate by table + column
        $seen = [];
        $results = array_values(array_filter($results, function ($r) use (&$seen) {
            $key = $r['table'].'.'.$r['column'];
            if (in_array($key, $seen)) {
                return false;
            }
            $seen[] = $key;

            return true;
        }));

        return $results;
    }

    /**
     * Generate unified hints combining all detection methods.
     *
     * @param  array<int, array{sql: string, caller?: string, time_ms?: float}>  $queries
     * @param  array<int, array{name: string, indexes: array}>  $schema
     * @return array<int, array{severity: string, type: string, message: string, suggestion: string, details?: array}>
     */
    public static function generateHints(array $queries, array $schema = []): array
    {
        $hints = [];

        // N+1 detection
        $nPlusOne = self::detectNPlusOne($queries);
        foreach ($nPlusOne as $np) {
            $hints[] = [
                'severity' => 'warning',
                'type' => 'n_plus_one',
                'message' => "N+1 detected: {$np['count']}x repeated query" . ($np['table'] ? " on '{$np['table']}'" : ''),
                'suggestion' => $np['suggestion'] ?? 'Consider eager loading this relationship',
                'details' => ['pattern' => $np['pattern'], 'count' => $np['count']],
            ];
        }

        // SELECT * detection
        $selectStar = self::detectSelectStar($queries);
        foreach ($selectStar as $ss) {
            $hints[] = [
                'severity' => 'info',
                'type' => 'select_star',
                'message' => "SELECT * used on '{$ss['table']}'",
                'suggestion' => $ss['suggestion'],
            ];
        }

        // Missing indexes
        if (!empty($schema)) {
            $missingIndexes = self::detectMissingIndexes($queries, $schema);
            foreach ($missingIndexes as $mi) {
                $hints[] = [
                    'severity' => 'warning',
                    'type' => 'missing_index',
                    'message' => "Column '{$mi['table']}.{$mi['column']}' used in WHERE but not indexed",
                    'suggestion' => $mi['suggestion'],
                ];
            }
        }

        return $hints;
    }

    /**
     * Extract the primary table name from a SQL statement.
     */
    private static function extractTable(string $sql): ?string
    {
        if (preg_match('/\bFROM\s+[`"]?(\w+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }

        return null;
    }
}
