<?php

namespace LaravelPlus\DigDeep\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use LaravelPlus\DigDeep\Analyzers\QueryAnalyzer;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

class DashboardController extends Controller
{
    public function __construct(private DigDeepStorage $storage) {}

    public function index(): View
    {
        $allProfiles = $this->storage->all();
        $profiles = array_slice($allProfiles, 0, 50);
        $hasMore = count($allProfiles) > 50;
        $stats = $this->storage->stats();
        $topRoutes = $this->storage->topRoutes();
        $currentSection = 'web';

        $routePerf = $this->computeRoutePerformance($allProfiles);
        $globalPerf = $this->computeGlobalPerformance($allProfiles);

        return view('digdeep::dashboard', compact(
            'profiles', 'hasMore', 'stats', 'topRoutes',
            'routePerf', 'globalPerf', 'currentSection'
        ));
    }

    public function show(string $id): View
    {
        $profile = $this->storage->find($id);

        if (! $profile) {
            abort(404, 'Profile not found');
        }

        $currentSection = 'web';

        return view('digdeep::show', compact('profile', 'currentSection'));
    }

    public function pipeline(): View
    {
        $currentSection = 'pipeline';

        // Collect all registered application routes
        $ignoredPrefixes = ['digdeep', '_debugbar', 'telescope', 'horizon', '_ignition', 'sanctum', '_boost'];
        $registeredRoutes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            $skip = false;
            foreach ($ignoredPrefixes as $prefix) {
                if (str_starts_with($uri, $prefix)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $methods = array_filter($route->methods(), fn ($m) => $m !== 'HEAD');
            $normalizedUri = '/'.ltrim($uri, '/');

            // Build regex pattern from URI for matching parameterized routes
            $quotedUri = preg_quote($normalizedUri, '#');
            $pattern = '#^'.preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', $quotedUri).'$#';

            foreach ($methods as $method) {
                $key = $method.' '.$normalizedUri;
                $registeredRoutes[$key] = [
                    'method' => $method,
                    'uri' => $normalizedUri,
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'middleware' => array_map(fn ($mw) => is_string($mw) ? $mw : get_class($mw), $route->gatherMiddleware()),
                    'pattern' => $pattern,
                    'profiles' => [],
                ];
            }
        }

        // Build hash maps for O(1) route lookups
        $nameIndex = [];    // "METHOD:routeName" => route key
        $actionIndex = [];  // "METHOD:actionName" => route key
        $uriIndex = [];     // "METHOD /uri" => route key (exact match)
        $patternIndex = []; // method => [['pattern' => ..., 'key' => ...], ...]

        foreach ($registeredRoutes as $key => $rr) {
            $uriIndex[$key] = $key; // key is already "METHOD /uri"

            if (! empty($rr['name'])) {
                $nameIndex[$rr['method'].':'.$rr['name']] = $key;
            }
            if (! empty($rr['action'])) {
                $actionIndex[$rr['method'].':'.$rr['action']] = $key;
            }
            $patternIndex[$rr['method']][] = ['pattern' => $rr['pattern'], 'key' => $key];
        }

        // Match profiled requests to registered routes
        $profiles = $this->storage->allWithData();
        foreach ($profiles as $p) {
            $data = $p['data'];
            $routeName = $data['route']['name'] ?? null;
            $routeAction = $data['route']['action'] ?? null;
            $lifecycle = $data['lifecycle'] ?? [];
            $hasLifecycle = ! empty($lifecycle) && ! empty($lifecycle['phases']);

            $profileEntry = [
                'id' => $p['id'],
                'method' => $p['method'],
                'url' => $p['url'],
                'status_code' => (int) $p['status_code'],
                'duration_ms' => (float) $p['duration_ms'],
                'memory_peak_mb' => (float) $p['memory_peak_mb'],
                'query_count' => (int) $p['query_count'],
                'created_at' => $p['created_at'],
                'has_lifecycle' => $hasLifecycle,
                'phases' => $hasLifecycle ? $lifecycle['phases'] : [],
                'queries' => $data['queries'] ?? [],
                'views' => $data['views'] ?? [],
                'route' => $data['route'] ?? [],
                'events' => $data['events'] ?? [],
                'cache' => $data['cache'] ?? [],
                'inertia' => $data['inertia'] ?? [],
                'mail' => $data['mail'] ?? [],
                'http_client' => $data['http_client'] ?? [],
                'jobs' => $data['jobs'] ?? [],
                'models' => $data['models'] ?? [],
                'request' => $data['request'] ?? [],
                'response' => $data['response'] ?? [],
                'performance' => $data['performance'] ?? [],
                'exception' => $data['exception'] ?? null,
                'is_ajax' => $data['is_ajax'] ?? false,
                'middleware_timing' => $data['middleware_timing'] ?? [],
            ];

            $matchedKey = null;

            // 1. O(1) match by route name or action
            if ($routeName && isset($nameIndex[$p['method'].':'.$routeName])) {
                $matchedKey = $nameIndex[$p['method'].':'.$routeName];
            } elseif ($routeAction && isset($actionIndex[$p['method'].':'.$routeAction])) {
                $matchedKey = $actionIndex[$p['method'].':'.$routeAction];
            }

            // 2. O(1) fallback: exact URL match
            if ($matchedKey === null) {
                $exactKey = $p['method'].' '.$p['url'];
                if (isset($uriIndex[$exactKey])) {
                    $matchedKey = $uriIndex[$exactKey];
                }
            }

            // 3. Fallback: pattern match for parameterized routes (only routes for this method)
            if ($matchedKey === null && isset($patternIndex[$p['method']])) {
                foreach ($patternIndex[$p['method']] as $entry) {
                    if (preg_match($entry['pattern'], $p['url'])) {
                        $matchedKey = $entry['key'];
                        break;
                    }
                }
            }

            if ($matchedKey !== null) {
                $registeredRoutes[$matchedKey]['profiles'][] = $profileEntry;
            }
        }

        // Remove pattern from output (not needed in frontend)
        foreach ($registeredRoutes as &$rr) {
            unset($rr['pattern']);
        }
        unset($rr);

        // Collect loaded service providers
        $serviceProviders = collect(app()->getLoadedProviders())
            ->keys()
            ->map(fn (string $provider) => [
                'class' => $provider,
                'short' => class_basename($provider),
            ])
            ->values()
            ->all();

        // Sort: routes with profiles first, then alphabetically
        uasort($registeredRoutes, function ($a, $b) {
            $aHas = count($a['profiles']) > 0 ? 0 : 1;
            $bHas = count($b['profiles']) > 0 ? 0 : 1;
            if ($aHas !== $bHas) {
                return $aHas <=> $bHas;
            }

            return ($a['method'].' '.$a['uri']) <=> ($b['method'].' '.$b['uri']);
        });

        return view('digdeep::pipeline', compact('registeredRoutes', 'serviceProviders', 'currentSection'));
    }

    public function profiler(): View
    {
        $currentSection = 'profiler';
        $topRoutes = $this->storage->topRoutes(20);

        return view('digdeep::profiler', compact('currentSection', 'topRoutes'));
    }

    public function compare(): View
    {
        $currentSection = 'compare';
        $profiles = $this->storage->all();

        return view('digdeep::compare', compact('profiles', 'currentSection'));
    }

    public function security(): View
    {
        $profiles = $this->storage->allWithData();
        $currentSection = 'security';

        $securityIssues = [];
        foreach ($profiles as $p) {
            $data = $p['data'];

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

    public function audits(): View
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

    public function urls(): View
    {
        $topRoutes = $this->storage->topRoutes(50);
        $currentSection = 'urls';

        return view('digdeep::urls', compact('topRoutes', 'currentSection'));
    }

    public function errors(): View
    {
        $currentSection = 'errors';
        $profiles = $this->storage->allWithData();

        $errors = [];
        foreach ($profiles as $p) {
            $exception = $p['data']['exception'] ?? null;
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

    public function database(): View
    {
        $currentSection = 'database';
        $profiles = $this->storage->allWithData();

        // Collect all queries from profiles
        $allQueries = [];
        $reads = 0;
        $writes = 0;
        $others = 0;
        $totalTime = 0;
        $tableAccess = [];
        $slowQueries = [];

        foreach ($profiles as $p) {
            foreach ($p['data']['queries'] ?? [] as $q) {
                $allQueries[] = $q;
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

        // Generate query optimization hints
        $hints = QueryAnalyzer::generateHints($allQueries, $schema);

        $dbStats = [
            'total_queries' => $reads + $writes + $others,
            'reads' => $reads,
            'writes' => $writes,
            'others' => $others,
            'total_time' => round($totalTime, 2),
            'avg_time' => ($reads + $writes + $others) > 0 ? round($totalTime / ($reads + $writes + $others), 2) : 0,
            'table_count' => count($schema),
        ];

        return view('digdeep::database', compact('currentSection', 'dbStats', 'tableAccess', 'slowQueries', 'schema', 'hints'));
    }

    public function trends(): View
    {
        $currentSection = 'trends';

        return view('digdeep::trends', compact('currentSection'));
    }

    public function performance(): View
    {
        $currentSection = 'performance';
        $profiles = $this->storage->all();

        $routes = $this->computeRoutePerformance($profiles);
        $global = $this->computeGlobalPerformance($profiles);

        return view('digdeep::performance', compact('currentSection', 'routes', 'global'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     * @return array<int, array<string, mixed>>
     */
    private function computeRoutePerformance(array $profiles): array
    {
        $routeMap = [];

        foreach ($profiles as $p) {
            $key = $p['method'].' '.$p['url'];
            $duration = (float) $p['duration_ms'];
            $status = (int) $p['status_code'];

            if (! isset($routeMap[$key])) {
                $routeMap[$key] = [
                    'method' => $p['method'],
                    'url' => $p['url'],
                    'durations' => [],
                    'statuses' => [],
                    'queries' => [],
                    'memories' => [],
                    'timestamps' => [],
                ];
            }

            $routeMap[$key]['durations'][] = $duration;
            $routeMap[$key]['statuses'][] = $status;
            $routeMap[$key]['queries'][] = (int) $p['query_count'];
            $routeMap[$key]['memories'][] = (float) $p['memory_peak_mb'];
            $routeMap[$key]['timestamps'][] = $p['created_at'];
        }

        $routes = [];
        foreach ($routeMap as $route) {
            $durations = $route['durations'];
            sort($durations);
            $count = count($durations);

            $errors = count(array_filter($route['statuses'], fn ($s) => $s >= 400));

            $timestamps = $route['timestamps'];
            sort($timestamps);
            $first = strtotime($timestamps[0]);
            $last = strtotime($timestamps[$count - 1]);
            $spanMinutes = max(($last - $first) / 60, 1);

            $routes[] = [
                'method' => $route['method'],
                'url' => $route['url'],
                'count' => $count,
                'p50' => $this->percentile($durations, 0.50),
                'p95' => $this->percentile($durations, 0.95),
                'p99' => $this->percentile($durations, 0.99),
                'avg_duration' => round(array_sum($durations) / $count, 1),
                'throughput_rpm' => round($count / $spanMinutes, 1),
                'error_rate' => round($errors / $count * 100, 1),
                'avg_queries' => round(array_sum($route['queries']) / $count, 1),
                'avg_memory' => round(array_sum($route['memories']) / $count, 1),
            ];
        }

        usort($routes, fn ($a, $b) => $b['p95'] <=> $a['p95']);

        return $routes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     * @return array<string, mixed>
     */
    private function computeGlobalPerformance(array $profiles): array
    {
        $allDurations = array_map(fn ($p) => (float) $p['duration_ms'], $profiles);

        if (count($allDurations) === 0) {
            return [];
        }

        sort($allDurations);

        $totalErrors = 0;
        foreach ($profiles as $p) {
            if ((int) $p['status_code'] >= 400) {
                $totalErrors++;
            }
        }

        $timestamps = array_column($profiles, 'created_at');
        sort($timestamps);
        $first = strtotime($timestamps[0]);
        $last = strtotime(end($timestamps));
        $spanMinutes = max(($last - $first) / 60, 1);

        return [
            'total' => count($allDurations),
            'p50' => $this->percentile($allDurations, 0.50),
            'p95' => $this->percentile($allDurations, 0.95),
            'p99' => $this->percentile($allDurations, 0.99),
            'throughput_rpm' => round(count($allDurations) / $spanMinutes, 1),
            'error_rate' => round($totalErrors / count($allDurations) * 100, 1),
            'avg_memory' => round(array_sum(array_map(fn ($p) => (float) $p['memory_peak_mb'], $profiles)) / count($profiles), 1),
        ];
    }

    private function percentile(array $sorted, float $p): float
    {
        if (empty($sorted)) {
            return 0.0;
        }

        $idx = (int) ceil(count($sorted) * $p) - 1;

        return round($sorted[max(0, $idx)], 1);
    }

    public function cache(): View
    {
        $currentSection = 'cache';
        $profiles = $this->storage->allWithData();

        $totalOps = 0;
        $hits = 0;
        $misses = 0;
        $writes = 0;
        $keyPatterns = [];
        $missedKeys = [];
        $perProfileHitRate = [];

        foreach ($profiles as $p) {
            $cacheOps = $p['data']['cache'] ?? [];
            $profileHits = 0;
            $profileTotal = 0;

            foreach ($cacheOps as $op) {
                $totalOps++;
                $profileTotal++;

                if ($op['type'] === 'hit') {
                    $hits++;
                    $profileHits++;
                } elseif ($op['type'] === 'miss') {
                    $misses++;
                    $missedKeys[$op['key']] = ($missedKeys[$op['key']] ?? 0) + 1;
                } elseif ($op['type'] === 'write') {
                    $writes++;
                }

                // Group by prefix (before first : or .)
                $key = $op['key'];
                $prefix = 'other';
                if (str_contains($key, ':')) {
                    $prefix = explode(':', $key)[0];
                } elseif (str_contains($key, '.')) {
                    $prefix = explode('.', $key)[0];
                }

                if (! isset($keyPatterns[$prefix])) {
                    $keyPatterns[$prefix] = ['hits' => 0, 'misses' => 0, 'writes' => 0, 'total' => 0];
                }
                $keyPatterns[$prefix]['total']++;
                $keyPatterns[$prefix][$op['type'] === 'hit' ? 'hits' : ($op['type'] === 'miss' ? 'misses' : 'writes')]++;
            }

            if ($profileTotal > 0) {
                $perProfileHitRate[] = [
                    'id' => $p['id'],
                    'url' => $p['url'],
                    'hit_rate' => round($profileHits / $profileTotal * 100),
                    'total_ops' => $profileTotal,
                    'created_at' => $p['created_at'],
                ];
            }
        }

        // Sort missed keys by count desc
        arsort($missedKeys);
        $missedKeys = array_slice($missedKeys, 0, 20, true);

        // Sort patterns by total desc
        uasort($keyPatterns, fn ($a, $b) => $b['total'] <=> $a['total']);

        $cacheStats = [
            'total_ops' => $totalOps,
            'hits' => $hits,
            'misses' => $misses,
            'writes' => $writes,
            'hit_rate' => ($hits + $misses) > 0 ? round($hits / ($hits + $misses) * 100, 1) : 0,
        ];

        $cacheDriver = config('cache.default', 'file');
        $cacheConfig = config('cache.stores.'.$cacheDriver, []);
        $cacheDriverInfo = [
            'driver' => $cacheDriver,
            'store' => $cacheConfig['driver'] ?? $cacheDriver,
            'prefix' => config('cache.prefix', ''),
        ];

        return view('digdeep::cache', compact('currentSection', 'cacheStats', 'keyPatterns', 'missedKeys', 'perProfileHitRate', 'cacheDriverInfo'));
    }
}
