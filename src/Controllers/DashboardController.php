<?php

namespace LaravelPlus\DigDeep\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

class DashboardController extends Controller
{
    public function __construct(private DigDeepStorage $storage) {}

    public function index()
    {
        $profiles = $this->storage->all();
        $stats = $this->storage->stats();
        $topRoutes = $this->storage->topRoutes();
        $currentSection = 'web';

        return view('digdeep::dashboard', compact('profiles', 'stats', 'topRoutes', 'currentSection'));
    }

    public function show(string $id)
    {
        $profile = $this->storage->find($id);

        if (! $profile) {
            abort(404, 'Profile not found');
        }

        $currentSection = 'web';

        return view('digdeep::show', compact('profile', 'currentSection'));
    }

    public function security()
    {
        $profiles = $this->storage->all();
        $currentSection = 'security';

        $securityIssues = [];
        foreach ($profiles as $p) {
            $full = $this->storage->find($p['id']);
            if (! $full) {
                continue;
            }

            $data = $full['data'];

            if (in_array($p['method'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                $middleware = $data['route']['middleware'] ?? [];
                $hasVerifyCsrf = false;
                foreach ($middleware as $mw) {
                    if (is_string($mw) && str_contains(strtolower($mw), 'csrf')) {
                        $hasVerifyCsrf = true;
                        break;
                    }
                }
                if (! $hasVerifyCsrf) {
                    $securityIssues[] = [
                        'type' => 'warning',
                        'category' => 'CSRF',
                        'message' => "Route {$p['method']} {$p['url']} may not have CSRF protection",
                        'profile_id' => $p['id'],
                    ];
                }
            }

            $responseHeaders = $data['response']['headers'] ?? [];
            if (! isset($responseHeaders['x-content-type-options'])) {
                $securityIssues[] = [
                    'type' => 'info',
                    'category' => 'Headers',
                    'message' => "Missing X-Content-Type-Options header on {$p['url']}",
                    'profile_id' => $p['id'],
                ];
            }

            $queries = $data['queries'] ?? [];
            foreach ($queries as $q) {
                if (preg_match('/\bWHERE\b.*(?:--|;|\/\*|UNION|DROP|ALTER)/i', $q['sql'])) {
                    $securityIssues[] = [
                        'type' => 'danger',
                        'category' => 'SQL',
                        'message' => "Potentially dangerous SQL pattern on {$p['url']}",
                        'profile_id' => $p['id'],
                    ];
                }
            }
        }

        $seen = [];
        $securityIssues = array_filter($securityIssues, function ($issue) use (&$seen) {
            if (in_array($issue['message'], $seen)) {
                return false;
            }
            $seen[] = $issue['message'];

            return true;
        });

        return view('digdeep::security', compact('securityIssues', 'currentSection'));
    }

    public function audits()
    {
        $profiles = $this->storage->all();
        $currentSection = 'audits';

        $routeAudits = [];
        foreach ($profiles as $p) {
            $key = $p['method'].' '.$p['url'];
            if (! isset($routeAudits[$key])) {
                $routeAudits[$key] = [
                    'method' => $p['method'],
                    'url' => $p['url'],
                    'count' => 0,
                    'avg_duration' => 0,
                    'min_duration' => PHP_FLOAT_MAX,
                    'max_duration' => 0,
                    'avg_queries' => 0,
                    'statuses' => [],
                    'last_seen' => $p['created_at'],
                    'durations' => [],
                    'queries' => [],
                ];
            }
            $routeAudits[$key]['count']++;
            $routeAudits[$key]['durations'][] = (float) $p['duration_ms'];
            $routeAudits[$key]['queries'][] = (int) $p['query_count'];
            $routeAudits[$key]['statuses'][] = (int) $p['status_code'];
        }

        foreach ($routeAudits as &$audit) {
            $audit['avg_duration'] = round(array_sum($audit['durations']) / count($audit['durations']), 1);
            $audit['min_duration'] = round(min($audit['durations']), 1);
            $audit['max_duration'] = round(max($audit['durations']), 1);
            $audit['avg_queries'] = round(array_sum($audit['queries']) / count($audit['queries']), 1);
            $audit['error_rate'] = round(count(array_filter($audit['statuses'], fn ($s) => $s >= 400)) / count($audit['statuses']) * 100);
            unset($audit['durations'], $audit['queries']);
            $audit['statuses'] = array_unique($audit['statuses']);
            sort($audit['statuses']);
        }

        uasort($routeAudits, fn ($a, $b) => $b['count'] <=> $a['count']);

        return view('digdeep::audits', compact('routeAudits', 'currentSection'));
    }

    public function urls()
    {
        $topRoutes = $this->storage->topRoutes(50);
        $currentSection = 'urls';

        return view('digdeep::urls', compact('topRoutes', 'currentSection'));
    }

    public function errors()
    {
        $currentSection = 'errors';
        $profiles = $this->storage->all();

        $errors = [];
        foreach ($profiles as $p) {
            $full = $this->storage->find($p['id']);
            if (! $full) {
                continue;
            }

            $exception = $full['data']['exception'] ?? null;
            if (! $exception) {
                continue;
            }

            $errors[] = [
                'profile_id' => $p['id'],
                'method' => $p['method'],
                'url' => $p['url'],
                'status_code' => (int) $p['status_code'],
                'created_at' => $p['created_at'],
                'class' => $exception['class'],
                'message' => $exception['message'],
                'code' => $exception['code'],
                'file' => $exception['file'],
                'line' => $exception['line'],
                'trace' => $exception['trace'] ?? [],
                'previous' => $exception['previous'] ?? null,
            ];
        }

        // Group by exception class for stats
        $errorsByClass = [];
        foreach ($errors as $err) {
            $class = $err['class'];
            if (! isset($errorsByClass[$class])) {
                $errorsByClass[$class] = [
                    'class' => $class,
                    'count' => 0,
                    'last_message' => $err['message'],
                    'last_seen' => $err['created_at'],
                ];
            }
            $errorsByClass[$class]['count']++;
        }

        uasort($errorsByClass, fn ($a, $b) => $b['count'] <=> $a['count']);

        $errorStats = [
            'total' => count($errors),
            'unique_classes' => count($errorsByClass),
            'error_rate' => count($profiles) > 0 ? round(count($errors) / count($profiles) * 100, 1) : 0,
        ];

        return view('digdeep::errors', compact('currentSection', 'errors', 'errorsByClass', 'errorStats'));
    }

    public function database()
    {
        $currentSection = 'database';
        $profiles = $this->storage->all();

        // Collect all queries from profiles
        $allQueries = [];
        $reads = 0;
        $writes = 0;
        $others = 0;
        $totalTime = 0;
        $tableAccess = [];
        $slowQueries = [];

        foreach ($profiles as $p) {
            $full = $this->storage->find($p['id']);
            if (! $full) {
                continue;
            }

            foreach ($full['data']['queries'] ?? [] as $q) {
                $sql = trim($q['sql']);
                $upper = strtoupper($sql);
                $timems = (float) $q['time_ms'];
                $totalTime += $timems;

                // Classify read/write
                if (str_starts_with($upper, 'SELECT') || str_starts_with($upper, 'PRAGMA') || str_starts_with($upper, 'EXPLAIN')) {
                    $reads++;
                } elseif (str_starts_with($upper, 'INSERT') || str_starts_with($upper, 'UPDATE') || str_starts_with($upper, 'DELETE') || str_starts_with($upper, 'REPLACE')) {
                    $writes++;
                } else {
                    $others++;
                }

                // Extract table names
                if (preg_match_all('/\b(?:FROM|JOIN|INTO|UPDATE|TABLE)\s+[`"]?(\w+)[`"]?/i', $sql, $matches)) {
                    foreach ($matches[1] as $table) {
                        $table = strtolower($table);
                        if (! isset($tableAccess[$table])) {
                            $tableAccess[$table] = ['reads' => 0, 'writes' => 0, 'total_time' => 0];
                        }
                        if (str_starts_with($upper, 'SELECT') || str_starts_with($upper, 'PRAGMA')) {
                            $tableAccess[$table]['reads']++;
                        } else {
                            $tableAccess[$table]['writes']++;
                        }
                        $tableAccess[$table]['total_time'] += $timems;
                    }
                }

                // Track slow queries (>5ms)
                if ($timems > 5) {
                    $slowQueries[] = [
                        'sql' => $sql,
                        'time_ms' => $timems,
                        'caller' => $q['caller'],
                        'url' => $p['url'],
                        'profile_id' => $p['id'],
                    ];
                }
            }
        }

        // Sort table access by total queries desc
        uasort($tableAccess, fn ($a, $b) => ($b['reads'] + $b['writes']) <=> ($a['reads'] + $a['writes']));

        // Sort slow queries by time desc, take top 20
        usort($slowQueries, fn ($a, $b) => $b['time_ms'] <=> $a['time_ms']);
        $slowQueries = array_slice($slowQueries, 0, 20);

        // Get the app database schema
        $schema = [];
        try {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            foreach ($tables as $table) {
                $columns = DB::select("PRAGMA table_info(\"{$table->name}\")");
                $indexes = DB::select("PRAGMA index_list(\"{$table->name}\")");
                $fks = DB::select("PRAGMA foreign_key_list(\"{$table->name}\")");
                $count = DB::selectOne("SELECT COUNT(*) as cnt FROM \"{$table->name}\"")?->cnt ?? 0;

                // Get index details (columns per index)
                $indexDetails = [];
                foreach ($indexes as $idx) {
                    $idxCols = DB::select("PRAGMA index_info(\"{$idx->name}\")");
                    $indexDetails[] = [
                        'name' => $idx->name,
                        'unique' => (bool) $idx->unique,
                        'columns' => collect($idxCols)->pluck('name')->all(),
                    ];
                }

                $schema[] = [
                    'name' => $table->name,
                    'columns' => collect($columns)->map(fn ($c) => [
                        'name' => $c->name,
                        'type' => $c->type,
                        'nullable' => ! $c->notnull,
                        'pk' => (bool) $c->pk,
                        'default' => $c->dflt_value,
                    ])->all(),
                    'indexes' => $indexDetails,
                    'foreign_keys' => collect($fks)->map(fn ($fk) => [
                        'from' => $fk->from,
                        'table' => $fk->table,
                        'to' => $fk->to,
                        'on_update' => $fk->on_update,
                        'on_delete' => $fk->on_delete,
                    ])->all(),
                    'index_count' => count($indexes),
                    'fk_count' => count($fks),
                    'row_count' => $count,
                ];
            }
        } catch (\Throwable) {
            // Schema introspection may fail on non-SQLite DBs, that's fine
        }

        $dbStats = [
            'total_queries' => $reads + $writes + $others,
            'reads' => $reads,
            'writes' => $writes,
            'others' => $others,
            'total_time' => round($totalTime, 2),
            'avg_time' => ($reads + $writes + $others) > 0 ? round($totalTime / ($reads + $writes + $others), 2) : 0,
            'table_count' => count($schema),
        ];

        return view('digdeep::database', compact('currentSection', 'dbStats', 'tableAccess', 'slowQueries', 'schema'));
    }
}
