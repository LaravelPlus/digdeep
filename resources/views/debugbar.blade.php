@php
    $perf        = $profile['performance'];
    $queries     = $profile['queries'] ?? [];
    $events      = $profile['events'] ?? [];
    $views       = $profile['views'] ?? [];
    $cache       = $profile['cache'] ?? [];
    $models      = $profile['models'] ?? [];
    $nPlusOne    = $profile['n_plus_one'] ?? [];
    $exception   = $profile['exception'] ?? null;
    $httpCalls   = $profile['http_client'] ?? [];
    $jobs        = $profile['jobs'] ?? [];
    $mailItems   = $profile['mail'] ?? [];
    $logs        = $profile['logs'] ?? [];

    $duration   = $perf['duration_ms'];
    $memory     = $perf['memory_peak_mb'];
    $queryCount = count($queries);
    $queryTime  = $perf['query_time_ms'];
    $eventCount = count($events);
    $viewCount  = count($views);
    $cacheHits  = count(array_filter($cache, fn ($op) => $op['type'] === 'hit'));
    $cacheMisses = count(array_filter($cache, fn ($op) => $op['type'] === 'miss'));
    $cacheWrites = count(array_filter($cache, fn ($op) => $op['type'] === 'write'));
    $modelTotal  = array_sum(array_map(fn ($m) => $m['retrieved'] + $m['created'] + $m['updated'] + $m['deleted'], $models));

    $method = $profile['request']['method'] ?? 'GET';
    $url    = $profile['request']['url'] ?? '/';
    $status = $profile['response']['status_code'] ?? 200;

    $durationThreshold  = config('digdeep.thresholds.duration_ms', 500);
    $queryCountThreshold = config('digdeep.thresholds.query_count', 20);
    $memoryThreshold    = config('digdeep.thresholds.memory_peak_mb', 64);
    $queryTimeThreshold = config('digdeep.thresholds.query_time_ms', 200);

    $durationColor = $duration > $durationThreshold ? '#ff5555' : ($duration > $durationThreshold * 0.6 ? '#ffb86c' : '#50fa7b');
    $memoryColor   = $memory > $memoryThreshold ? '#ff5555' : ($memory > $memoryThreshold * 0.6 ? '#ffb86c' : '#50fa7b');
    $queryBadColor = $queryCount > $queryCountThreshold || $queryTime > $queryTimeThreshold;
    $queryColor    = $queryBadColor ? '#ff5555' : ($queryCount > $queryCountThreshold * 0.6 ? '#ffb86c' : '#50fa7b');

    $statusColor = $status >= 500 ? '#ff5555' : ($status >= 400 ? '#ffb86c' : ($status >= 300 ? '#8be9fd' : '#50fa7b'));

    $methodColors = ['GET' => '#50fa7b', 'POST' => '#8be9fd', 'PUT' => '#ffb86c', 'PATCH' => '#ffb86c', 'DELETE' => '#ff5555'];
    $methodColor  = $methodColors[$method] ?? '#bd93f9';

    $formatSql = function ($sql, $bindings) {
        foreach ((array) $bindings as $binding) {
            if (is_null($binding)) {
                $value = 'NULL';
            } elseif (is_bool($binding)) {
                $value = $binding ? '1' : '0';
            } elseif (is_numeric($binding)) {
                $value = (string) $binding;
            } else {
                $value = "'".str_replace("'", "''", (string) $binding)."'";
            }
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    };

    $highlightSql = function ($sql, $bindings) use ($formatSql) {
        $raw = $formatSql($sql, $bindings);
        $escaped = e($raw);

        // Multi-word keywords first, then single-word
        $keywords = [
            'ORDER BY', 'GROUP BY', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'OUTER JOIN', 'CROSS JOIN',
            'IS NOT NULL', 'IS NULL', 'NOT IN', 'NOT LIKE', 'NOT BETWEEN', 'INSERT INTO',
            'SELECT', 'FROM', 'WHERE', 'JOIN', 'ON', 'AND', 'OR', 'NOT', 'IN', 'LIKE',
            'BETWEEN', 'AS', 'DISTINCT', 'LIMIT', 'OFFSET', 'HAVING', 'INSERT', 'INTO',
            'UPDATE', 'DELETE', 'SET', 'VALUES', 'RETURNING', 'COUNT', 'SUM', 'AVG',
            'MAX', 'MIN', 'UNION', 'ALL', 'EXISTS', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
            'INNER', 'LEFT', 'RIGHT', 'OUTER', 'CROSS', 'NULL',
        ];

        usort($keywords, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($keywords as $kw) {
            $escaped = preg_replace(
                '/\b'.preg_quote($kw, '/').'\b/',
                '<span class="sql-kw">'.strtoupper($kw).'</span>',
                $escaped
            );
        }

        // Highlight single-quoted strings (after e() they become &#039;...&#039;)
        $escaped = preg_replace('/&#039;([^&]*)&#039;/', '<span class="sql-str">&#039;$1&#039;</span>', $escaped);

        // Highlight standalone numbers
        $escaped = preg_replace('/(?<![<"\w])(\b\d+(?:\.\d+)?\b)/', '<span class="sql-num">$1</span>', $escaped);

        return $escaped;
    };

    $sessionData = $profile['session'] ?? [];

    $shortClass = fn ($fqn) => class_exists($fqn) ? (new \ReflectionClass($fqn))->getShortName() : basename(str_replace('\\', '/', $fqn));

    $inertia          = $profile['inertia'] ?? [];
    $inertiaComponent = $inertia['page']['component'] ?? ($inertia['component'] ?? null);
    $inertiaProps     = $inertia['page']['props'] ?? ($inertia['props'] ?? []);
    $inertiaUrl       = $inertia['page']['url'] ?? ($inertia['url'] ?? null);
    $inertiaVersion   = $inertia['page']['version'] ?? ($inertia['version'] ?? null);
    $isInertia        = $inertiaComponent !== null;
    $hasView          = $isInertia || $viewCount > 0;

    // --- Query analysis ---
    $slowQueryMs = 100; // ms threshold per individual query
    $sqlNorm     = fn ($sql) => strtolower(preg_replace('/\s+/', ' ', trim($sql)));
    $sqlCounts   = [];
    foreach ($queries as $q) {
        $key = $sqlNorm($q['sql']);
        $sqlCounts[$key] = ($sqlCounts[$key] ?? 0) + 1;
    }
    $dupSqls = array_keys(array_filter($sqlCounts, fn ($c) => $c >= 3));

    // Build N+1 SQL set for quick lookup
    $n1Sqls = [];
    foreach ($nPlusOne as $group) {
        if (isset($group['sql'])) {
            $n1Sqls[] = $sqlNorm($group['sql']);
        }
    }

    $selectStarCount = count(array_filter($queries, fn ($q) => str_contains(strtoupper($q['sql']), 'SELECT *')));
    $slowQueryList   = array_filter($queries, fn ($q) => $q['time_ms'] >= $slowQueryMs);
    $dupQueryCount   = count(array_filter($queries, fn ($q) => in_array($sqlNorm($q['sql']), $dupSqls)));

    // --- Warnings aggregation ---
    // Each warning: severity (critical|warning|info), category, title, desc, fix, panel (optional panel to jump to)
    $warnings = [];

    // ── Database ─────────────────────────────────────────────────────────────
    if (!empty($nPlusOne)) {
        $worst = array_reduce($nPlusOne, fn($c, $g) => max($c, $g['count'] ?? 0), 0);
        $warnings[] = [
            'severity' => 'critical', 'category' => 'Database',
            'title'    => 'N+1 Query Pattern ('.count($nPlusOne).' group'.( count($nPlusOne) > 1 ? 's' : '').')',
            'desc'     => 'Queries are running in a loop — '.$worst.' repeated executions in the worst group.',
            'fix'      => 'Use eager loading: Model::with(\'relation\')->get()',
            'panel'    => 'queries',
        ];
    }
    if (count($slowQueryList) > 0) {
        $worst = max(array_column(array_values($slowQueryList), 'time_ms'));
        $warnings[] = [
            'severity' => 'warning', 'category' => 'Database',
            'title'    => count($slowQueryList).' Slow '.( count($slowQueryList) === 1 ? 'Query' : 'Queries').' (worst '.round($worst, 1).'ms)',
            'desc'     => 'Individual queries exceeding '.$slowQueryMs.'ms. Missing indexes are the most common cause.',
            'fix'      => 'Run EXPLAIN on the slowest query and add a covering index.',
            'panel'    => 'queries',
        ];
    }
    if ($selectStarCount > 0) {
        $warnings[] = [
            'severity' => 'warning', 'category' => 'Database',
            'title'    => 'SELECT * Used ('.$selectStarCount.' '.( $selectStarCount === 1 ? 'query' : 'queries').')',
            'desc'     => 'Fetching all columns wastes memory, prevents index-only scans, and breaks when schema changes.',
            'fix'      => "Replace with ->select(['id', 'name', ...]) or define \$fillable on the model.",
            'panel'    => 'queries',
        ];
    }
    if ($dupQueryCount > 0 && empty($nPlusOne)) {
        $warnings[] = [
            'severity' => 'warning', 'category' => 'Database',
            'title'    => 'Duplicate Queries ('.$dupQueryCount.' repeated)',
            'desc'     => 'The same query ran 3+ times in this request — redundant DB round-trips.',
            'fix'      => 'Cache with Cache::remember() or restructure logic to query once and pass results down.',
            'panel'    => 'queries',
        ];
    }
    if ($queryCount > $queryCountThreshold) {
        $warnings[] = [
            'severity' => 'warning', 'category' => 'Database',
            'title'    => 'High Query Count ('.$queryCount.' / limit '.$queryCountThreshold.')',
            'desc'     => 'Too many queries per request increases latency and DB connection pressure.',
            'fix'      => 'Eager-load relationships with with(); batch lookups with whereIn(); cache config/lookup queries.',
            'panel'    => 'queries',
        ];
    }
    if ($queryTime > $queryTimeThreshold) {
        $warnings[] = [
            'severity' => 'warning', 'category' => 'Database',
            'title'    => 'High Total Query Time ('.round($queryTime, 1).'ms / limit '.$queryTimeThreshold.'ms)',
            'desc'     => 'Cumulative DB execution time is high, even if individual queries look fast.',
            'fix'      => 'Reduce query count, add indexes, or move expensive aggregations to queued jobs.',
            'panel'    => 'queries',
        ];
    }

    // ── Performance ───────────────────────────────────────────────────────────
    if ($duration > $durationThreshold) {
        $warnings[] = [
            'severity' => $duration > $durationThreshold * 2 ? 'critical' : 'warning',
            'category' => 'Performance',
            'title'    => 'Slow Response ('.round($duration, 0).'ms / limit '.$durationThreshold.'ms)',
            'desc'     => 'Users experience this as lag. Everything above ~200ms is perceivable.',
            'fix'      => 'Cache full responses; move slow work to queued jobs; profile the controller with Telescope.',
            'panel'    => 'lifecycle',
        ];
    }
    if ($memory > $memoryThreshold) {
        $warnings[] = [
            'severity' => 'warning', 'category' => 'Performance',
            'title'    => 'High Memory Peak ('.$memory.'MB / limit '.$memoryThreshold.'MB)',
            'desc'     => 'Large memory footprint risks hitting PHP limits and slows GC cycles.',
            'fix'      => 'Use cursor() or chunk() for large collections; avoid loading full Eloquent models when IDs suffice.',
            'panel'    => 'lifecycle',
        ];
    }
    $responseBodySize = $profile['response']['body_size'] ?? 0;
    if ($responseBodySize > 512 * 1024) { // >512KB HTML/JSON payload
        $warnings[] = [
            'severity' => 'warning', 'category' => 'Performance',
            'title'    => 'Large Response Body ('.round($responseBodySize / 1024).'KB)',
            'desc'     => 'Large payloads increase bandwidth cost and Time-to-First-Byte on slow connections.',
            'fix'      => 'Paginate API responses; compress with gzip; strip unused Inertia props with ->only().',
            'panel'    => null,
        ];
    }

    // ── Inertia ───────────────────────────────────────────────────────────────
    if ($isInertia && !empty($inertiaProps)) {
        $propsJson = json_encode($inertiaProps);
        $propsSize = $propsJson ? mb_strlen($propsJson) : 0;
        if ($propsSize > 200 * 1024) { // >200KB props
            $warnings[] = [
                'severity' => 'warning', 'category' => 'Inertia',
                'title'    => 'Large Inertia Props ('.round($propsSize / 1024).'KB)',
                'desc'     => 'Serialised props are sent on every page visit and on every Inertia navigation.',
                'fix'      => 'Use ->only() to limit props; use deferred props for data not needed on first render.',
                'panel'    => 'views',
            ];
        }
    }

    // ── HTTP Client ───────────────────────────────────────────────────────────
    if (!empty($httpCalls)) {
        $slowHttp = array_filter($httpCalls, fn ($r) => $r['duration_ms'] > 1000);
        $failedHttp = array_filter($httpCalls, fn ($r) => ($r['status'] ?? 200) >= 400);
        if (!empty($slowHttp)) {
            $worstHttp = max(array_column(array_values($slowHttp), 'duration_ms'));
            $warnings[] = [
                'severity' => 'warning', 'category' => 'HTTP Client',
                'title'    => count($slowHttp).' Slow External '.( count($slowHttp) === 1 ? 'Request' : 'Requests').' (worst '.round($worstHttp).'ms)',
                'desc'     => 'Synchronous external HTTP calls block the PHP worker for the full duration.',
                'fix'      => 'Dispatch to a queued job; use Http::pool() for concurrent requests; cache the response.',
                'panel'    => null,
            ];
        }
        if (!empty($failedHttp)) {
            $warnings[] = [
                'severity' => 'critical', 'category' => 'HTTP Client',
                'title'    => count($failedHttp).' Failed External '.( count($failedHttp) === 1 ? 'Request' : 'Requests'),
                'desc'     => implode(', ', array_map(fn ($r) => ($r['status'] ?? '?').' '.$r['method'].' '.parse_url($r['url'], PHP_URL_HOST), array_values($failedHttp))),
                'fix'      => 'Add retry logic with Http::retry(); handle failures gracefully with a fallback.',
                'panel'    => null,
            ];
        }
        if (count($httpCalls) >= 5) {
            $warnings[] = [
                'severity' => 'info', 'category' => 'HTTP Client',
                'title'    => count($httpCalls).' External Requests in One Response',
                'desc'     => 'Many outbound calls add up and make the response fragile if any service is slow.',
                'fix'      => 'Use Http::pool() to run them concurrently; cache results that rarely change.',
                'panel'    => null,
            ];
        }
    }

    // ── Cache ─────────────────────────────────────────────────────────────────
    if (($cacheHits + $cacheMisses) >= 5 && $hitRate < 50) {
        $warnings[] = [
            'severity' => 'info', 'category' => 'Cache',
            'title'    => 'Low Cache Hit Rate ('.$hitRate.'%)',
            'desc'     => $cacheHits.' hits vs '.$cacheMisses.' misses — the cache is not being used effectively.',
            'fix'      => 'Ensure TTLs are appropriate; pre-warm frequently read keys; check key naming consistency.',
            'panel'    => 'cache',
        ];
    }

    // ── Exception ─────────────────────────────────────────────────────────────
    if ($exception) {
        $warnings[] = [
            'severity' => 'critical', 'category' => 'Exception',
            'title'    => ($exception['class'] ?? 'Exception').' thrown',
            'desc'     => mb_substr($exception['message'] ?? '', 0, 120).( isset($exception['file']) ? ' — '.basename($exception['file']).':'.($exception['line'] ?? '?') : ''),
            'fix'      => 'Handle or report this in app/Exceptions/Handler.php.',
            'panel'    => 'exception',
        ];
    }

    // Sort: critical first, then warning, then info
    usort($warnings, function ($a, $b) {
        $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
        return ($order[$a['severity']] ?? 3) <=> ($order[$b['severity']] ?? 3);
    });

    $warningCount  = count($warnings);
    $criticalCount = count(array_filter($warnings, fn ($w) => $w['severity'] === 'critical'));
    $warnCount     = count(array_filter($warnings, fn ($w) => $w['severity'] === 'warning'));
    $infoCount     = count(array_filter($warnings, fn ($w) => $w['severity'] === 'info'));
    $hasWarnings   = $warningCount > 0;
    $warnCategories = array_unique(array_column($warnings, 'category'));

    // Problem count for query metric badge
    $problemQueryCount = count($slowQueryList) + ($dupQueryCount > 0 && empty($nPlusOne) ? 1 : 0) + (!empty($nPlusOne) ? count($nPlusOne) : 0) + ($selectStarCount > 0 ? 1 : 0);
    $fastMs            = 0.5; // queries under this are "very fast" (possibly DB-cached)

    // Cache: group by key with hit/miss/write counts
    $cacheByKey = [];
    foreach ($cache as $op) {
        $k = $op['key'];
        if (!isset($cacheByKey[$k])) {
            $cacheByKey[$k] = ['hit' => 0, 'miss' => 0, 'write' => 0];
        }
        $cacheByKey[$k][$op['type']]++;
    }
    arsort($cacheByKey);
    $hitRate = ($cacheHits + $cacheMisses) > 0 ? round($cacheHits / ($cacheHits + $cacheMisses) * 100) : 0;

    $hasAiSdk = ! empty(config('digdeep.ai_key')) || function_exists('Laravel\Ai\agent');

    // --- Audits ---
    $responseHeaders = $profile['response']['headers'] ?? [];
    $headerKeys      = array_map('strtolower', array_keys($responseHeaders));

    // Each audit: category, severity (critical|warning|info), pass (bool), label, detail, fix
    $audits = [
        // ── Database ──────────────────────────────────────────────────────────
        [
            'category' => 'Database',
            'severity' => 'critical',
            'pass'     => empty($nPlusOne),
            'label'    => 'No N+1 queries',
            'detail'   => empty($nPlusOne) ? 'No repeated query patterns detected.' : count($nPlusOne).' N+1 group(s) found — queries run in a loop.',
            'fix'      => 'Use eager loading: Model::with(\'relation\')->get()',
        ],
        [
            'category' => 'Database',
            'severity' => 'warning',
            'pass'     => $selectStarCount === 0,
            'label'    => 'No SELECT * queries',
            'detail'   => $selectStarCount === 0 ? 'All queries select specific columns.' : $selectStarCount.' SELECT * found — fetches unused columns.',
            'fix'      => 'Replace SELECT * with ->select([\'id\', \'name\', ...])',
        ],
        [
            'category' => 'Database',
            'severity' => 'warning',
            'pass'     => count($slowQueryList) === 0,
            'label'    => 'No slow queries (>'.$slowQueryMs.'ms)',
            'detail'   => count($slowQueryList) === 0 ? 'All queries completed under '.$slowQueryMs.'ms.' : count($slowQueryList).' slow quer'.( count($slowQueryList) === 1 ? 'y' : 'ies').' — worst: '.round(max(array_column(array_values($slowQueryList), 'time_ms')), 1).'ms.',
            'fix'      => 'Run EXPLAIN on the query and add a covering index.',
        ],
        [
            'category' => 'Database',
            'severity' => 'warning',
            'pass'     => $queryCount <= $queryCountThreshold,
            'label'    => 'Query count ≤ '.$queryCountThreshold,
            'detail'   => $queryCount.' quer'.($queryCount === 1 ? 'y' : 'ies').' executed (limit: '.$queryCountThreshold.').',
            'fix'      => 'Eager-load relationships; cache repeated lookups with remember().',
        ],
        [
            'category' => 'Database',
            'severity' => 'warning',
            'pass'     => $dupQueryCount === 0,
            'label'    => 'No duplicate queries',
            'detail'   => $dupQueryCount === 0 ? 'No identical queries repeated 3+ times.' : $dupQueryCount.' duplicate quer'.($dupQueryCount === 1 ? 'y' : 'ies').' run 3+ times.',
            'fix'      => 'Cache with Cache::remember() or restructure to query once.',
        ],
        [
            'category' => 'Database',
            'severity' => 'info',
            'pass'     => $queryTime <= $queryTimeThreshold,
            'label'    => 'Total query time ≤ '.$queryTimeThreshold.'ms',
            'detail'   => round($queryTime, 1).'ms total DB time (limit: '.$queryTimeThreshold.'ms).',
            'fix'      => 'Optimise the slowest queries; move heavy work to queued jobs.',
        ],
        // ── Performance ───────────────────────────────────────────────────────
        [
            'category' => 'Performance',
            'severity' => $duration > $durationThreshold * 2 ? 'critical' : 'warning',
            'pass'     => $duration <= $durationThreshold,
            'label'    => 'Response time ≤ '.$durationThreshold.'ms',
            'detail'   => round($duration, 0).'ms response time (limit: '.$durationThreshold.'ms).',
            'fix'      => 'Cache the response, push heavy work to queued jobs, or profile the controller.',
        ],
        [
            'category' => 'Performance',
            'severity' => 'warning',
            'pass'     => $memory <= $memoryThreshold,
            'label'    => 'Memory ≤ '.$memoryThreshold.'MB',
            'detail'   => $memory.'MB peak memory (limit: '.$memoryThreshold.'MB).',
            'fix'      => 'Use cursor() for large Eloquent result sets; chunk() for batch processing.',
        ],
        [
            'category' => 'Performance',
            'severity' => 'info',
            'pass'     => $viewCount <= 20 || $isInertia,
            'label'    => 'Reasonable view/partial count',
            'detail'   => $isInertia ? 'Inertia response — no Blade partials rendered.' : $viewCount.' Blade view'.($viewCount === 1 ? '' : 's').' rendered.',
            'fix'      => 'Cache expensive partials; flatten deeply nested view hierarchies.',
        ],
        // ── Security ──────────────────────────────────────────────────────────
        [
            'category' => 'Security',
            'severity' => 'warning',
            'pass'     => in_array('x-frame-options', $headerKeys) || in_array('content-security-policy', $headerKeys),
            'label'    => 'Clickjacking protection header',
            'detail'   => (in_array('x-frame-options', $headerKeys) || in_array('content-security-policy', $headerKeys))
                ? 'X-Frame-Options or CSP header present.'
                : 'Missing X-Frame-Options and Content-Security-Policy.',
            'fix'      => "Add \Illuminate\Http\Middleware\FrameGuard to your middleware stack.",
        ],
        [
            'category' => 'Security',
            'severity' => 'warning',
            'pass'     => !in_array('server', $headerKeys),
            'label'    => 'Server header not exposed',
            'detail'   => in_array('server', $headerKeys) ? 'Server: '.($responseHeaders['server'][0] ?? '?').' leaks software info.' : 'No Server header in response.',
            'fix'      => 'Remove the Server header at the web server (nginx: server_tokens off).',
        ],
        [
            'category' => 'Security',
            'severity' => 'info',
            'pass'     => in_array('x-content-type-options', $headerKeys),
            'label'    => 'X-Content-Type-Options: nosniff',
            'detail'   => in_array('x-content-type-options', $headerKeys) ? 'nosniff header is set.' : 'Missing X-Content-Type-Options: nosniff.',
            'fix'      => "Add response()->header('X-Content-Type-Options', 'nosniff') or use a global middleware.",
        ],
        // ── Correctness ───────────────────────────────────────────────────────
        [
            'category' => 'Correctness',
            'severity' => 'critical',
            'pass'     => $exception === null,
            'label'    => 'No unhandled exception',
            'detail'   => $exception === null ? 'Request completed without exceptions.' : ($exception['class'] ?? 'Exception').': '.mb_substr($exception['message'] ?? '', 0, 80),
            'fix'      => 'Handle or log the exception in app/Exceptions/Handler.php.',
        ],
        [
            'category' => 'Correctness',
            'severity' => 'critical',
            'pass'     => $status < 500,
            'label'    => 'No 5xx server error',
            'detail'   => 'HTTP '.$status.' response.',
            'fix'      => 'Investigate the exception in the Errors tab or Laravel logs.',
        ],
    ];

    // Score: start at 100, deduct by severity per failure
    $auditScore = 100;
    $severityDeduct = ['critical' => 20, 'warning' => 8, 'info' => 3];
    foreach ($audits as $a) {
        if (!$a['pass']) {
            $auditScore -= $severityDeduct[$a['severity']] ?? 5;
        }
    }
    $auditScore = max(0, $auditScore);
    $auditScoreColor = $auditScore >= 80 ? '#50fa7b' : ($auditScore >= 50 ? '#ffb86c' : '#ff5555');
    $auditCategories = array_unique(array_column($audits, 'category'));
@endphp
<div id="__digdeep__">
<style>
#__digdeep__ *{box-sizing:border-box;font-family:'JetBrains Mono','Fira Code',ui-monospace,monospace;line-height:1.4;}
#__digdeep__ a{text-decoration:none;}
#__digdeep_panels__{position:fixed!important;bottom:36px!important;left:0!important;right:0!important;z-index:2147483645!important;background:#282a36;border-top:1px solid #44475a;max-height:380px;overflow:hidden;display:none;box-shadow:0 -8px 32px rgba(0,0,0,.5);}
#__digdeep_panels_inner__{max-height:380px;overflow-y:auto;padding:0;}
#__digdeep_panels__ .ddp{display:none;padding:12px 16px;}
#__digdeep_panels__ .ddp.active{display:block;}
#__digdeep_panels__ .ddp-title{font-size:11px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
#__digdeep_panels__ table{width:100%;border-collapse:collapse;font-size:11.5px;}
#__digdeep_panels__ table th{color:#6272a4;font-size:10px;text-transform:uppercase;letter-spacing:.06em;padding:4px 8px;text-align:left;border-bottom:1px solid #44475a;font-weight:600;}
#__digdeep_panels__ table td{padding:5px 8px;border-bottom:1px solid rgba(68,71,90,.4);color:#f8f8f2;vertical-align:top;}
#__digdeep_panels__ table tr:last-child td{border-bottom:none;}
#__digdeep_panels__ table tr:hover td{background:rgba(68,71,90,.3);}
#__digdeep_panels__ .ddsql{color:#f8f8f2;font-size:11px;word-break:break-all;max-width:600px;}
#__digdeep_panels__ .ddtime{color:#8be9fd;white-space:nowrap;text-align:right;}
#__digdeep_panels__ .ddcaller{color:#6272a4;font-size:10px;white-space:nowrap;}
#__digdeep_panels__ .ddbadge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:700;}
#__digdeep_panels__ .dd-hit{background:rgba(80,250,123,.12);color:#50fa7b;}
#__digdeep_panels__ .dd-miss{background:rgba(255,85,85,.12);color:#ff5555;}
#__digdeep_panels__ .dd-write{background:rgba(139,233,253,.12);color:#8be9fd;}
#__digdeep_panels__ .dd-retrieved{color:#bd93f9;}
#__digdeep_panels__ .dd-created{color:#50fa7b;}
#__digdeep_panels__ .dd-updated{color:#ffb86c;}
#__digdeep_panels__ .dd-deleted{color:#ff5555;}
#__digdeep_panels__ .dd-warn{background:rgba(255,85,85,.1);border-left:3px solid #ff5555;padding:8px 12px;border-radius:0 4px 4px 0;margin-bottom:10px;font-size:11px;color:#ff5555;}
#__digdeep_panels__ .dd-empty{color:#6272a4;font-size:12px;padding:20px 0;text-align:center;}
#__digdeep_bar__{position:fixed!important;bottom:0!important;left:0!important;right:0!important;height:36px;z-index:2147483646!important;background:#21222c;border-top:1px solid #44475a;display:flex;align-items:center;gap:0;font-size:11.5px;color:#f8f8f2;user-select:none;box-shadow:0 -2px 12px rgba(0,0,0,.4);transform:none!important;}
#__digdeep_bar__.collapsed #__digdeep_panels__{display:none!important;}
#__digdeep_bar__ .dd-logo{display:flex;align-items:center;gap:6px;padding:0 10px 0 12px;height:100%;border-right:1px solid #44475a;cursor:pointer;flex-shrink:0;color:#bd93f9;transition:background .15s;}
#__digdeep_bar__ .dd-logo:hover{background:rgba(189,147,249,.08);}
#__digdeep_bar__ .dd-logo svg{width:14px;height:14px;}
#__digdeep_bar__ .dd-logo span{font-size:11px;font-weight:700;color:#bd93f9;letter-spacing:.02em;}
#__digdeep_bar__ .dd-req{display:flex;align-items:center;gap:6px;padding:0 12px;height:100%;border-right:1px solid #44475a;flex-shrink:0;transition:background .15s;}
#__digdeep_bar__ .dd-req:hover{background:rgba(255,255,255,.05);}
#__digdeep_bar__ .dd-req.active{background:rgba(189,147,249,.1);}
#__digdeep_bar__ .dd-req .dd-method{font-size:10px;font-weight:800;letter-spacing:.04em;}
#__digdeep_bar__ .dd-req .dd-url{color:#6272a4;font-size:11px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#__digdeep_bar__ .dd-req .dd-status{font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;background:rgba(0,0,0,.2);}
#__digdeep_bar__ .dd-metrics{display:flex;align-items:center;flex:1;height:100%;overflow:hidden;}
#__digdeep_bar__ .ddm{display:flex;align-items:center;gap:4px;padding:0 10px;height:100%;cursor:pointer;border-right:1px solid #44475a;transition:background .15s;flex-shrink:0;}
#__digdeep_bar__ .ddm:hover{background:rgba(255,255,255,.05);}
#__digdeep_bar__ .ddm.active{background:rgba(189,147,249,.1);}
#__digdeep_bar__ .ddm svg{width:12px;height:12px;opacity:.6;flex-shrink:0;}
#__digdeep_bar__ .ddm .ddm-val{font-weight:600;font-size:11px;}
#__digdeep_bar__ .ddm .ddm-lbl{color:#6272a4;font-size:10px;}
#__digdeep_bar__ .ddm .ddm-n1{background:#ff5555;color:#fff;font-size:9px;font-weight:800;padding:0 3px;border-radius:2px;margin-left:2px;}
#__digdeep_bar__ .dd-exc{display:flex;align-items:center;gap:4px;padding:0 10px;height:100%;background:rgba(255,85,85,.1);border-right:1px solid rgba(255,85,85,.3);cursor:pointer;flex-shrink:0;transition:background .15s;}
#__digdeep_bar__ .dd-exc:hover{background:rgba(255,85,85,.2);}
#__digdeep_bar__ .dd-exc svg{width:12px;height:12px;color:#ff5555;}
#__digdeep_bar__ .dd-exc span{color:#ff5555;font-size:11px;font-weight:600;}
#__digdeep_bar__ .dd-actions{display:flex;align-items:center;gap:0;margin-left:auto;height:100%;}
#__digdeep_bar__ .dd-actions a,#__digdeep_bar__ .dd-actions button{display:flex;align-items:center;gap:4px;padding:0 10px;height:100%;color:#6272a4;font-size:11px;cursor:pointer;border:none;background:none;border-left:1px solid #44475a;transition:all .15s;white-space:nowrap;font-family:inherit;}
#__digdeep_bar__ .dd-actions a:hover,#__digdeep_bar__ .dd-actions button:hover{color:#f8f8f2;background:rgba(255,255,255,.05);}
#__digdeep_bar__ .dd-actions a svg,#__digdeep_bar__ .dd-actions button svg{width:11px;height:11px;}
#__digdeep_bar__ .dd-stat{display:flex;align-items:center;gap:4px;padding:0 10px;height:100%;border-left:1px solid #44475a;white-space:nowrap;flex-shrink:0;}
#__digdeep_bar__ .dd-stat svg{width:11px;height:11px;opacity:.4;flex-shrink:0;}
#__digdeep_bar__ .dd-stat span{font-size:11px;font-weight:600;}
#__digdeep_panels__ .dd-route-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;padding:12px 16px;}
#__digdeep_panels__ .dd-route-card{background:#21222c;border:1px solid #44475a;border-radius:8px;padding:12px;}
#__digdeep_panels__ .dd-route-card .ddk{color:#6272a4;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-bottom:6px;}
#__digdeep_panels__ .dd-route-card .ddv{color:#f8f8f2;font-size:11.5px;word-break:break-all;}
#__digdeep_panels__ .dd-route-card .ddv.hi{color:#bd93f9;}
#__digdeep_panels__ .dd-route-card .ddv.cyan{color:#8be9fd;}
#__digdeep_panels__ .dd-route-card .ddv.green{color:#50fa7b;}
#__digdeep_panels__ .dd-mw-pill{display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;background:rgba(68,71,90,.5);color:#6272a4;margin:1px 2px 1px 0;}
#__digdeep_panels__ .dd-lc-bar{display:flex;align-items:center;gap:8px;margin:3px 0;}
#__digdeep_panels__ .dd-lc-bar .dd-lc-name{color:#6272a4;font-size:10px;width:120px;flex-shrink:0;}
#__digdeep_panels__ .dd-lc-bar .dd-lc-track{flex:1;background:#44475a;border-radius:2px;height:4px;overflow:hidden;}
#__digdeep_panels__ .dd-lc-bar .dd-lc-fill{background:#bd93f9;height:100%;border-radius:2px;min-width:2px;}
#__digdeep_panels__ .dd-lc-bar .dd-lc-ms{color:#6272a4;font-size:10px;width:50px;text-align:right;}
#__digdeep_panels__ .dd-lc-section{padding:12px 16px 0;}
#__digdeep_panels__ .dd-lc-section + .dd-lc-section{padding-top:16px;border-top:1px solid #44475a;margin-top:16px;}
#__digdeep_panels__ .dd-lc-waterfall{position:relative;margin-top:8px;}
#__digdeep_panels__ .dd-lc-row{display:grid;grid-template-columns:160px 1fr 60px;align-items:center;gap:8px;padding:3px 0;}
#__digdeep_panels__ .dd-lc-row:hover{background:rgba(68,71,90,.2);border-radius:4px;}
#__digdeep_panels__ .dd-lc-label{font-size:10.5px;color:#6272a4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
#__digdeep_panels__ .dd-lc-label.main{color:#f8f8f2;font-weight:600;}
#__digdeep_panels__ .dd-lc-track{background:#2d2f3e;border-radius:2px;height:6px;overflow:hidden;position:relative;}
#__digdeep_panels__ .dd-lc-fill{height:100%;border-radius:2px;min-width:2px;position:absolute;top:0;}
#__digdeep_panels__ .dd-lc-val{font-size:10px;color:#8be9fd;text-align:right;white-space:nowrap;}
#__digdeep_panels__ .dd-perf-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:12px 16px;}
#__digdeep_panels__ .dd-perf-card{background:#21222c;border:1px solid #44475a;border-radius:8px;padding:10px 12px;}
#__digdeep_panels__ .dd-perf-card .dd-pc-val{font-size:20px;font-weight:700;margin-bottom:2px;}
#__digdeep_panels__ .dd-perf-card .dd-pc-lbl{font-size:10px;color:#6272a4;text-transform:uppercase;letter-spacing:.06em;}
#__digdeep_panels__ .dd-perf-card .dd-pc-sub{font-size:10px;color:#6272a4;margin-top:4px;}
#__digdeep_panels__ table tr.q-slow td{background:rgba(255,184,108,.08);}
#__digdeep_panels__ table tr.q-slow .ddtime{color:#ffb86c!important;}
#__digdeep_panels__ table tr.q-n1 td{background:rgba(255,85,85,.08);}
#__digdeep_panels__ table tr.q-n1 .ddtime{color:#ff5555!important;}
#__digdeep_panels__ table tr.q-dup td{background:rgba(241,250,140,.07);}
#__digdeep_panels__ table tr.q-star td{background:rgba(241,250,140,.08);}
#__digdeep_panels__ .q-badge.star{background:rgba(241,250,140,.2);color:#f1fa8c;}
#__digdeep_panels__ .q-explain{margin-top:5px;padding:5px 8px;border-radius:4px;font-size:10px;line-height:1.5;}
#__digdeep_panels__ .q-explain-n1{background:rgba(255,85,85,.08);border-left:2px solid #ff5555;}
#__digdeep_panels__ .q-explain-slow{background:rgba(255,184,108,.08);border-left:2px solid #ffb86c;}
#__digdeep_panels__ .q-explain-star{background:rgba(241,250,140,.06);border-left:2px solid #f1fa8c;}
#__digdeep_panels__ .q-explain-dup{background:rgba(241,250,140,.06);border-left:2px solid #f1fa8c;}
#__digdeep_panels__ .q-explain-title{font-weight:700;margin-right:6px;}
#__digdeep_panels__ .q-explain-n1 .q-explain-title{color:#ff5555;}
#__digdeep_panels__ .q-explain-slow .q-explain-title{color:#ffb86c;}
#__digdeep_panels__ .q-explain-star .q-explain-title{color:#f1fa8c;}
#__digdeep_panels__ .q-explain-dup .q-explain-title{color:#f1fa8c;}
#__digdeep_panels__ .q-explain-desc{color:#6272a4;}
#__digdeep_panels__ .q-fix{display:inline-block;margin-top:3px;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700;letter-spacing:.03em;background:rgba(68,71,90,.5);color:#8be9fd;}
#__digdeep_panels__ .q-ai-row{display:flex;align-items:center;gap:6px;margin-top:5px;}
#__digdeep_panels__ .q-ai-btn{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:all .15s;}
#__digdeep_panels__ .q-ai-btn.ai{background:linear-gradient(135deg,rgba(189,147,249,.2),rgba(255,121,198,.15));color:#bd93f9;border:1px solid rgba(189,147,249,.3);}
#__digdeep_panels__ .q-ai-btn.ai:hover{background:linear-gradient(135deg,rgba(189,147,249,.3),rgba(255,121,198,.25));border-color:rgba(189,147,249,.5);}
#__digdeep_panels__ .q-ai-btn.copy{background:rgba(68,71,90,.4);color:#6272a4;border:1px solid rgba(68,71,90,.6);}
#__digdeep_panels__ .q-ai-btn.copy:hover{color:#f8f8f2;background:rgba(68,71,90,.6);}
#__digdeep_panels__ .q-ai-btn:disabled{opacity:.5;cursor:not-allowed;}
#__digdeep_panels__ .q-ai-result{margin-top:6px;padding:8px 10px;background:#1a1b26;border:1px solid #44475a;border-radius:5px;font-size:10.5px;color:#f8f8f2;white-space:pre-wrap;line-height:1.6;max-height:180px;overflow-y:auto;}
#__digdeep_panels__ .q-ai-result .ai-section{color:#bd93f9;font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.06em;margin-top:4px;display:block;}
#__digdeep_panels__ .q-ai-result code{background:rgba(68,71,90,.5);padding:1px 4px;border-radius:2px;font-size:10px;color:#50fa7b;}
#__digdeep_panels__ .q-badge{display:inline-block;padding:1px 4px;border-radius:2px;font-size:9px;font-weight:700;margin-left:4px;vertical-align:middle;}
#__digdeep_panels__ .q-badge.slow{background:rgba(255,184,108,.2);color:#ffb86c;}
#__digdeep_panels__ .q-badge.n1{background:rgba(255,85,85,.2);color:#ff5555;}
#__digdeep_panels__ .q-badge.dup{background:rgba(241,250,140,.15);color:#f1fa8c;}
#__digdeep_panels__ details{display:inline;}
#__digdeep_panels__ details summary{cursor:pointer;color:#8be9fd;font-size:10px;list-style:none;display:inline-flex;align-items:center;gap:3px;}
#__digdeep_panels__ details summary::-webkit-details-marker{display:none;}
#__digdeep_panels__ details summary::before{content:'▶';font-size:8px;transition:transform .15s;display:inline-block;}
#__digdeep_panels__ details[open] summary::before{transform:rotate(90deg);}
#__digdeep_panels__ details .dd-json{display:block;margin-top:4px;background:#1e1f2b;border:1px solid #44475a;border-radius:4px;padding:6px 8px;font-size:10px;color:#f8f8f2;white-space:pre-wrap;word-break:break-all;max-height:200px;overflow-y:auto;max-width:500px;}
#__digdeep_panels__ .dd-warn-row{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid rgba(68,71,90,.3);}
#__digdeep_panels__ .dd-warn-row:last-child{border-bottom:none;}
#__digdeep_panels__ .dd-warn-icon{flex-shrink:0;width:20px;height:20px;display:flex;align-items:center;justify-content:center;}
#__digdeep_panels__ .dd-warn-body{flex:1;}
#__digdeep_panels__ .dd-warn-title{font-size:11.5px;font-weight:600;margin-bottom:2px;}
#__digdeep_panels__ .dd-warn-desc{font-size:10px;color:#6272a4;line-height:1.5;}
#__digdeep_panels__ .sev-critical{color:#ff5555;}
#__digdeep_panels__ .sev-warning{color:#ffb86c;}
#__digdeep_panels__ .sev-info{color:#8be9fd;}
#__digdeep_panels__ .dd-audit-section{padding:10px 16px;border-bottom:1px solid #44475a;}
#__digdeep_panels__ .dd-audit-section:last-child{border-bottom:none;}
#__digdeep_panels__ .dd-audit-pass{color:#50fa7b;font-size:10px;}
#__digdeep_panels__ .dd-audit-fail{color:#ff5555;font-size:10px;}
#__digdeep_panels__ .dd-audit-warn{color:#ffb86c;font-size:10px;}
#__digdeep_bar__ .ddm-warn{background:#ff5555;color:#fff;font-size:9px;font-weight:800;padding:0 4px;border-radius:2px;margin-left:2px;}
#__digdeep_bar__ .ddm-warn.orange{background:#ffb86c;color:#21222c;}
#__digdeep_panels__ .ddp-inner{padding:12px 16px;}
#__digdeep_panels__ .ddp-close{position:sticky;top:0;display:flex;align-items:center;justify-content:space-between;background:#21222c;border-bottom:1px solid #44475a;padding:6px 16px;z-index:1;}
#__digdeep_panels__ .ddp-close button{background:none;border:none;color:#6272a4;cursor:pointer;font-size:11px;display:flex;align-items:center;gap:4px;padding:2px 6px;border-radius:3px;font-family:inherit;transition:all .15s;}
#__digdeep_panels__ .ddp-close button:hover{color:#f8f8f2;background:rgba(255,255,255,.05);}
#__digdeep_panels__ .ddp-close .ddp-title{margin-bottom:0;}
#__digdeep_fab__{position:fixed!important;bottom:12px!important;left:12px!important;z-index:2147483646!important;width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#bd93f9,#ff79c6);box-shadow:0 4px 16px rgba(189,147,249,.4);cursor:pointer;display:none;align-items:center;justify-content:center;border:none;transition:transform .15s,box-shadow .15s;}
#__digdeep_fab__:hover{transform:scale(1.1);box-shadow:0 6px 20px rgba(189,147,249,.55);}
#__digdeep_fab__ svg{width:16px;height:16px;color:#fff;}
@keyframes dd-spin{to{transform:rotate(360deg);}}
/* SQL syntax highlighting */
#__digdeep_panels__ .sql-kw{color:#ff79c6;font-weight:700;}
#__digdeep_panels__ .sql-str{color:#f1fa8c;}
#__digdeep_panels__ .sql-num{color:#bd93f9;}
/* Query search */
#__dd_qsearch__{display:block;width:calc(100% - 32px);margin:8px 16px 0;background:#1e1f2b;border:1px solid #44475a;border-radius:4px;padding:5px 10px;color:#f8f8f2;font-size:11px;font-family:inherit;outline:none;}
#__dd_qsearch__:focus{border-color:#bd93f9;}
/* Resize handle */
#__dd_resize__{position:absolute;top:0;left:0;right:0;height:5px;cursor:ns-resize;z-index:10;}
#__dd_resize__:hover,#__dd_resize__.active{background:rgba(189,147,249,.35);}
#__digdeep_panels__{position:relative;}
</style>

{{-- Panels --}}
<div id="__digdeep_panels__">
  <div id="__dd_resize__" title="Drag to resize"></div>
  <div id="__digdeep_panels_inner__">

    {{-- Close bar --}}
    <div class="ddp-close">
      <span class="ddp-title" id="__dd_panel_label__">Panel</span>
      <button onclick="__dd.closePanel()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        Close
      </button>
    </div>

    {{-- Queries panel --}}
    <div class="ddp" id="__ddp_queries__">
      @if($hasNPlusOne ?? !empty($nPlusOne))
        <div class="dd-warn">
          ⚠ N+1 query pattern detected — {{ count($nPlusOne) }} group(s) of repeated queries.
        </div>
      @endif
      @if(empty($queries))
        <div class="dd-empty">No queries recorded.</div>
      @else
        <input id="__dd_qsearch__" type="text" placeholder="Filter queries by SQL…" oninput="__dd.filterQueries(this.value)">
        <table>
          <thead><tr>
            <th>#</th>
            <th>Query</th>
            <th>Time</th>
            <th>Caller</th>
          </tr></thead>
          <tbody>
            @foreach($queries as $i => $query)
              @php
                $qNorm        = $sqlNorm($query['sql']);
                $isN1         = in_array($qNorm, $n1Sqls);
                $isSlow       = $query['time_ms'] >= $slowQueryMs;
                $isDup        = in_array($qNorm, $dupSqls);
                $isFast       = $query['time_ms'] < $fastMs;
                $isSelectStar = str_contains(strtoupper($query['sql']), 'SELECT *');
                $rowClass     = $isN1 ? 'q-n1' : ($isSlow ? 'q-slow' : ($isSelectStar ? 'q-star' : ($isDup ? 'q-dup' : '')));
              @endphp
              <tr class="{{ $rowClass }}" data-sql="{{ e(strtolower($query['sql'])) }}">
                <td style="color:#6272a4;text-align:right;padding-right:4px;">{{ $i + 1 }}</td>
                <td>
                  <div class="ddsql">
                    {!! $highlightSql($query['sql'], $query['bindings']) !!}
                    @if($isN1)<span class="q-badge n1">N+1</span>@endif
                    @if($isSlow && !$isN1)<span class="q-badge slow">slow</span>@endif
                    @if($isSelectStar)<span class="q-badge star">SELECT *</span>@endif
                    @if($isDup && !$isN1 && !$isSelectStar)<span class="q-badge dup">dup</span>@endif
                    @if($isFast)<span class="q-badge" style="background:rgba(80,250,123,.12);color:#50fa7b;">⚡ cached</span>@endif
                  </div>
                  @php
                    $aiIssues = [];
                    if ($isN1) {
                        $aiIssues[] = [
                            'type'   => 'n1',
                            'class'  => 'n1',
                            'title'  => 'N+1 Query Pattern',
                            'desc'   => 'This query runs inside a loop — once per parent record. Load all related records upfront with eager loading.',
                            'fix'    => "→ Use: Model::with('relation')->get()",
                            'prompt' => "I have an N+1 query issue in Laravel.\n\nSQL: ".$formatSql($query['sql'], $query['bindings'])."\nCaller: ".($query['caller'] ?? 'unknown')."\n\nThe query runs in a loop, once per parent record. Please show me the Eloquent before/after fix using eager loading. Be concise, 5 lines max.",
                        ];
                    } elseif ($isSlow) {
                        $aiIssues[] = [
                            'type'   => 'slow',
                            'class'  => 'slow',
                            'title'  => 'Slow Query ('.number_format($query['time_ms'], 1).'ms)',
                            'desc'   => 'Exceeds '.$slowQueryMs.'ms threshold. Add an index on the columns used in WHERE, JOIN, or ORDER BY clauses.',
                            'fix'    => '→ Run: EXPLAIN SELECT to inspect the query plan',
                            'prompt' => "I have a slow SQL query in Laravel taking ".number_format($query['time_ms'], 1)."ms.\n\nSQL: ".$formatSql($query['sql'], $query['bindings'])."\nCaller: ".($query['caller'] ?? 'unknown')."\n\nSuggest the best index to add and any Eloquent refactoring. Be concise.",
                        ];
                    }
                    if ($isSelectStar) {
                        $aiIssues[] = [
                            'type'   => 'select_star',
                            'class'  => 'star',
                            'title'  => 'SELECT * — Avoid fetching all columns',
                            'desc'   => 'Wastes memory and prevents index-only scans. Specify only the columns you need.',
                            'fix'    => "→ Use: ->select('id', 'name', '...')",
                            'prompt' => "I have a SELECT * query in Laravel.\n\nSQL: ".$formatSql($query['sql'], $query['bindings'])."\nCaller: ".($query['caller'] ?? 'unknown')."\n\nSuggest which columns to select instead and show the Eloquent fix. Be concise.",
                        ];
                    } elseif ($isDup && !$isN1) {
                        $aiIssues[] = [
                            'type'   => 'duplicate',
                            'class'  => 'dup',
                            'title'  => 'Duplicate Query (×'.($sqlCounts[$qNorm] ?? '?').')',
                            'desc'   => 'The same query runs '.($sqlCounts[$qNorm] ?? '?').' times in one request. Cache the result or restructure.',
                            'fix'    => '→ Use: Cache::remember() or restructure with a single query',
                            'prompt' => "This SQL query runs ".($sqlCounts[$qNorm] ?? 'multiple')." times in a single Laravel request.\n\nSQL: ".$formatSql($query['sql'], $query['bindings'])."\nCaller: ".($query['caller'] ?? 'unknown')."\n\nSuggest how to deduplicate it — caching, query restructuring, or Eloquent changes. Be concise.",
                        ];
                    }
                  @endphp
                  @foreach($aiIssues as $aiIssue)
                    @php $aiRowId = '__dd_ai_'.$i.'_'.$aiIssue['type']; @endphp
                    <div class="q-explain q-explain-{{ $aiIssue['class'] }}">
                      <span class="q-explain-title">{{ $aiIssue['title'] }}</span>
                      <span class="q-explain-desc">{{ $aiIssue['desc'] }}</span>
                      <br><code class="q-fix">{{ $aiIssue['fix'] }}</code>
                      <div class="q-ai-row">
                        @if($hasAiSdk)
                          <button class="q-ai-btn ai"
                            data-result="{{ $aiRowId }}"
                            data-sql="{{ e($formatSql($query['sql'], $query['bindings'])) }}"
                            data-type="{{ $aiIssue['type'] }}"
                            data-caller="{{ e($query['caller'] ?? '') }}"
                            data-time="{{ $query['time_ms'] }}"
                            onclick="__dd.aiSuggestFromEl(this)">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                            Fix with AI
                          </button>
                        @endif
                        <button class="q-ai-btn copy" data-prompt="{{ e($aiIssue['prompt']) }}" onclick="__dd.copyPromptFromEl(this)">
                          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                          Copy Prompt
                        </button>
                        <div id="{{ $aiRowId }}" style="display:none;width:100%;"></div>
                      </div>
                    </div>
                  @endforeach
                </td>
                <td class="ddtime">{{ number_format($query['time_ms'], 2) }}<span style="color:#6272a4;font-size:9px;">ms</span></td>
                <td class="ddcaller">{{ $query['caller'] ?? '—' }}</td>
                <td style="padding:5px 6px;">
                  <button title="Copy SQL" onclick="navigator.clipboard&&navigator.clipboard.writeText({{ json_encode($formatSql($query['sql'], $query['bindings'])) }}).then(function(){var b=this;b.style.color='#50fa7b';setTimeout(function(){b.style.color='';},600);}.bind(event.currentTarget))" style="background:none;border:none;cursor:pointer;color:#44475a;padding:2px 4px;border-radius:3px;font-family:inherit;font-size:10px;transition:color .15s;" onmouseover="this.style.color='#6272a4'" onmouseout="this.style.color='#44475a'">
                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    {{-- Views panel --}}
    <div class="ddp" id="__ddp_views__">
      @if($isInertia)
        {{-- Inertia Vue --}}
        <div style="padding:4px 0 12px;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
            <span class="ddbadge" style="background:rgba(80,250,123,.12);color:#50fa7b;font-size:11px;padding:2px 8px;">Vue</span>
            <span class="ddbadge" style="background:rgba(139,233,253,.08);color:#8be9fd;font-size:11px;padding:2px 8px;">Inertia{{ $inertiaVersion ? ' · '.substr($inertiaVersion, 0, 8) : '' }}</span>
            @if($inertiaUrl)
              <span style="color:#6272a4;font-size:10px;">{{ $inertiaUrl }}</span>
            @endif
          </div>
          <div style="font-size:20px;font-weight:700;color:#bd93f9;letter-spacing:-.01em;margin-bottom:16px;">
            {!! str_replace('/', ' <span style="color:#44475a;font-weight:400;">/</span> ', e($inertiaComponent)) !!}
          </div>
          @if(!empty($inertiaProps))
            <div class="ddp-title">Props ({{ count($inertiaProps) }})</div>
            <table>
              <thead><tr>
                <th>Key</th>
                <th>Type</th>
                <th>Preview</th>
              </tr></thead>
              <tbody>
                @foreach($inertiaProps as $key => $val)
                  @php
                    $isList = is_array($val) && array_is_list($val ?? []);
                    $type   = match(true) {
                        is_null($val)                => 'null',
                        is_bool($val)                => 'boolean',
                        is_int($val)                 => 'integer',
                        is_float($val)               => 'float',
                        is_string($val)              => 'string('.mb_strlen($val).')',
                        is_array($val) && $isList    => 'array['.count($val).']',
                        is_array($val)               => 'object{'.count($val).'}',
                        default                      => gettype($val),
                    };
                    $isComplex = is_array($val);
                    $inlinePreview = match(true) {
                        is_null($val)                => '<span style="color:#6272a4;">null</span>',
                        is_bool($val)                => '<span style="color:#bd93f9;">'.($val ? 'true' : 'false').'</span>',
                        is_int($val) || is_float($val) => '<span style="color:#f1fa8c;">'.$val.'</span>',
                        is_string($val)              => '<span style="color:#f1fa8c;">"'.e(mb_substr($val, 0, 80)).(mb_strlen($val) > 80 ? '…' : '').'"</span>',
                        $isComplex && empty($val)    => '<span style="color:#6272a4;">'.($isList ? '[]' : '{}').'</span>',
                        default                      => '',
                    };
                    $jsonVal = $isComplex ? json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
                  @endphp
                  <tr>
                    <td style="color:#f8f8f2;font-weight:600;vertical-align:top;padding-top:7px;">{{ $key }}</td>
                    <td style="color:#8be9fd;font-size:10px;white-space:nowrap;vertical-align:top;padding-top:7px;">{{ $type }}</td>
                    <td style="font-size:10px;vertical-align:top;">
                      @if($isComplex && !empty($val))
                        <details>
                          <summary>{{ $isList ? count($val).' items' : count($val).' keys' }}</summary>
                          <code class="dd-json">{{ $jsonVal }}</code>
                        </details>
                      @else
                        {!! $inlinePreview !!}
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <div class="dd-empty">No props passed.</div>
          @endif
        </div>
      @elseif(!empty($views))
        {{-- Blade --}}
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
          <span class="ddbadge" style="background:rgba(255,184,108,.12);color:#ffb86c;font-size:11px;padding:2px 8px;">Blade</span>
          <span style="color:#6272a4;font-size:11px;">{{ $viewCount }} {{ Str::plural('view', $viewCount) }} rendered</span>
        </div>
        @foreach($views as $i => $view)
          @php
            $vName  = is_array($view) ? ($view['name'] ?? '—') : $view;
            $vPath  = is_array($view) ? ($view['path'] ?? null) : null;
            $vKeys  = is_array($view) ? ($view['data_keys'] ?? []) : [];
            $vId    = 'dd_v_'.$i;
          @endphp
          <div style="border-bottom:1px solid rgba(68,71,90,.3);cursor:pointer;" onclick="(function(){var d=document.getElementById('{{ $vId }}');if(d){d.style.display=d.style.display==='none'?'block':'none';}})()">
            <div style="display:flex;align-items:center;gap:10px;padding:7px 16px;">
              <span style="color:#6272a4;font-size:10px;width:20px;text-align:right;flex-shrink:0;">{{ $i + 1 }}</span>
              <span style="flex:1;font-size:11px;color:#ffb86c;">{{ $vName }}</span>
              @if(!empty($vKeys))
                <span style="font-size:10px;color:#6272a4;">{{ count($vKeys) }} var{{ count($vKeys) !== 1 ? 's' : '' }}</span>
              @endif
              <svg width="9" height="9" fill="none" stroke="#44475a" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
          </div>
          <div id="{{ $vId }}" style="display:none;background:#1e1f2b;border-left:2px solid rgba(255,184,108,.3);padding:8px 16px 8px 24px;font-size:10.5px;">
            @if($vPath)
              <div style="margin-bottom:6px;"><span style="color:#6272a4;">Path:</span> <span style="color:#f8f8f2;font-size:10px;">{{ $vPath }}</span></div>
            @endif
            @if(!empty($vKeys))
              <div><span style="color:#6272a4;margin-right:6px;">Variables:</span>
                @foreach($vKeys as $vk)
                  <span style="display:inline-block;background:rgba(255,184,108,.12);color:#ffb86c;padding:1px 5px;border-radius:3px;font-size:10px;margin:1px 2px;">${{ $vk }}</span>
                @endforeach
              </div>
            @else
              <div style="color:#44475a;">No data passed to this view.</div>
            @endif
          </div>
        @endforeach
      @else
        <div class="dd-empty">No views rendered.</div>
      @endif
    </div>

    {{-- Events panel --}}
    <div class="ddp" id="__ddp_events__">
      @if(empty($events))
        <div class="dd-empty">No events recorded.</div>
      @else
        @php
          $eventCounts = [];
          foreach ($events as $ev) {
              $name = is_array($ev) ? ($ev['event'] ?? $ev['name'] ?? '?') : $ev;
              $eventCounts[$name] = ($eventCounts[$name] ?? 0) + 1;
          }
          $evGroups = [];
          foreach ($events as $ev) {
              $name = is_array($ev) ? ($ev['event'] ?? $ev['name'] ?? '?') : $ev;
              $ns   = str_contains($name, '\\') ? implode('\\', array_slice(explode('\\', $name), 0, -1)) : 'Global';
              $short = str_contains($name, '\\') ? class_basename($name) : $name;
              $evGroups[$ns][] = ['name' => $name, 'short' => $short, 'payload' => is_array($ev) ? ($ev['payload_summary'] ?? null) : null];
          }
          // De-duplicate within each group for display (show count badge)
          foreach ($evGroups as $ns => $items) {
              $seen = [];
              $deduped = [];
              foreach ($items as $item) {
                  if (!isset($seen[$item['name']])) {
                      $seen[$item['name']] = 0;
                      $item['count'] = $eventCounts[$item['name']];
                      $deduped[] = $item;
                      $seen[$item['name']] = 1;
                  }
              }
              $evGroups[$ns] = $deduped;
          }
        @endphp
        <div style="padding:8px 16px 0;color:#6272a4;font-size:10px;">{{ count($events) }} event{{ count($events) !== 1 ? 's' : '' }} fired across {{ count($evGroups) }} namespace{{ count($evGroups) !== 1 ? 's' : '' }}</div>
        @foreach($evGroups as $ns => $items)
          <div style="border-bottom:1px solid rgba(68,71,90,.4);">
            <div style="padding:5px 16px;background:#21222c;font-size:10px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.07em;">{{ $ns }}</div>
            @foreach($items as $idx => $evItem)
              @php $evId = 'dd_ev_'.md5($ns.$evItem['name'].$idx); @endphp
              <div style="border-bottom:1px solid rgba(68,71,90,.2);cursor:pointer;" onclick="(function(el){var d=document.getElementById('{{ $evId }}');if(d){d.style.display=d.style.display==='none'?'block':'none';}})(this)">
                <div style="display:flex;align-items:center;gap:8px;padding:6px 16px;">
                  <svg width="11" height="11" fill="none" stroke="#6272a4" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                  <span style="flex:1;font-size:11px;color:#f8f8f2;">{{ $evItem['short'] }}</span>
                  @if(($evItem['count'] ?? 1) > 1)
                    <span style="background:rgba(189,147,249,.15);color:#bd93f9;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;">×{{ $evItem['count'] }}</span>
                  @endif
                  <svg width="9" height="9" fill="none" stroke="#44475a" stroke-width="2" viewBox="0 0 24 24" class="dd-ev-chevron-{{ $evId }}" style="flex-shrink:0;transition:transform .15s;"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </div>
              </div>
              <div id="{{ $evId }}" style="display:none;background:#1e1f2b;border-left:2px solid rgba(189,147,249,.3);padding:8px 16px 8px 24px;font-size:10.5px;">
                <div style="margin-bottom:4px;"><span style="color:#6272a4;">Full name:</span> <span style="color:#bd93f9;font-family:monospace;">{{ $evItem['name'] }}</span></div>
                @if(!empty($evItem['payload']))
                  <div><span style="color:#6272a4;">Payload:</span> <span style="color:#f8f8f2;">{{ $evItem['payload'] }}</span></div>
                @else
                  <div style="color:#44475a;">No payload captured.</div>
                @endif
              </div>
            @endforeach
          </div>
        @endforeach
      @endif
    </div>

    {{-- Cache panel --}}
    <div class="ddp" id="__ddp_cache__">
      @if(empty($cache))
        <div class="dd-empty">No cache operations recorded.</div>
      @else
        {{-- Hit rate bar + counters --}}
        <div style="padding:10px 16px 0;">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
            <div style="display:flex;align-items:center;gap:6px;flex:1;">
              <span style="font-size:10px;color:#6272a4;width:52px;flex-shrink:0;">Hit rate</span>
              <div style="flex:1;background:#44475a;border-radius:3px;height:6px;overflow:hidden;">
                <div style="background:{{ $hitRate >= 80 ? '#50fa7b' : ($hitRate >= 50 ? '#ffb86c' : '#ff5555') }};width:{{ $hitRate }}%;height:100%;border-radius:3px;transition:width .3s;"></div>
              </div>
              <span style="font-size:11px;font-weight:700;color:{{ $hitRate >= 80 ? '#50fa7b' : ($hitRate >= 50 ? '#ffb86c' : '#ff5555') }};min-width:32px;text-align:right;">{{ $hitRate }}%</span>
            </div>
            <div style="display:flex;gap:10px;flex-shrink:0;">
              <span class="ddbadge dd-hit">{{ $cacheHits }}H</span>
              <span class="ddbadge dd-miss">{{ $cacheMisses }}M</span>
              @if($cacheWrites > 0)<span class="ddbadge dd-write">{{ $cacheWrites }}W</span>@endif
            </div>
          </div>
        </div>
        {{-- View toggle: Grouped / Timeline --}}
        <div style="display:flex;gap:0;padding:0 16px 8px;">
          <button onclick="document.getElementById('dd_cache_grouped').style.display='block';document.getElementById('dd_cache_timeline').style.display='none';this.style.background='rgba(139,233,253,.12)';this.style.color='#8be9fd';document.getElementById('dd_cache_tl_btn').style.background='none';document.getElementById('dd_cache_tl_btn').style.color='#6272a4';"
            style="font-size:10px;font-weight:700;padding:2px 10px;border:1px solid #44475a;border-right:none;border-radius:4px 0 0 4px;background:rgba(139,233,253,.12);color:#8be9fd;cursor:pointer;">By Key</button>
          <button id="dd_cache_tl_btn" onclick="document.getElementById('dd_cache_grouped').style.display='none';document.getElementById('dd_cache_timeline').style.display='block';this.style.background='rgba(139,233,253,.12)';this.style.color='#8be9fd';document.getElementById('dd_cache_tl_btn').previousElementSibling.style.background='none';document.getElementById('dd_cache_tl_btn').previousElementSibling.style.color='#6272a4';"
            style="font-size:10px;font-weight:700;padding:2px 10px;border:1px solid #44475a;border-radius:0 4px 4px 0;background:none;color:#6272a4;cursor:pointer;">Timeline</button>
        </div>

        {{-- Grouped by key --}}
        <div id="dd_cache_grouped">
          <table>
            <thead><tr>
              <th>Key</th>
              <th style="text-align:center;width:40px;">H</th>
              <th style="text-align:center;width:40px;">M</th>
              <th style="text-align:center;width:40px;">W</th>
              <th style="text-align:center;width:60px;">Status</th>
            </tr></thead>
            <tbody>
              @foreach($cacheByKey as $cacheKey => $counts)
                @php
                  $allHit  = $counts['hit'] > 0 && $counts['miss'] === 0;
                  $anyMiss = $counts['miss'] > 0;
                  $mixed   = $counts['hit'] > 0 && $counts['miss'] > 0;
                  $rowBg   = $allHit ? 'rgba(80,250,123,.04)' : ($anyMiss ? 'rgba(255,85,85,.04)' : 'transparent');
                @endphp
                <tr style="background:{{ $rowBg }}" title="{{ $cacheKey }}" onclick="navigator.clipboard&&navigator.clipboard.writeText('{{ addslashes($cacheKey) }}').then(function(){var el=event.currentTarget;el.style.opacity='.6';setTimeout(function(){el.style.opacity='1';},400);})" style="cursor:pointer;">
                  <td style="color:#f8f8f2;font-size:11px;word-break:break-all;">{{ $cacheKey }}</td>
                  <td style="text-align:center;color:{{ $counts['hit'] > 0 ? '#50fa7b' : '#44475a' }};">{{ $counts['hit'] ?: '—' }}</td>
                  <td style="text-align:center;color:{{ $counts['miss'] > 0 ? '#ff5555' : '#44475a' }};">{{ $counts['miss'] ?: '—' }}</td>
                  <td style="text-align:center;color:#8be9fd;">{{ $counts['write'] ?: '—' }}</td>
                  <td style="text-align:center;">
                    @if($mixed)
                      <span style="color:#ffb86c;font-size:10px;">mixed</span>
                    @elseif($allHit)
                      <span style="color:#50fa7b;font-size:10px;">✓ hit</span>
                    @elseif($anyMiss && $counts['hit'] === 0)
                      <span style="color:#ff5555;font-size:10px;">✗ miss</span>
                    @else
                      <span style="color:#6272a4;font-size:10px;">write</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Timeline (sequence) --}}
        <div id="dd_cache_timeline" style="display:none;padding:0 16px 8px;">
          <div style="display:flex;gap:4px;flex-wrap:wrap;">
            @foreach($cache as $i => $op)
              @php
                $opColor = $op['type'] === 'hit' ? '#50fa7b' : ($op['type'] === 'miss' ? '#ff5555' : '#8be9fd');
                $opBg    = $op['type'] === 'hit' ? 'rgba(80,250,123,.12)' : ($op['type'] === 'miss' ? 'rgba(255,85,85,.12)' : 'rgba(139,233,253,.1)');
              @endphp
              <div title="{{ $op['key'] }}" style="cursor:default;display:inline-flex;align-items:center;gap:3px;background:{{ $opBg }};border:1px solid {{ $opColor }}33;border-radius:4px;padding:2px 6px;font-size:10px;max-width:220px;overflow:hidden;">
                <span style="color:{{ $opColor }};font-weight:700;flex-shrink:0;">{{ strtoupper(substr($op['type'], 0, 1)) }}</span>
                <span style="color:#6272a4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $op['key'] }}</span>
              </div>
            @endforeach
          </div>
          <div style="margin-top:8px;font-size:10px;color:#44475a;">Click any key tile in "By Key" view to copy key name.</div>
        </div>
      @endif
    </div>

    {{-- Models panel --}}
    <div class="ddp" id="__ddp_models__">
      @if(empty($models))
        <div class="dd-empty">No model operations recorded.</div>
      @else
        @php
          $modelTotals = array_map(fn ($m) => $m['retrieved'] + $m['created'] + $m['updated'] + $m['deleted'], $models);
          $modelMax    = max($modelTotals ?: [1]);
          usort($models, fn ($a, $b) => ($b['retrieved'] + $b['created'] + $b['updated'] + $b['deleted']) <=> ($a['retrieved'] + $a['created'] + $a['updated'] + $a['deleted']));
        @endphp
        <div style="padding:8px 16px 0;color:#6272a4;font-size:10px;">{{ count($models) }} model{{ count($models) !== 1 ? 's' : '' }} · {{ $modelTotal }} total operations · click any row to see full class name</div>
        <table>
          <thead><tr>
            <th>Model</th>
            <th style="width:120px;">Activity</th>
            <th style="text-align:right;width:32px;" title="Retrieved"><span style="color:#bd93f9;">R</span></th>
            <th style="text-align:right;width:32px;" title="Created"><span style="color:#50fa7b;">C</span></th>
            <th style="text-align:right;width:32px;" title="Updated"><span style="color:#ffb86c;">U</span></th>
            <th style="text-align:right;width:32px;" title="Deleted"><span style="color:#ff5555;">D</span></th>
          </tr></thead>
          <tbody>
            @foreach($models as $midx => $model)
              @php
                $mTotal  = $model['retrieved'] + $model['created'] + $model['updated'] + $model['deleted'];
                $mPct    = $modelMax > 0 ? ($mTotal / $modelMax) * 100 : 0;
                $mShort  = $shortClass($model['class']);
                $mId     = 'dd_m_'.$midx;
              @endphp
              <tr style="cursor:pointer;" onclick="(function(){var d=document.getElementById('{{ $mId }}');if(d){d.style.display=d.style.display==='none'?'table-row':'none';}})()">
                <td style="color:#f8f8f2;">{{ $mShort }}</td>
                <td style="padding-top:7px;padding-bottom:7px;">
                  <div style="background:#44475a;border-radius:2px;height:5px;overflow:hidden;">
                    <div style="height:100%;border-radius:2px;width:{{ $mPct }}%;background:{{ $model['retrieved'] > 0 ? '#bd93f9' : ($model['created'] > 0 ? '#50fa7b' : ($model['updated'] > 0 ? '#ffb86c' : '#ff5555')) }};"></div>
                  </div>
                </td>
                <td class="dd-retrieved" style="text-align:right;">{{ $model['retrieved'] ?: '—' }}</td>
                <td class="dd-created"   style="text-align:right;">{{ $model['created']   ?: '—' }}</td>
                <td class="dd-updated"   style="text-align:right;">{{ $model['updated']   ?: '—' }}</td>
                <td class="dd-deleted"   style="text-align:right;">{{ $model['deleted']   ?: '—' }}</td>
              </tr>
              <tr id="{{ $mId }}" style="display:none;">
                <td colspan="6" style="background:#1e1f2b;padding:6px 12px 8px;border-left:2px solid rgba(189,147,249,.3);">
                  <div style="font-size:10px;color:#6272a4;margin-bottom:3px;">Full class:</div>
                  <div style="font-size:10.5px;color:#bd93f9;font-family:monospace;word-break:break-all;">{{ $model['class'] }}</div>
                  <div style="display:flex;gap:8px;margin-top:6px;">
                    @if($model['retrieved'] > 0)<span style="font-size:10px;background:rgba(189,147,249,.12);color:#bd93f9;padding:1px 6px;border-radius:3px;">{{ $model['retrieved'] }} retrieved</span>@endif
                    @if($model['created']   > 0)<span style="font-size:10px;background:rgba(80,250,123,.12);color:#50fa7b;padding:1px 6px;border-radius:3px;">{{ $model['created'] }} created</span>@endif
                    @if($model['updated']   > 0)<span style="font-size:10px;background:rgba(255,184,108,.12);color:#ffb86c;padding:1px 6px;border-radius:3px;">{{ $model['updated'] }} updated</span>@endif
                    @if($model['deleted']   > 0)<span style="font-size:10px;background:rgba(255,85,85,.12);color:#ff5555;padding:1px 6px;border-radius:3px;">{{ $model['deleted'] }} deleted</span>@endif
                  </div>
                  @if($model['retrieved'] > 0)
                    <div style="margin-top:5px;font-size:10px;color:#44475a;">Tip: check Queries tab for SELECT statements loading this model.</div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    {{-- Route panel --}}
    <div class="ddp" id="__ddp_route__">
      @php
        $route     = $profile['route'] ?? [];
        $lifecycle = $profile['lifecycle'] ?? [];
        $routeAction = $route['action'] ?? null;
        [$routeController, $routeMethod] = $routeAction && str_contains($routeAction, '@')
            ? explode('@', $routeAction, 2)
            : [$routeAction, null];
        $routeControllerShort = $routeController ? basename(str_replace('\\', '/', $routeController)) : null;
      @endphp
      <div class="dd-route-grid">

        {{-- Request --}}
        <div class="dd-route-card">
          <div class="ddk">Request</div>
          <div class="ddv" style="margin-bottom:4px;">
            <span style="color:{{ $methodColor }};font-weight:700;">{{ $method }}</span>
            <span style="color:#f8f8f2;margin-left:6px;">{{ $url }}</span>
          </div>
          <div style="display:flex;gap:8px;margin-top:4px;">
            <span class="ddbadge" style="background:rgba(80,250,123,.1);color:{{ $statusColor }};">{{ $status }}</span>
            @if(!empty($route['name']))
              <span class="ddbadge" style="background:rgba(139,233,253,.08);color:#8be9fd;">{{ $route['name'] }}</span>
            @endif
          </div>
          @if(!empty($route['parameters']))
            <div style="margin-top:8px;">
              <div class="ddk" style="margin-bottom:3px;">Parameters</div>
              @foreach($route['parameters'] as $k => $v)
                <div style="font-size:10px;"><span style="color:#6272a4;">{{ $k }}:</span> <span style="color:#f1fa8c;">{{ is_scalar($v) ? $v : json_encode($v) }}</span></div>
              @endforeach
            </div>
          @endif
        </div>

        {{-- Controller --}}
        <div class="dd-route-card">
          <div class="ddk">Controller & Action</div>
          @if($routeControllerShort)
            <div class="ddv hi" style="margin-bottom:2px;">{{ $routeControllerShort }}</div>
            @if($routeMethod)
              <div style="color:#6272a4;font-size:10px;margin-bottom:4px;">{{ $routeController }}</div>
              <div style="display:flex;align-items:center;gap:6px;margin-top:6px;">
                <span style="font-size:10px;color:#6272a4;">method</span>
                <span class="ddv cyan">{{ $routeMethod }}</span>
              </div>
            @endif
          @elseif($routeAction)
            <div class="ddv hi">{{ $routeAction }}</div>
          @else
            <div style="color:#6272a4;font-size:11px;">No controller bound</div>
          @endif
        </div>

        {{-- View / Inertia --}}
        <div class="dd-route-card">
          @if($isInertia)
            <div class="ddk" style="display:flex;align-items:center;gap:6px;">
              <span>Vue Component</span>
              <span class="ddbadge" style="background:rgba(80,250,123,.1);color:#50fa7b;text-transform:none;">Inertia</span>
            </div>
            <div class="ddv hi" style="font-size:13px;margin-top:4px;">{{ $inertiaComponent }}</div>
            @if(!empty($inertiaProps))
              <div style="margin-top:8px;">
                <div class="ddk" style="margin-bottom:3px;">Props ({{ count($inertiaProps) }})</div>
                @foreach(array_keys($inertiaProps) as $prop)
                  <span class="dd-mw-pill">{{ $prop }}</span>
                @endforeach
              </div>
            @endif
          @elseif(!empty($views))
            <div class="ddk" style="display:flex;align-items:center;gap:6px;">
              <span>Blade Views</span>
              <span class="ddbadge" style="background:rgba(255,184,108,.1);color:#ffb86c;text-transform:none;">{{ $viewCount }}</span>
            </div>
            @foreach(array_slice($views, 0, 6) as $v)
              <div class="ddv" style="color:#ffb86c;font-size:11px;margin-top:4px;">{{ is_array($v) ? ($v['name'] ?? '') : $v }}</div>
            @endforeach
            @if($viewCount > 6)
              <div style="color:#6272a4;font-size:10px;margin-top:3px;">+{{ $viewCount - 6 }} more</div>
            @endif
          @else
            <div class="ddk">View</div>
            <div style="color:#6272a4;font-size:11px;margin-top:4px;">No view rendered</div>
          @endif
        </div>

        {{-- Middleware --}}
        @if(!empty($route['middleware']))
          <div class="dd-route-card">
            <div class="ddk">Middleware ({{ count($route['middleware']) }})</div>
            <div style="margin-top:2px;">
              @foreach($route['middleware'] as $mw)
                <span class="dd-mw-pill">{{ is_string($mw) ? basename(str_replace('\\', '/', $mw)) : $mw }}</span>
              @endforeach
            </div>
          </div>
        @endif

      </div>
    </div>

    {{-- Lifecycle panel --}}
    <div class="ddp" id="__ddp_lifecycle__">
      @php
        $lc       = $profile['lifecycle'] ?? [];
        $lcPhases = $lc['phases'] ?? [];
        $perf     = $profile['performance'];
        $overhead = $perf['profiling_overhead_ms'] ?? 0;
        $appMs    = max(0.01, $perf['duration_ms'] - $overhead);
        $maxPhase = !empty($lcPhases) ? max(array_column($lcPhases, 'duration_ms') ?: [1]) : 1;
        $phaseColors = [
            'bootstrap'      => '#bd93f9',
            'routing'        => '#8be9fd',
            'controller'     => '#50fa7b',
            'view'           => '#ffb86c',
            'middleware_done'=> '#ff79c6',
            'response_ready' => '#f1fa8c',
        ];
      @endphp

      {{-- Summary cards --}}
      <div class="dd-perf-grid">
        <div class="dd-perf-card">
          <div class="dd-pc-val" style="color:{{ $durationColor }}">{{ number_format($perf['duration_ms'], 1) }}<span style="font-size:12px;font-weight:400;color:#6272a4;">ms</span></div>
          <div class="dd-pc-lbl">Total Time</div>
          <div class="dd-pc-sub">App: {{ number_format($appMs, 1) }}ms</div>
        </div>
        <div class="dd-perf-card">
          <div class="dd-pc-val" style="color:{{ $memoryColor }}">{{ $perf['memory_peak_mb'] }}<span style="font-size:12px;font-weight:400;color:#6272a4;">MB</span></div>
          <div class="dd-pc-lbl">Peak Memory</div>
          <div class="dd-pc-sub">Overhead: {{ number_format($overhead, 2) }}ms</div>
        </div>
        <div class="dd-perf-card">
          <div class="dd-pc-val" style="color:{{ $queryColor }}">{{ $perf['query_count'] }}<span style="font-size:12px;font-weight:400;color:#6272a4;">q</span></div>
          <div class="dd-pc-lbl">Queries</div>
          <div class="dd-pc-sub">{{ number_format($perf['query_time_ms'], 1) }}ms total</div>
        </div>
        <div class="dd-perf-card">
          @php $nonQueryMs = max(0, $appMs - $perf['query_time_ms']); @endphp
          <div class="dd-pc-val" style="color:#f8f8f2">{{ number_format($nonQueryMs, 1) }}<span style="font-size:12px;font-weight:400;color:#6272a4;">ms</span></div>
          <div class="dd-pc-lbl">PHP Time</div>
          <div class="dd-pc-sub">Ex. query wait</div>
        </div>
      </div>

      {{-- Waterfall --}}
      @if(!empty($lcPhases))
        <div class="dd-lc-section">
          <div class="ddp-title">Phase Waterfall</div>
          <div class="dd-lc-waterfall">
            @foreach($lcPhases as $phase)
              @php
                $pName  = $phase['name'] ?? '—';
                $pMs    = $phase['duration_ms'] ?? 0;
                $pStart = $phase['offset_ms'] ?? 0;
                $color  = $phaseColors[$pName] ?? '#6272a4';
                $totalMs = max($perf['duration_ms'], 0.01);
                $barW   = min(100, ($pMs / $totalMs) * 100);
                $barL   = min(95, ($pStart / $totalMs) * 100);
              @endphp
              <div class="dd-lc-row">
                <div class="dd-lc-label">{{ str_replace('_', ' ', $pName) }}</div>
                <div class="dd-lc-track">
                  <div class="dd-lc-fill" style="background:{{ $color }};width:{{ $barW }}%;left:{{ $barL }}%;opacity:.85;"></div>
                </div>
                <div class="dd-lc-val">{{ number_format($pMs, 1) }}ms</div>
              </div>
            @endforeach

            {{-- Profiling overhead row --}}
            @if($overhead > 0.5)
              <div class="dd-lc-row" style="margin-top:4px;padding-top:4px;border-top:1px solid #44475a;">
                <div class="dd-lc-label" style="color:#44475a;">profiling overhead</div>
                <div class="dd-lc-track">
                  <div class="dd-lc-fill" style="background:#44475a;width:{{ min(100, ($overhead / max($perf['duration_ms'], 0.01)) * 100) }}%;"></div>
                </div>
                <div class="dd-lc-val" style="color:#44475a;">{{ number_format($overhead, 1) }}ms</div>
              </div>
            @endif
          </div>
        </div>
      @endif

      {{-- Middleware timing breakdown --}}
      @if(!empty($profile['middleware_timing']))
        <div class="dd-lc-section">
          <div class="ddp-title">Middleware Timing</div>
          @php
            $mwTotal = max(0.01, $profile['middleware_pipeline_ms'] ?? 1);
            $mwMaxMs = max(array_column($profile['middleware_timing'], 'duration_ms') ?: [1]);
          @endphp
          @foreach(collect($profile['middleware_timing'])->sortByDesc('duration_ms')->take(20) as $mw)
            <div class="dd-lc-row">
              <div class="dd-lc-label">{{ basename(str_replace('\\', '/', $mw['name'] ?? '—')) }}</div>
              <div class="dd-lc-track">
                <div class="dd-lc-fill" style="background:#ff79c6;width:{{ min(100, (($mw['duration_ms'] ?? 0) / $mwMaxMs) * 100) }}%;opacity:.7;"></div>
              </div>
              <div class="dd-lc-val">{{ number_format($mw['duration_ms'] ?? 0, 2) }}ms</div>
            </div>
          @endforeach
        </div>
      @endif

    </div>

    {{-- AJAX / Inertia history panel (populated by JS) --}}
    <div class="ddp" id="__ddp_ajax__" style="padding:0;">
      <div id="__dd_ajax_empty__" class="dd-empty" style="padding:20px;">No AJAX / Inertia navigation recorded yet.</div>
      <div id="__dd_ajax_split__" style="display:none;height:340px;display:none;flex-direction:row;">
        <div id="__dd_ajax_list__" style="width:320px;flex-shrink:0;overflow-y:auto;border-right:1px solid #44475a;height:100%;"></div>
        <div id="__dd_ajax_detail__" style="flex:1;overflow-y:auto;padding:12px 16px;height:100%;">
          <div style="color:#6272a4;font-size:11px;margin-top:40px;text-align:center;">Select a request to inspect</div>
        </div>
      </div>
    </div>

    {{-- HTTP Client panel --}}
    <div class="ddp" id="__ddp_http__">
      @if(empty($httpCalls))
        <div class="dd-empty">No outbound HTTP requests recorded.</div>
      @else
        @php
          $httpTotal = count($httpCalls);
          $httpFailed = count(array_filter($httpCalls, fn ($r) => ($r['status'] ?? 200) >= 400));
          $httpSlow   = count(array_filter($httpCalls, fn ($r) => $r['duration_ms'] > 1000));
          $httpTotalMs = array_sum(array_column($httpCalls, 'duration_ms'));
        @endphp
        {{-- Summary strip --}}
        <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid #44475a;background:#21222c;">
          <span style="font-size:11px;font-weight:700;color:#f8f8f2;flex:1;">{{ $httpTotal }} outbound request{{ $httpTotal !== 1 ? 's' : '' }} · {{ round($httpTotalMs) }}ms total</span>
          @if($httpFailed > 0)
            <span style="background:rgba(255,85,85,.12);color:#ff5555;border:1px solid rgba(255,85,85,.3);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $httpFailed }} failed</span>
          @endif
          @if($httpSlow > 0)
            <span style="background:rgba(255,184,108,.12);color:#ffb86c;border:1px solid rgba(255,184,108,.3);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $httpSlow }} slow</span>
          @endif
        </div>
        {{-- Request list --}}
        @foreach($httpCalls as $hi => $hReq)
          @php
            $hStatus  = $hReq['status'] ?? 0;
            $hOk      = $hStatus >= 200 && $hStatus < 300;
            $hFail    = $hStatus >= 400;
            $hSlow    = $hReq['duration_ms'] > 1000;
            $hSColor  = $hFail ? '#ff5555' : ($hStatus >= 300 ? '#ffb86c' : '#50fa7b');
            $hDColor  = $hSlow ? '#ff5555' : ($hReq['duration_ms'] > 500 ? '#ffb86c' : '#50fa7b');
            $hHost    = parse_url($hReq['url'], PHP_URL_HOST) ?? $hReq['url'];
            $hPath    = parse_url($hReq['url'], PHP_URL_PATH) ?? '/';
            $hId      = 'dd_http_'.$hi;
            $mColors  = ['GET' => '#50fa7b','POST' => '#8be9fd','PUT' => '#ffb86c','PATCH' => '#ffb86c','DELETE' => '#ff5555'];
            $hMColor  = $mColors[$hReq['method']] ?? '#bd93f9';
            $hSize    = $hReq['response_size'] ?? 0;
          @endphp
          <div style="border-bottom:1px solid rgba(68,71,90,.3);{{ $hFail ? 'background:rgba(255,85,85,.04);border-left:2px solid rgba(255,85,85,.35);' : '' }}">
            <div style="display:flex;align-items:center;gap:8px;padding:8px 16px;cursor:pointer;" onclick="(function(){var d=document.getElementById('{{ $hId }}');if(d){d.style.display=d.style.display==='none'?'block':'none';}})()">
              <span style="font-size:10px;font-weight:800;color:{{ $hMColor }};width:40px;flex-shrink:0;">{{ $hReq['method'] }}</span>
              <div style="flex:1;min-width:0;">
                <div style="font-size:11px;color:#f8f8f2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $hHost }}<span style="color:#6272a4;">{{ $hPath }}</span></div>
              </div>
              <span style="font-size:10px;font-weight:700;color:{{ $hSColor }};flex-shrink:0;">{{ $hStatus }}</span>
              <span style="font-size:10px;font-weight:700;color:{{ $hDColor }};white-space:nowrap;flex-shrink:0;">{{ round($hReq['duration_ms']) }}ms</span>
              @if($hSize > 0)
                <span style="font-size:9px;color:#6272a4;flex-shrink:0;">{{ $hSize > 1024 ? round($hSize/1024,1).'KB' : $hSize.'B' }}</span>
              @endif
              <svg width="9" height="9" fill="none" stroke="#44475a" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div id="{{ $hId }}" style="display:none;background:#1e1f2b;padding:0;">
              {{-- Full URL --}}
              <div style="padding:6px 16px;border-bottom:1px solid rgba(68,71,90,.3);font-size:10px;word-break:break-all;color:#8be9fd;">{{ $hReq['url'] }}</div>
              {{-- Tabs: Request / Response --}}
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
                {{-- Request side --}}
                <div style="padding:8px 16px;border-right:1px solid rgba(68,71,90,.3);">
                  <div style="font-size:10px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;">Request</div>
                  @if(!empty($hReq['request_headers']))
                    <div style="font-size:10px;font-weight:600;color:#6272a4;margin-bottom:3px;">Headers</div>
                    @foreach($hReq['request_headers'] as $hk => $hv)
                      <div style="font-size:10px;margin-bottom:1px;"><span style="color:#6272a4;">{{ $hk }}:</span> <span style="color:#f8f8f2;">{{ is_array($hv) ? implode(', ', $hv) : $hv }}</span></div>
                    @endforeach
                  @endif
                  @if(!empty($hReq['request_body']))
                    <div style="font-size:10px;font-weight:600;color:#6272a4;margin-top:6px;margin-bottom:3px;">Body</div>
                    <pre style="margin:0;font-size:10px;color:#f8f8f2;background:#282a36;padding:5px 8px;border-radius:4px;overflow-x:auto;max-height:100px;white-space:pre-wrap;word-break:break-all;">{{ mb_substr($hReq['request_body'], 0, 500) }}{{ mb_strlen($hReq['request_body']) > 500 ? '…' : '' }}</pre>
                  @else
                    <div style="font-size:10px;color:#44475a;margin-top:4px;">No body</div>
                  @endif
                </div>
                {{-- Response side --}}
                <div style="padding:8px 16px;">
                  <div style="font-size:10px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;">Response <span style="color:{{ $hSColor }};">{{ $hStatus }}</span></div>
                  @if(!empty($hReq['response_headers']))
                    <div style="font-size:10px;font-weight:600;color:#6272a4;margin-bottom:3px;">Headers</div>
                    @foreach($hReq['response_headers'] as $hk => $hv)
                      <div style="font-size:10px;margin-bottom:1px;"><span style="color:#6272a4;">{{ $hk }}:</span> <span style="color:#f8f8f2;">{{ is_array($hv) ? implode(', ', $hv) : $hv }}</span></div>
                    @endforeach
                  @endif
                  @if(!empty($hReq['response_body']))
                    <div style="font-size:10px;font-weight:600;color:#6272a4;margin-top:6px;margin-bottom:3px;">Body preview</div>
                    <pre style="margin:0;font-size:10px;color:#f8f8f2;background:#282a36;padding:5px 8px;border-radius:4px;overflow-x:auto;max-height:100px;white-space:pre-wrap;word-break:break-all;">{{ mb_substr($hReq['response_body'], 0, 800) }}{{ mb_strlen($hReq['response_body']) > 800 ? '…' : '' }}</pre>
                  @endif
                </div>
              </div>
            </div>
          </div>
        @endforeach
      @endif
    </div>

    {{-- Exception panel --}}
    <div class="ddp" id="__ddp_exception__">
      @if($exception)
        @php
          $excRelFile = str_replace(base_path().'/', '', $exception['file'] ?? '');
          $excTrace   = array_slice($exception['trace'] ?? [], 0, 8);
          $excTraceForAi = array_map(fn($f) => [
              'file'     => str_replace(base_path().'/', '', $f['file'] ?? ''),
              'line'     => $f['line'] ?? null,
              'class'    => $f['class'] ?? null,
              'function' => $f['function'] ?? null,
          ], $excTrace);
        @endphp
        <div style="display:flex;align-items:flex-start;gap:10px;justify-content:space-between;">
          <table style="flex:1;">
            <tbody>
              <tr><td style="color:#6272a4;width:100px;">Class</td><td style="color:#ff5555;">{{ $exception['class'] }}</td></tr>
              <tr><td style="color:#6272a4;">Message</td><td style="color:#f8f8f2;">{{ $exception['message'] }}</td></tr>
              <tr><td style="color:#6272a4;">File</td><td style="color:#f8f8f2;font-size:10px;">{{ $excRelFile }}:{{ $exception['line'] }}</td></tr>
            </tbody>
          </table>
          @if($hasAiSdk)
            <div style="flex-shrink:0;">
              <button class="q-ai-btn ai"
                data-exc-class="{{ e($exception['class']) }}"
                data-exc-message="{{ e($exception['message']) }}"
                data-exc-file="{{ e($excRelFile) }}"
                data-exc-line="{{ $exception['line'] ?? '' }}"
                data-exc-trace="{{ e(json_encode($excTraceForAi)) }}"
                onclick="__dd.investigateExceptionFromEl(this)"
                style="white-space:nowrap;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                Investigate with AI
              </button>
            </div>
          @endif
        </div>
        <div id="__dd_exc_ai_result__" class="q-ai-result" style="display:none;margin-top:8px;"></div>
        @if(!empty($exception['trace']))
          <div style="margin-top:10px;">
            <div class="ddp-title">Stack Trace</div>
            <table>
              <thead><tr><th>File</th><th>Line</th><th>Call</th></tr></thead>
              <tbody>
                @foreach(array_slice($exception['trace'], 0, 15) as $frame)
                  <tr>
                    <td style="color:#6272a4;font-size:10px;max-width:300px;overflow:hidden;text-overflow:ellipsis;">
                      {{ isset($frame['file']) ? str_replace(base_path().'/', '', $frame['file']) : '—' }}
                    </td>
                    <td style="color:#8be9fd;text-align:right;white-space:nowrap;">{{ $frame['line'] ?? '—' }}</td>
                    <td style="color:#f8f8f2;font-size:10px;">
                      {{ isset($frame['class']) ? $frame['class'].($frame['function'] ? '::'.$frame['function'].'()' : '') : ($frame['function'] ?? '—') }}
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      @else
        <div class="dd-empty">No exception recorded.</div>
      @endif
    </div>

    {{-- Request panel --}}
    <div class="ddp" id="__ddp_request__">
      @php
        $reqHeaders  = $profile['request']['headers'] ?? [];
        $reqPayload  = $profile['request']['payload'] ?? [];
        $reqBody     = $profile['request']['body'] ?? '';
        $resHeaders  = $profile['response']['headers'] ?? [];
        $resSize     = $profile['response']['size'] ?? 0;
        $resHeaderKeys = array_map('strtolower', array_keys($resHeaders));
        $authUser    = $profile['request']['auth_user'] ?? null;
        $reqIp       = $profile['request']['ip'] ?? null;
        $userAgent   = null;
        foreach ($reqHeaders as $hk => $hv) {
            if (strtolower($hk) === 'user-agent') {
                $userAgent = is_array($hv) ? ($hv[0] ?? null) : $hv;
                break;
            }
        }
        $secChecks = [
            ['header' => 'x-frame-options',      'label' => 'X-Frame-Options',      'pass' => in_array('x-frame-options', $resHeaderKeys)],
            ['header' => 'content-security-policy','label' => 'CSP',                 'pass' => in_array('content-security-policy', $resHeaderKeys)],
            ['header' => 'x-content-type-options','label' => 'nosniff',              'pass' => in_array('x-content-type-options', $resHeaderKeys)],
            ['header' => 'strict-transport-security','label' => 'HSTS',              'pass' => in_array('strict-transport-security', $resHeaderKeys)],
            ['header' => 'referrer-policy',       'label' => 'Referrer-Policy',      'pass' => in_array('referrer-policy', $resHeaderKeys)],
            ['header' => 'server',                'label' => 'No Server leak',        'pass' => !in_array('server', $resHeaderKeys)],
        ];
      @endphp

      {{-- Quick-info strip --}}
      <div style="display:flex;flex-wrap:wrap;gap:10px;padding:10px 16px;border-bottom:1px solid #44475a;background:#21222c;">
        @if($authUser)
          <div style="display:flex;align-items:center;gap:5px;">
            <svg width="11" height="11" fill="none" stroke="#50fa7b" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
            <span style="font-size:10px;color:#50fa7b;font-weight:600;">{{ is_array($authUser) ? ($authUser['name'] ?? $authUser['email'] ?? json_encode($authUser)) : $authUser }}</span>
          </div>
        @else
          <div style="display:flex;align-items:center;gap:5px;">
            <svg width="11" height="11" fill="none" stroke="#6272a4" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
            <span style="font-size:10px;color:#6272a4;">Guest</span>
          </div>
        @endif
        @if($reqIp)
          <span style="font-size:10px;color:#6272a4;"><span style="color:#44475a;">IP</span> {{ $reqIp }}</span>
        @endif
        @if($userAgent)
          <span style="font-size:10px;color:#6272a4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px;" title="{{ $userAgent }}">{{ mb_substr($userAgent, 0, 60) }}{{ mb_strlen($userAgent) > 60 ? '…' : '' }}</span>
        @endif
        @if($resSize > 0)
          <span style="font-size:10px;color:#6272a4;margin-left:auto;">Response size: <span style="color:#f8f8f2;font-weight:600;">{{ $resSize > 1024 ? round($resSize/1024,1).'KB' : $resSize.'B' }}</span></span>
        @endif
      </div>

      {{-- Security header chips --}}
      <div style="display:flex;flex-wrap:wrap;gap:4px;padding:8px 16px;border-bottom:1px solid rgba(68,71,90,.4);">
        <span style="font-size:10px;color:#6272a4;margin-right:4px;align-self:center;">Security:</span>
        @foreach($secChecks as $sc)
          <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px;
            {{ $sc['pass'] ? 'background:rgba(80,250,123,.1);color:#50fa7b;border:1px solid rgba(80,250,123,.25);' : 'background:rgba(255,85,85,.08);color:#ff5555;border:1px solid rgba(255,85,85,.2);' }}">
            @if($sc['pass'])
              <svg width="9" height="9" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            @else
              <svg width="9" height="9" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            @endif
            {{ $sc['label'] }}
          </span>
        @endforeach
      </div>

      <div class="dd-route-grid">
        <div class="dd-route-card">
          <div class="ddk">Request Headers ({{ count($reqHeaders) }})</div>
          @foreach($reqHeaders as $hk => $hv)
            <div style="font-size:10px;margin-top:3px;"><span style="color:#6272a4;">{{ $hk }}:</span> <span style="color:#f8f8f2;">{{ is_array($hv) ? implode(', ', $hv) : $hv }}</span></div>
          @endforeach
        </div>
        <div class="dd-route-card">
          <div class="ddk">Response Headers ({{ count($resHeaders) }})</div>
          @foreach($resHeaders as $hk => $hv)
            @php $hkLower = strtolower($hk); $secFail = !in_array($hkLower, ['x-frame-options','content-security-policy','x-content-type-options','strict-transport-security','referrer-policy']) ? false : false; $isSensitive = $hkLower === 'server'; @endphp
            <div style="font-size:10px;margin-top:3px;"><span style="color:#6272a4;">{{ $hk }}:</span> <span style="color:{{ $isSensitive ? '#ff5555' : '#f8f8f2' }};">{{ is_array($hv) ? implode(', ', $hv) : $hv }}</span></div>
          @endforeach
        </div>
        @if(!empty($reqPayload))
          <div class="dd-route-card" style="grid-column:1/-1;">
            <div class="ddk">POST Payload</div>
            <code class="dd-json" style="display:block;margin-top:6px;">{{ json_encode($reqPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code>
          </div>
        @endif
      </div>
    </div>


    {{-- AI Suggestions panel --}}
    <div class="ddp" id="__ddp_ai__">
      @php
        $aiItems = [];
        foreach ($queries as $qi => $query) {
            $qNorm = $sqlNorm($query['sql']);
            $isN1q = in_array($qNorm, $n1Sqls);
            $isSlowQ = $query['time_ms'] >= $slowQueryMs;
            $isDupQ  = in_array($qNorm, $dupSqls);
            $isStarQ = str_contains(strtoupper($query['sql']), 'SELECT *');
            if ($isN1q) {
                $aiItems[] = ['qi' => $qi, 'query' => $query, 'type' => 'n1', 'class' => 'n1', 'label' => 'N+1 Query',
                    'desc'   => 'Runs in a loop — once per parent record. Use eager loading.',
                    'fix'    => "Model::with('relation')->get()",
                    'prompt' => "I have an N+1 query issue in Laravel.\n\nSQL: ".$formatSql($query['sql'], $query['bindings'])."\nCaller: ".($query['caller'] ?? 'unknown')."\n\nPlease show me the Eloquent eager loading fix (before/after). Be concise, 5 lines max.",
                ];
            } elseif ($isSlowQ) {
                $aiItems[] = ['qi' => $qi, 'query' => $query, 'type' => 'slow', 'class' => 'slow', 'label' => 'Slow Query ('.number_format($query['time_ms'], 1).'ms)',
                    'desc'   => 'Exceeds '.$slowQueryMs.'ms. Add an index or optimise the query.',
                    'fix'    => 'Add index on WHERE/JOIN/ORDER BY columns',
                    'prompt' => "I have a slow SQL query in Laravel taking ".number_format($query['time_ms'], 1)."ms.\n\nSQL: ".$formatSql($query['sql'], $query['bindings'])."\nCaller: ".($query['caller'] ?? 'unknown')."\n\nSuggest the best index to add and any Eloquent refactoring. Be concise.",
                ];
            }
            if ($isStarQ) {
                $aiItems[] = ['qi' => $qi, 'query' => $query, 'type' => 'select_star', 'class' => 'star', 'label' => 'SELECT *',
                    'desc'   => 'Fetches all columns unnecessarily. Specify only needed columns.',
                    'fix'    => "->select('id', 'name', '...')",
                    'prompt' => "I have a SELECT * query in Laravel.\n\nSQL: ".$formatSql($query['sql'], $query['bindings'])."\nCaller: ".($query['caller'] ?? 'unknown')."\n\nSuggest which columns to select and show the Eloquent fix. Be concise.",
                ];
            } elseif ($isDupQ && !$isN1q) {
                $aiItems[] = ['qi' => $qi, 'query' => $query, 'type' => 'duplicate', 'class' => 'dup', 'label' => 'Duplicate ×'.($sqlCounts[$qNorm] ?? '?'),
                    'desc'   => 'Same query runs '.($sqlCounts[$qNorm] ?? '?').' times. Cache or restructure.',
                    'fix'    => 'Cache::remember() or query once',
                    'prompt' => "This SQL query runs ".($sqlCounts[$qNorm] ?? 'multiple')." times in a single Laravel request.\n\nSQL: ".$formatSql($query['sql'], $query['bindings'])."\nCaller: ".($query['caller'] ?? 'unknown')."\n\nSuggest how to deduplicate using caching or Eloquent changes. Be concise.",
                ];
            }
        }
      @endphp
      @if(empty($aiItems))
        <div class="dd-empty" style="color:#50fa7b;">✓ No query issues found — no AI suggestions needed!</div>
      @else
        {{-- Header --}}
        <div style="padding:10px 16px 0;display:flex;align-items:center;justify-content:space-between;">
          <div>
            <span style="font-size:12px;font-weight:700;color:#bd93f9;">{{ count($aiItems) }} issue{{ count($aiItems) !== 1 ? 's' : '' }} detected</span>
            <span style="color:#6272a4;font-size:11px;margin-left:8px;">
              @if($hasAiSdk)
                <span style="color:#50fa7b;font-size:9px;font-weight:700;">● laravel/ai</span> AI fixes available
              @else
                Copy prompts to use with any AI assistant
              @endif
            </span>
          </div>
          @if($hasAiSdk)
            <button class="q-ai-btn ai" style="font-size:10px;" onclick="__dd.aiFixAll()">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
              Fix All with AI
            </button>
          @endif
        </div>

        {{-- Issue list --}}
        @foreach($aiItems as $idx => $item)
          @php $panelRowId = '__dd_aip_'.$idx; @endphp
          <div style="border-bottom:1px solid #44475a;padding:12px 16px;">
            {{-- Issue header --}}
            <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;">
              <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                  <span class="q-badge {{ $item['class'] }}" style="font-size:10px;">{{ $item['label'] }}</span>
                  <span style="color:#6272a4;font-size:10px;">Query #{{ $item['qi'] + 1 }}</span>
                  @if($item['query']['caller'] ?? null)
                    <span style="color:#44475a;font-size:9px;">{{ $item['query']['caller'] }}</span>
                  @endif
                </div>
                <div class="ddsql" style="font-size:10.5px;color:#f8f8f2;margin-bottom:5px;">
                  {{ mb_substr($formatSql($item['query']['sql'], $item['query']['bindings']), 0, 200) }}{{ mb_strlen($formatSql($item['query']['sql'], $item['query']['bindings'])) > 200 ? '…' : '' }}
                </div>
                <div style="font-size:10px;color:#6272a4;margin-bottom:6px;">
                  {{ $item['desc'] }}
                  <code style="background:rgba(68,71,90,.5);padding:1px 5px;border-radius:2px;color:#8be9fd;margin-left:6px;">{{ $item['fix'] }}</code>
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;">
                @if($hasAiSdk)
                  <button class="q-ai-btn ai" id="{{ $panelRowId }}_btn"
                    data-result="{{ $panelRowId }}_result"
                    data-sql="{{ e($formatSql($item['query']['sql'], $item['query']['bindings'])) }}"
                    data-type="{{ $item['type'] }}"
                    data-caller="{{ e($item['query']['caller'] ?? '') }}"
                    data-time="{{ $item['query']['time_ms'] }}"
                    onclick="__dd.aiSuggestFromEl(this)">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                    Fix with AI
                  </button>
                @endif
                <button class="q-ai-btn copy" data-prompt="{{ e($item['prompt']) }}" onclick="__dd.copyPromptFromEl(this)">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                  Copy Prompt
                </button>
              </div>
            </div>
            {{-- AI result area --}}
            <div id="{{ $panelRowId }}_result" class="q-ai-result" style="display:none;"></div>
          </div>
        @endforeach
      @endif
    </div>

    {{-- Warnings panel --}}
    <div class="ddp" id="__ddp_warnings__">
      @if(empty($warnings))
        <div class="dd-empty" style="color:#50fa7b;">✓ No warnings detected — looking good!</div>
      @else
        @php
          $warnCritical = count(array_filter($warnings, fn ($w) => $w['severity'] === 'critical'));
          $warnWarning  = count(array_filter($warnings, fn ($w) => $w['severity'] === 'warning'));
          $warnInfo     = count(array_filter($warnings, fn ($w) => $w['severity'] === 'info'));
          $wSevColors   = ['critical' => '#ff5555', 'warning' => '#ffb86c', 'info' => '#8be9fd'];
          $wSevBgs      = ['critical' => 'rgba(255,85,85,.07)', 'warning' => 'rgba(255,184,108,.06)', 'info' => 'rgba(139,233,253,.05)'];
          $wSevBorder   = ['critical' => 'rgba(255,85,85,.4)', 'warning' => 'rgba(255,184,108,.35)', 'info' => 'rgba(139,233,253,.3)'];
        @endphp

        {{-- Summary strip --}}
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid #44475a;background:#21222c;">
          <span style="font-size:11px;font-weight:700;color:#f8f8f2;flex:1;">{{ count($warnings) }} warning{{ count($warnings) > 1 ? 's' : '' }} detected</span>
          @if($warnCritical > 0)
            <span style="background:rgba(255,85,85,.12);color:#ff5555;border:1px solid rgba(255,85,85,.3);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $warnCritical }} critical</span>
          @endif
          @if($warnWarning > 0)
            <span style="background:rgba(255,184,108,.12);color:#ffb86c;border:1px solid rgba(255,184,108,.3);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $warnWarning }} warning</span>
          @endif
          @if($warnInfo > 0)
            <span style="background:rgba(139,233,253,.1);color:#8be9fd;border:1px solid rgba(139,233,253,.25);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $warnInfo }} info</span>
          @endif
        </div>

        {{-- Category sections --}}
        @foreach($warnCategories as $cat)
          @php $catWarnings = array_values(array_filter($warnings, fn ($w) => $w['category'] === $cat)); @endphp
          @if(!empty($catWarnings))
            @php
              $catCritical = count(array_filter($catWarnings, fn ($w) => $w['severity'] === 'critical'));
              $catLabel    = $catCritical > 0 ? ($catCritical.' critical') : (count($catWarnings).' warning'.(count($catWarnings) > 1 ? 's' : ''));
              $catLabelCol = $catCritical > 0 ? '#ff5555' : '#ffb86c';
            @endphp
            <div style="border-bottom:1px solid rgba(68,71,90,.5);">
              {{-- Category header --}}
              <div style="display:flex;align-items:center;gap:8px;padding:5px 16px;background:#21222c;">
                <span style="font-size:10px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.07em;flex:1;">{{ $cat }}</span>
                <span style="font-size:10px;font-weight:600;color:{{ $catLabelCol }};">{{ $catLabel }}</span>
              </div>
              {{-- Warning rows --}}
              @foreach($catWarnings as $w)
                @php
                  $wColor  = $wSevColors[$w['severity']] ?? '#ffb86c';
                  $wBg     = $wSevBgs[$w['severity']] ?? '';
                  $wBorder = $wSevBorder[$w['severity']] ?? '';
                @endphp
                <div style="display:grid;grid-template-columns:14px 1fr;gap:8px;align-items:start;padding:7px 16px 7px 14px;background:{{ $wBg }};border-left:2px solid {{ $wBorder }};">
                  {{-- Severity icon --}}
                  <div style="padding-top:1px;">
                    @if($w['severity'] === 'critical')
                      <svg width="13" height="13" fill="none" stroke="{{ $wColor }}" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    @elseif($w['severity'] === 'info')
                      <svg width="13" height="13" fill="none" stroke="{{ $wColor }}" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                    @else
                      <svg width="13" height="13" fill="none" stroke="{{ $wColor }}" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    @endif
                  </div>
                  {{-- Warning body --}}
                  <div>
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                      <span style="font-size:11px;font-weight:700;color:{{ $wColor }};">{{ $w['title'] }}</span>
                      @if(!empty($w['panel']))
                        <button onclick="window.__dd && window.__dd.openPanel('{{ $w['panel'] }}')" style="font-size:9px;color:#6272a4;background:rgba(68,71,90,.4);border:none;padding:1px 6px;border-radius:3px;cursor:pointer;line-height:1.6;" onmouseover="this.style.color='#bd93f9'" onmouseout="this.style.color='#6272a4'">→ {{ $w['panel'] }}</button>
                      @endif
                    </div>
                    <div style="font-size:10px;color:#6272a4;line-height:1.5;">{{ $w['desc'] }}</div>
                    @if(!empty($w['fix']))
                      <div style="font-size:10px;color:#50fa7b;margin-top:3px;display:flex;align-items:flex-start;gap:4px;">
                        <span style="opacity:.7;flex-shrink:0;">Fix:</span>
                        <span>{{ $w['fix'] }}</span>
                      </div>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        @endforeach
      @endif
    </div>

    {{-- Audits panel --}}
    <div class="ddp" id="__ddp_audits__">
      @php
        $auditPass     = count(array_filter($audits, fn ($a) => $a['pass']));
        $auditCritical = count(array_filter($audits, fn ($a) => !$a['pass'] && $a['severity'] === 'critical'));
        $auditWarning  = count(array_filter($audits, fn ($a) => !$a['pass'] && $a['severity'] === 'warning'));
        $auditInfo     = count(array_filter($audits, fn ($a) => !$a['pass'] && $a['severity'] === 'info'));
        $severityColors = ['critical' => '#ff5555', 'warning' => '#ffb86c', 'info' => '#8be9fd'];
        $severityBgs    = ['critical' => 'rgba(255,85,85,.07)', 'warning' => 'rgba(255,184,108,.06)', 'info' => 'rgba(139,233,253,.05)'];
        $severityBorder = ['critical' => 'rgba(255,85,85,.4)', 'warning' => 'rgba(255,184,108,.35)', 'info' => 'rgba(139,233,253,.3)'];
      @endphp

      {{-- Score + summary strip --}}
      <div style="display:flex;align-items:center;gap:16px;padding:12px 16px;border-bottom:1px solid #44475a;background:#21222c;">
        <div style="text-align:center;flex-shrink:0;">
          <div style="font-size:26px;font-weight:800;line-height:1;color:{{ $auditScoreColor }}">{{ $auditScore }}</div>
          <div style="font-size:9px;color:#6272a4;text-transform:uppercase;letter-spacing:.06em;margin-top:2px;">score</div>
        </div>
        <div style="flex:1;display:flex;gap:8px;flex-wrap:wrap;">
          @if($auditCritical > 0)
            <span style="background:rgba(255,85,85,.12);color:#ff5555;border:1px solid rgba(255,85,85,.3);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $auditCritical }} critical</span>
          @endif
          @if($auditWarning > 0)
            <span style="background:rgba(255,184,108,.12);color:#ffb86c;border:1px solid rgba(255,184,108,.3);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $auditWarning }} warning</span>
          @endif
          @if($auditInfo > 0)
            <span style="background:rgba(139,233,253,.1);color:#8be9fd;border:1px solid rgba(139,233,253,.25);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $auditInfo }} info</span>
          @endif
          <span style="background:rgba(80,250,123,.1);color:#50fa7b;border:1px solid rgba(80,250,123,.25);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $auditPass }} passed</span>
        </div>
        <a href="/digdeep/audits" style="font-size:10px;color:#6272a4;white-space:nowrap;text-decoration:none;" onmouseover="this.style.color='#bd93f9'" onmouseout="this.style.color='#6272a4'">Full report →</a>
      </div>

      {{-- Category sections --}}
      @foreach($auditCategories as $cat)
        @php $catAudits = array_filter($audits, fn ($a) => $a['category'] === $cat); @endphp
        @php $catFailed = array_filter($catAudits, fn ($a) => !$a['pass']); @endphp
        <div style="border-bottom:1px solid rgba(68,71,90,.5);">
          <div style="display:flex;align-items:center;gap:8px;padding:6px 16px;background:#21222c;">
            <span style="font-size:10px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.07em;flex:1;">{{ $cat }}</span>
            @if(empty($catFailed))
              <span style="font-size:10px;color:#50fa7b;font-weight:600;">all passed</span>
            @else
              <span style="font-size:10px;color:#ff5555;font-weight:600;">{{ count($catFailed) }} issue{{ count($catFailed) > 1 ? 's' : '' }}</span>
            @endif
          </div>
          @foreach($catAudits as $audit)
            @php
              $sColor  = $audit['pass'] ? '#50fa7b' : ($severityColors[$audit['severity']] ?? '#ffb86c');
              $rowBg   = $audit['pass'] ? '' : 'background:'.$severityBgs[$audit['severity']].';border-left:2px solid '.$severityBorder[$audit['severity']].';';
            @endphp
            <div style="display:grid;grid-template-columns:14px 1fr;gap:8px;align-items:start;padding:6px 16px 6px {{ $audit['pass'] ? '16px' : '14px' }};{{ $rowBg }}">
              @if($audit['pass'])
                <svg width="12" height="12" fill="none" stroke="#50fa7b" stroke-width="2.5" viewBox="0 0 24 24" style="margin-top:1px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              @elseif($audit['severity'] === 'critical')
                <svg width="12" height="12" fill="none" stroke="#ff5555" stroke-width="2.5" viewBox="0 0 24 24" style="margin-top:1px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374l7.548-13.124c.866-1.5 3.032-1.5 3.898 0l7.548 13.124zM12 15.75h.007v.008H12v-.008z"/></svg>
              @elseif($audit['severity'] === 'info')
                <svg width="12" height="12" fill="none" stroke="#8be9fd" stroke-width="2.5" viewBox="0 0 24 24" style="margin-top:1px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
              @else
                <svg width="12" height="12" fill="none" stroke="#ffb86c" stroke-width="2.5" viewBox="0 0 24 24" style="margin-top:1px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
              @endif
              <div>
                <div style="font-size:11px;font-weight:600;color:{{ $sColor }};">{{ $audit['label'] }}</div>
                <div style="font-size:10px;color:#6272a4;margin-top:1px;">{{ $audit['detail'] }}</div>
                @if(!$audit['pass'])
                  <div style="font-size:10px;color:#6272a4;margin-top:3px;font-style:italic;">
                    <span style="color:#44475a;font-style:normal;">Fix → </span>{{ $audit['fix'] }}
                  </div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endforeach
    </div>

    {{-- Export panel --}}
    <div class="ddp" id="__ddp_export__">
      <div class="ddp-inner">

        {{-- Primary: export current page --}}
        <div class="ddp-title">Current Page</div>
        <button id="__dd_export_page_btn__" onclick="__dd.exportPage(this)" style="width:100%;display:flex;align-items:center;gap:10px;background:linear-gradient(135deg,rgba(189,147,249,.15),rgba(255,121,198,.1));border:1px solid rgba(189,147,249,.35);color:#f8f8f2;border-radius:8px;padding:12px 14px;cursor:pointer;font-size:12px;font-weight:700;font-family:inherit;transition:all .15s;margin-bottom:6px;text-align:left;" onmouseover="this.disabled||this.style.setProperty('background','linear-gradient(135deg,rgba(189,147,249,.25),rgba(255,121,198,.18))')" onmouseout="this.disabled||this.style.setProperty('background','linear-gradient(135deg,rgba(189,147,249,.15),rgba(255,121,198,.1))')">
          <svg id="__dd_export_icon__" fill="none" stroke="#bd93f9" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
          <div style="flex:1;">
            <div id="__dd_export_label__">Export Current Page as HTML</div>
            <div id="__dd_export_sub__" style="font-weight:400;font-size:10px;color:#6272a4;margin-top:1px;">CSS, fonts, and JS inlined — fully self-contained</div>
          </div>
        </button>
        <div id="__dd_export_progress__" style="display:none;margin-bottom:14px;padding:8px 12px;background:#1e1f2b;border:1px solid #44475a;border-radius:6px;font-size:10px;color:#6272a4;">
          <div style="display:flex;align-items:center;gap:8px;">
            <svg style="animation:dd-spin 1s linear infinite;flex-shrink:0;" width="12" height="12" fill="none" stroke="#bd93f9" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            <span id="__dd_export_step__">Preparing…</span>
          </div>
          <div style="margin-top:6px;background:#44475a;border-radius:3px;height:3px;overflow:hidden;"><div id="__dd_export_bar__" style="height:100%;background:#bd93f9;border-radius:3px;width:0%;transition:width .3s;"></div></div>
        </div>

        {{-- Secondary: DigDeep data reports --}}
        <div class="ddp-title" style="margin-bottom:8px;">DigDeep Reports</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;">
          <button onclick="window.location.href='/digdeep/api/html-export?template=dashboard'" style="background:rgba(189,147,249,.1);border:1px solid rgba(189,147,249,.25);color:#bd93f9;border-radius:6px;padding:8px 6px;cursor:pointer;font-size:10px;font-weight:700;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:4px;transition:all .15s;" onmouseover="this.style.background='rgba(189,147,249,.2)'" onmouseout="this.style.background='rgba(189,147,249,.1)'">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
            Dashboard
          </button>
          <button onclick="window.location.href='/digdeep/api/html-export?template=performance'" style="background:rgba(80,250,123,.08);border:1px solid rgba(80,250,123,.2);color:#50fa7b;border-radius:6px;padding:8px 6px;cursor:pointer;font-size:10px;font-weight:700;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:4px;transition:all .15s;" onmouseover="this.style.background='rgba(80,250,123,.18)'" onmouseout="this.style.background='rgba(80,250,123,.08)'">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
            Performance
          </button>
          <button onclick="window.location.href='/digdeep/api/html-export?template=profile&id={{ $profileId }}'" style="background:rgba(139,233,253,.08);border:1px solid rgba(139,233,253,.2);color:#8be9fd;border-radius:6px;padding:8px 6px;cursor:pointer;font-size:10px;font-weight:700;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:4px;transition:all .15s;" onmouseover="this.style.background='rgba(139,233,253,.18)'" onmouseout="this.style.background='rgba(139,233,253,.08)'">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
            Profile
          </button>
        </div>

      </div>
    </div>

    {{-- Logs panel --}}
    <div class="ddp" id="__ddp_logs__">
      @php
        $logLevelColors = [
            'emergency' => '#ff5555', 'alert' => '#ff5555', 'critical' => '#ff5555',
            'error'     => '#ff5555', 'warning' => '#ffb86c', 'notice' => '#8be9fd',
            'info'      => '#8be9fd', 'debug'   => '#6272a4',
        ];
        $logErrorCount = count(array_filter($logs, fn ($l) => in_array($l['level'], ['emergency', 'alert', 'critical', 'error'])));
        $logWarnCount  = count(array_filter($logs, fn ($l) => $l['level'] === 'warning'));
      @endphp
      <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 16px 0;">
        <span style="font-size:10px;color:#6272a4;">{{ count($logs) > 0 ? count($logs).' log '.(count($logs) === 1 ? 'entry' : 'entries').' captured this request' : 'No logs captured for this request' }}</span>
        <a href="/digdeep/logs" target="_blank" style="font-size:10px;color:#bd93f9;text-decoration:none;display:inline-flex;align-items:center;gap:3px;" onmouseover="this.style.color='#f8f8f2'" onmouseout="this.style.color='#bd93f9'">
          Full log viewer
          <svg width="9" height="9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
        </a>
      </div>
      @if(empty($logs))
        <div class="dd-empty" style="padding-top:12px;">No log messages captured for this request.<br><span style="font-size:10px;">Logs written via <code>Log::info()</code>, <code>Log::error()</code>, etc. will appear here.</span></div>
      @else
        <div style="display:flex;align-items:center;gap:10px;padding:8px 16px;border-bottom:1px solid #44475a;background:#21222c;">
          <span style="font-size:11px;font-weight:700;color:#f8f8f2;flex:1;">{{ count($logs) }} log {{ count($logs) === 1 ? 'entry' : 'entries' }}</span>
          @if($logErrorCount > 0)
            <span style="background:rgba(255,85,85,.12);color:#ff5555;border:1px solid rgba(255,85,85,.3);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $logErrorCount }} error{{ $logErrorCount > 1 ? 's' : '' }}</span>
          @endif
          @if($logWarnCount > 0)
            <span style="background:rgba(255,184,108,.12);color:#ffb86c;border:1px solid rgba(255,184,108,.3);font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">{{ $logWarnCount }} warning{{ $logWarnCount > 1 ? 's' : '' }}</span>
          @endif
        </div>
        <table>
          <thead><tr>
            <th style="width:60px;">Level</th>
            <th>Message</th>
            <th style="width:70px;">Time</th>
          </tr></thead>
          <tbody>
            @foreach($logs as $log)
              @php
                $logColor = $logLevelColors[$log['level']] ?? '#6272a4';
                $isLogError = in_array($log['level'], ['emergency', 'alert', 'critical', 'error']);
              @endphp
              <tr style="{{ $isLogError ? 'background:rgba(255,85,85,.05);' : ($log['level'] === 'warning' ? 'background:rgba(255,184,108,.04);' : '') }}">
                <td>
                  <span style="background:{{ $isLogError ? 'rgba(255,85,85,.15)' : ($log['level'] === 'warning' ? 'rgba(255,184,108,.15)' : ($log['level'] === 'debug' ? 'rgba(98,114,164,.15)' : 'rgba(139,233,253,.1)')) }};color:{{ $logColor }};font-size:9px;font-weight:800;padding:1px 5px;border-radius:3px;letter-spacing:.04em;text-transform:uppercase;">{{ $log['level'] }}</span>
                </td>
                <td>
                  <div style="color:#f8f8f2;font-size:11px;word-break:break-all;">{{ $log['message'] }}</div>
                  @if(!empty($log['context']))
                    <details style="display:block;margin-top:3px;">
                      <summary style="cursor:pointer;color:#8be9fd;font-size:10px;list-style:none;display:inline-flex;align-items:center;gap:3px;">
                        context
                      </summary>
                      <pre class="dd-json">{{ $log['context'] }}</pre>
                    </details>
                  @endif
                </td>
                <td class="ddtime">+{{ number_format($log['time_ms'], 1) }}<span style="color:#6272a4;font-size:9px;">ms</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    {{-- Session panel --}}
    <div class="ddp" id="__ddp_session__">
      @if(empty($sessionData))
        <div class="dd-empty">No session data recorded.</div>
      @else
        <div style="padding:8px 16px 0;color:#6272a4;font-size:10px;">{{ count($sessionData) }} {{ Str::plural('key', count($sessionData)) }} in session</div>
        <table>
          <thead><tr>
            <th>Key</th>
            <th>Type</th>
            <th>Value</th>
          </tr></thead>
          <tbody>
            @foreach($sessionData as $sKey => $sVal)
              <tr>
                <td style="color:#f8f8f2;font-weight:600;white-space:nowrap;">{{ $sKey }}</td>
                <td style="color:#8be9fd;font-size:10px;white-space:nowrap;">{{ $sVal['type'] }}</td>
                <td style="color:#f1fa8c;font-size:10px;word-break:break-all;max-width:400px;">
                  {{ mb_strlen($sVal['value']) > 150 ? mb_substr($sVal['value'], 0, 150).'…' : $sVal['value'] }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    {{-- Timeline panel --}}
    <div class="ddp" id="__ddp_timeline__">
      @php
        $tlDuration  = max($perf['duration_ms'], 0.01);
        $tlPhases    = $profile['lifecycle']['phases'] ?? [];
        $tlQueries   = $profile['queries'] ?? [];
        $tlHttp      = $profile['http_client'] ?? [];
        $tlPhaseClrs = [
            'bootstrap'       => '#bd93f9',
            'routing'         => '#8be9fd',
            'controller'      => '#50fa7b',
            'view'            => '#ffb86c',
            'middleware_done' => '#ff79c6',
            'response_ready'  => '#f1fa8c',
        ];
      @endphp

      {{-- Scale ruler --}}
      <div style="padding:12px 16px 2px;">
        <div style="display:grid;grid-template-columns:140px 1fr 60px;gap:8px;align-items:center;margin-bottom:2px;">
          <div></div>
          <div style="display:flex;justify-content:space-between;color:#44475a;font-size:9px;">
            <span>0ms</span>
            <span>{{ number_format($tlDuration / 4, 0) }}</span>
            <span>{{ number_format($tlDuration / 2, 0) }}</span>
            <span>{{ number_format($tlDuration * 3 / 4, 0) }}</span>
            <span>{{ number_format($tlDuration, 0) }}ms</span>
          </div>
          <div></div>
        </div>
      </div>

      {{-- Lifecycle Phases --}}
      @if(!empty($tlPhases))
        <div style="padding:4px 16px 8px;">
          <div style="font-size:9px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Lifecycle</div>
          @foreach($tlPhases as $tlPhase)
            @php
              $pMs    = $tlPhase['duration_ms'] ?? 0;
              $pStart = $tlPhase['offset_ms']   ?? 0;
              $pColor = $tlPhaseClrs[$tlPhase['name'] ?? ''] ?? '#6272a4';
              $pW     = min(100, ($pMs / $tlDuration) * 100);
              $pL     = min(98, ($pStart / $tlDuration) * 100);
            @endphp
            <div style="display:grid;grid-template-columns:140px 1fr 60px;align-items:center;gap:8px;padding:2px 0;" title="{{ number_format($pMs, 1) }}ms @ {{ number_format($pStart, 1) }}ms">
              <div style="font-size:10px;color:#6272a4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ str_replace('_', ' ', $tlPhase['name'] ?? '') }}</div>
              <div style="background:#2d2f3e;border-radius:2px;height:8px;position:relative;">
                <div style="position:absolute;top:0;height:100%;border-radius:2px;min-width:2px;background:{{ $pColor }};width:{{ number_format($pW, 2) }}%;left:{{ number_format($pL, 2) }}%;opacity:.85;"></div>
              </div>
              <div style="font-size:10px;color:#8be9fd;text-align:right;white-space:nowrap;">{{ number_format($pMs, 1) }}ms</div>
            </div>
          @endforeach
        </div>
      @endif

      {{-- Queries --}}
      @if(!empty($tlQueries))
        <div style="padding:4px 16px 8px;border-top:1px solid #44475a;">
          <div style="font-size:9px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Queries ({{ count($tlQueries) }})</div>
          @foreach(array_slice($tlQueries, 0, 40) as $tlQi => $tlQ)
            @php
              $qMs    = $tlQ['time_ms'] ?? 0;
              $qStart = $tlQ['start_offset_ms'] ?? 0;
              $qIsN1  = in_array($sqlNorm($tlQ['sql']), $n1Sqls);
              $qIsSl  = $qMs >= $slowQueryMs;
              $qClr   = $qIsN1 ? '#ff5555' : ($qIsSl ? '#ffb86c' : '#8be9fd');
              $qW     = max(0.3, min(100, ($qMs / $tlDuration) * 100));
              $qL     = min(99.5, ($qStart / $tlDuration) * 100);
            @endphp
            <div style="display:grid;grid-template-columns:140px 1fr 60px;align-items:center;gap:8px;padding:1px 0;" title="{{ e(mb_substr($tlQ['sql'], 0, 120)) }} — {{ number_format($qMs, 2) }}ms">
              <div style="font-size:9px;color:#6272a4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                @if($qIsN1)<span style="color:#ff5555;font-weight:700;">N+1 </span>@elseif($qIsSl)<span style="color:#ffb86c;font-weight:700;">slow </span>@endif
                #{{ $tlQi + 1 }} {{ mb_substr($tlQ['sql'], 0, 28) }}
              </div>
              <div style="background:#2d2f3e;border-radius:2px;height:5px;position:relative;">
                <div style="position:absolute;top:0;height:100%;border-radius:2px;min-width:2px;background:{{ $qClr }};width:{{ number_format($qW, 2) }}%;left:{{ number_format($qL, 2) }}%;opacity:.7;"></div>
              </div>
              <div style="font-size:9px;color:{{ $qClr }};text-align:right;white-space:nowrap;">{{ number_format($qMs, 2) }}ms</div>
            </div>
          @endforeach
          @if(count($tlQueries) > 40)
            <div style="font-size:10px;color:#6272a4;padding-top:4px;">+{{ count($tlQueries) - 40 }} more queries</div>
          @endif
        </div>
      @endif

      {{-- HTTP calls (duration only, no start offset) --}}
      @if(!empty($tlHttp))
        <div style="padding:4px 16px 8px;border-top:1px solid #44475a;">
          <div style="font-size:9px;font-weight:700;color:#6272a4;text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">HTTP Client ({{ count($tlHttp) }})</div>
          @foreach($tlHttp as $tlH)
            @php
              $hMs  = $tlH['duration_ms'] ?? 0;
              $hClr = $hMs > 1000 ? '#ff5555' : ($hMs > 500 ? '#ffb86c' : '#50fa7b');
              $hW   = max(0.3, min(100, ($hMs / $tlDuration) * 100));
            @endphp
            <div style="display:grid;grid-template-columns:140px 1fr 60px;align-items:center;gap:8px;padding:1px 0;" title="{{ e($tlH['url']) }}">
              <div style="font-size:9px;color:#6272a4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <span style="color:{{ $hClr }};font-weight:700;">{{ $tlH['method'] }} </span>{{ parse_url($tlH['url'], PHP_URL_HOST) }}
              </div>
              <div style="background:#2d2f3e;border-radius:2px;height:5px;overflow:hidden;">
                <div style="height:100%;border-radius:2px;min-width:2px;background:{{ $hClr }};width:{{ number_format($hW, 2) }}%;opacity:.7;"></div>
              </div>
              <div style="font-size:9px;color:{{ $hClr }};text-align:right;white-space:nowrap;">{{ number_format($hMs, 0) }}ms</div>
            </div>
          @endforeach
        </div>
      @endif
    </div>

  </div>
</div>

{{-- Main bar --}}
<div id="__digdeep_bar__">

  {{-- Logo / collapse --}}
  <div class="dd-logo" onclick="__dd.toggleCollapse()" title="Toggle DigDeep debugbar">
    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
    </svg>
    <span>DigDeep</span>
  </div>

  {{-- Route info — clickable, opens route panel --}}
  <div class="dd-req" onclick="__dd.openPanel('route')" title="{{ $method }} {{ $url }}" style="cursor:pointer;" id="__ddm_route__">
    <span style="color:#6272a4;font-size:10px;font-weight:700;letter-spacing:.04em;">Route</span>
    <span class="dd-status" style="color:{{ $statusColor }}">{{ $status }}</span>
  </div>

  {{-- Exception alert --}}
  @if($exception)
    <div class="dd-exc" onclick="__dd.openPanel('exception')" title="{{ $exception['class'] }}">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
      </svg>
      <span>Exception</span>
    </div>
  @endif

  {{-- Metrics — ordered: perf → rendering → data → analysis → details --}}
  <div class="dd-metrics">

    {{-- Queries --}}
    <div class="ddm" id="__ddm_queries__" onclick="__dd.openPanel('queries')" title="SQL queries — click to inspect">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75"/>
      </svg>
      <span class="ddm-val" style="color:{{ $queryColor }}">{{ $queryCount }}</span>
      @if($problemQueryCount > 0)
        <span class="ddm-n1" style="background:#ffb86c;color:#21222c;">{{ $problemQueryCount }}⚠</span>
      @endif
      <span class="ddm-lbl">· {{ number_format($queryTime, 1) }}ms</span>
      @if(!empty($nPlusOne))<span class="ddm-n1">N+1</span>@endif
    </div>

    {{-- Lifecycle / timing --}}
    <div class="ddm" id="__ddm_lifecycle__" onclick="__dd.openPanel('lifecycle')" title="Request lifecycle & performance — click to inspect">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
      </svg>
      <span class="ddm-val" style="color:{{ $durationColor }}">{{ number_format($duration, 1) }}ms</span>
    </div>

    {{-- Views / Inertia Vue --}}
    @if($hasView)
      <div class="ddm" id="__ddm_views__" onclick="__dd.openPanel('views')"
           title="{{ $isInertia ? 'Inertia Vue component — click to inspect props' : 'Blade views — click to inspect' }}">
        @if($isInertia)
          <svg width="12" height="12" viewBox="0 0 261.76 226.69" fill="none">
            <path d="M161.096.001l-30.224 52.35L100.647.001H0l130.872 226.689L261.76.001z" fill="#41b883"/>
            <path d="M161.096.001l-30.224 52.35L100.647.001H52.346l78.526 136.01L209.4.001z" fill="#34495e"/>
          </svg>
          <span class="ddm-val" style="color:#50fa7b">{{ basename(str_replace('/', DIRECTORY_SEPARATOR, $inertiaComponent)) }}</span>
        @else
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
          </svg>
          <span class="ddm-val" style="color:#ffb86c">{{ $viewCount }}</span>
          <span class="ddm-lbl">blade</span>
        @endif
      </div>
    @endif

    {{-- Cache --}}
    @if(!empty($cache))
      <div class="ddm" id="__ddm_cache__" onclick="__dd.openPanel('cache')" title="Cache — {{ $hitRate }}% hit rate — click to inspect">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
        </svg>
        <span class="ddm-val" style="color:{{ $hitRate >= 80 ? '#50fa7b' : ($hitRate >= 50 ? '#ffb86c' : '#ff5555') }}">{{ $hitRate }}%</span>
        <span class="ddm-lbl">cache</span>
      </div>
    @endif

    {{-- Models --}}
    @if($modelTotal > 0)
      <div class="ddm" id="__ddm_models__" onclick="__dd.openPanel('models')" title="Eloquent model operations — click to inspect">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/>
        </svg>
        <span class="ddm-val" style="color:#bd93f9">{{ $modelTotal }}</span>
        <span class="ddm-lbl">models</span>
      </div>
    @endif

    {{-- Events --}}
    @if($eventCount > 0)
      <div class="ddm" id="__ddm_events__" onclick="__dd.openPanel('events')" title="Fired events — click to inspect">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
        </svg>
        <span class="ddm-val" style="color:#f8f8f2">{{ $eventCount }}</span>
        <span class="ddm-lbl">events</span>
      </div>
    @endif

    {{-- AI --}}
    @php $totalAiIssues = $problemQueryCount; @endphp
    @if($totalAiIssues > 0 || $hasAiSdk)
      <div class="ddm" id="__ddm_ai__" onclick="__dd.openPanel('ai')" title="AI query suggestions — click to inspect">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
        </svg>
        <span class="ddm-val" style="color:#bd93f9;">AI</span>
        @if($totalAiIssues > 0)
          <span class="ddm-n1" style="background:#bd93f9;color:#21222c;">{{ $totalAiIssues }}</span>
        @endif
      </div>
    @endif

    {{-- HTTP Client --}}
    @if(!empty($httpCalls))
      <div class="ddm" id="__ddm_http__" onclick="__dd.openPanel('http')"
           title="{{ count($httpCalls) }} outbound HTTP request(s) — click to inspect">
        <svg fill="none" stroke="{{ count(array_filter($httpCalls, fn($r) => ($r['status']??200)>=400)) > 0 ? '#ff5555' : '#8be9fd' }}" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
        </svg>
        <span class="ddm-val" style="color:{{ count(array_filter($httpCalls, fn($r) => ($r['status']??200)>=400)) > 0 ? '#ff5555' : '#8be9fd' }}">{{ count($httpCalls) }}</span>
        <span class="ddm-lbl">http</span>
      </div>
    @endif

    {{-- Warnings --}}
    @if($hasWarnings)
      <div class="ddm" id="__ddm_warnings__" onclick="__dd.openPanel('warnings')"
           title="{{ $warningCount }} warning(s) detected — click to inspect">
        <svg fill="none" stroke="{{ $criticalCount > 0 ? '#ff5555' : '#ffb86c' }}" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
        <span class="ddm-val" style="color:{{ $criticalCount > 0 ? '#ff5555' : '#ffb86c' }}">{{ $warningCount }}</span>
        <span class="ddm-lbl">warn</span>
      </div>
    @endif

    {{-- Audits --}}
    <div class="ddm" id="__ddm_audits__" onclick="__dd.openPanel('audits')" title="Audit score: {{ $auditScore }}/100 — click to inspect">
      <svg fill="none" stroke="{{ $auditCritical > 0 ? '#ff5555' : ($auditWarning > 0 ? '#ffb86c' : '#50fa7b') }}" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
      </svg>
      <span class="ddm-val" style="color:{{ $auditScoreColor }}">{{ $auditScore }}</span>
      <span class="ddm-lbl">score</span>
    </div>

    {{-- Separator --}}
    <div style="width:1px;background:#44475a;margin:6px 0;flex-shrink:0;"></div>

    {{-- Timeline --}}
    <div class="ddm" id="__ddm_timeline__" onclick="__dd.openPanel('timeline')" title="Request timeline — click to inspect">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/>
      </svg>
      <span class="ddm-lbl">timeline</span>
    </div>

    {{-- Session --}}
    @if(!empty($sessionData))
      <div class="ddm" id="__ddm_session__" onclick="__dd.openPanel('session')" title="Session data ({{ count($sessionData) }} keys) — click to inspect">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
        </svg>
        <span class="ddm-lbl">session</span>
      </div>
    @endif

    {{-- Request headers --}}
    <div class="ddm" id="__ddm_request__" onclick="__dd.openPanel('request')" title="Request & response headers — click to inspect">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 004.5 9.75v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25h-.75m0-3l-3-3m0 0l-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 012.25 2.25v7.5a2.25 2.25 0 01-2.25 2.25h-7.5a2.25 2.25 0 01-2.25-2.25v-.75"/>
      </svg>
      <span class="ddm-lbl">req</span>
    </div>


    {{-- AJAX history (populated by JS) --}}
    <div class="ddm" id="__ddm_ajax__" onclick="__dd.openPanel('ajax')" title="AJAX / Inertia navigation history" style="display:none;">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
      </svg>
      <span class="ddm-val" style="color:#8be9fd" id="__dd_ajax_count__">0</span>
      <span class="ddm-lbl">ajax</span>
    </div>

    {{-- Logs --}}
    <div class="ddm" id="__ddm_logs__" onclick="__dd.openPanel('logs')"
         title="{{ count($logs) > 0 ? count($logs).' log '.(count($logs) === 1 ? 'entry' : 'entries').' this request' : 'Logs — click to open viewer' }}{{ isset($logErrorCount) && $logErrorCount > 0 ? ' · '.$logErrorCount.' error'.($logErrorCount > 1 ? 's' : '') : '' }}">
      <svg fill="none" stroke="{{ isset($logErrorCount) && $logErrorCount > 0 ? '#ff5555' : (isset($logWarnCount) && $logWarnCount > 0 ? '#ffb86c' : '#6272a4') }}" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
      </svg>
      @if(count($logs) > 0)
        <span class="ddm-val" style="color:{{ isset($logErrorCount) && $logErrorCount > 0 ? '#ff5555' : (isset($logWarnCount) && $logWarnCount > 0 ? '#ffb86c' : '#6272a4') }}">{{ count($logs) }}</span>
        @if(isset($logErrorCount) && $logErrorCount > 0)
          <span class="ddm-n1">{{ $logErrorCount }}</span>
        @endif
      @endif
      <span class="ddm-lbl">logs</span>
    </div>

  </div>

  {{-- Actions --}}
  <div class="dd-actions">

    {{-- Memory stat (non-interactive) --}}
    <div class="dd-stat" title="Peak memory">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z"/>
      </svg>
      <span style="color:{{ $memoryColor }}">{{ $memory }}MB</span>
    </div>

    {{-- PHP version --}}
    <div class="dd-stat" title="PHP {{ PHP_VERSION }}">
      <span style="color:#6272a4;font-size:9px;font-weight:700;">PHP</span>
      <span style="color:#8be9fd;">{{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}</span>
    </div>

    {{-- Laravel version --}}
    <div class="dd-stat" title="Laravel {{ app()->version() }}">
      <span style="color:#6272a4;font-size:9px;font-weight:700;">L</span>
      <span style="color:#ff79c6;">{{ Str::before(app()->version(), '.', app()->version()) }}</span>
    </div>

    {{-- Vue badge (when Inertia detected) --}}
    @if($isInertia)
      <div class="dd-stat" title="Vue 3 via Inertia">
        <svg width="10" height="10" viewBox="0 0 261.76 226.69" fill="none">
          <path d="M161.096.001l-30.224 52.35L100.647.001H0l130.872 226.689L261.76.001z" fill="#41b883"/>
          <path d="M161.096.001l-30.224 52.35L100.647.001H52.346l78.526 136.01L209.4.001z" fill="#34495e"/>
        </svg>
        <span style="color:#41b883;">Vue 3</span>
      </div>
    @endif

    {{-- Export --}}
    <div class="ddm" id="__ddm_export__" onclick="__dd.openPanel('export')" title="Export as HTML — click to download">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
      </svg>
      <span class="ddm-lbl">export</span>
    </div>

    <a href="/digdeep/profile/{{ $profileId }}" target="_blank" title="Open full profile in DigDeep">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
      </svg>
      Profile
    </a>
    <a href="/digdeep" target="_blank" title="Open DigDeep dashboard">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
      </svg>
      Dashboard
    </a>
    <button onclick="__dd.hide()" title="Hide debugbar">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

</div>

{{-- Minimised FAB --}}
<button id="__digdeep_fab__" onclick="__dd.restore()" title="Restore DigDeep debugbar">
  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
  </svg>
</button>

<script>
(function(){
  var bar = document.getElementById('__digdeep_bar__');
  var fab = document.getElementById('__digdeep_fab__');
  var panels = document.getElementById('__digdeep_panels__');
  var label = document.getElementById('__dd_panel_label__');
  var activePanel = null;
  var LS_KEY = '__dd_minimized';
  var LS_TAB_KEY = '__dd_tab';
  var LS_HEIGHT_KEY = '__dd_height';
  var panelLabels = {
    route: 'Request Pipeline',
    request: 'Request & Response Headers',
    ajax: 'AJAX / Inertia Navigation',
    ai: 'AI Suggestions ({{ $totalAiIssues ?? 0 }} issue{{ ($totalAiIssues ?? 0) !== 1 ? "s" : "" }})',
    queries: 'Queries ({{ $queryCount }} · {{ number_format($queryTime, 1) }}ms)',
    views: '{{ $isInertia ? 'Vue · '.$inertiaComponent : 'Blade Views ('.$viewCount.')' }}',
    events: 'Events ({{ $eventCount }})',
    cache: 'Cache ({{ $cacheHits }}h / {{ $cacheMisses }}m / {{ $cacheWrites }}w)',
    models: 'Models ({{ $modelTotal }})',
    lifecycle: 'Request Lifecycle',
    http: 'HTTP Client ({{ count($httpCalls) }} request{{ count($httpCalls) !== 1 ? "s" : "" }}{{ count(array_filter($httpCalls, fn($r) => ($r["status"]??200)>=400)) > 0 ? " · ".count(array_filter($httpCalls, fn($r) => ($r["status"]??200)>=400))." failed" : "" }})',
    warnings: 'Warnings ({{ $warningCount }}){{ $criticalCount > 0 ? " — ".$criticalCount." critical" : "" }}',
    audits: 'Audits — Score {{ $auditScore }}/100{{ $auditCritical > 0 ? " · ".$auditCritical." critical" : "" }}{{ $auditWarning > 0 ? " · ".$auditWarning." warning" : "" }}',
    exception: 'Exception',
    export: 'Export as HTML',
    session: 'Session ({{ count($sessionData) }} {{ Str::plural("key", count($sessionData)) }})',
    timeline: 'Request Timeline — {{ number_format($duration, 1) }}ms total',
    logs: 'Logs{{ count($logs) > 0 ? " (".count($logs).")" : "" }}{{ isset($logErrorCount) && $logErrorCount > 0 ? " · ".$logErrorCount." error".($logErrorCount > 1 ? "s" : "") : "" }}',
  };

  function applyMinimized() {
    bar.style.display = 'none';
    panels.style.display = 'none';
    fab.style.display = 'flex';
    document.body.style.paddingBottom = Math.max(0, parseInt(document.body.style.paddingBottom || '0') - 36) + 'px';
  }

  function applyRestored() {
    bar.style.display = 'flex';
    fab.style.display = 'none';
    document.body.style.paddingBottom = (parseInt(document.body.style.paddingBottom || '0') + 36) + 'px';
  }

  // Restore persisted height
  var savedHeight = parseInt(localStorage.getItem(LS_HEIGHT_KEY) || '0');
  if (savedHeight >= 120 && savedHeight <= 800) {
    panels.style.maxHeight = savedHeight + 'px';
    panels.style.height = savedHeight + 'px';
    var restoredInner = document.getElementById('__digdeep_panels_inner__');
    if (restoredInner) { restoredInner.style.maxHeight = (savedHeight - 5) + 'px'; restoredInner.style.height = (savedHeight - 5) + 'px'; }
  }

  // Restore persisted state on load
  if (localStorage.getItem(LS_KEY) === '1') {
    applyMinimized();
  } else {
    applyRestored();
    // Restore last active tab
    var savedTab = localStorage.getItem(LS_TAB_KEY);
    if (savedTab) { setTimeout(function() { window.__dd.openPanel(savedTab); }, 0); }
  }

  window.__dd = {
    openPanel: function(name) {
      document.querySelectorAll('#__digdeep_panels__ .ddp').forEach(function(p){ p.classList.remove('active'); });
      document.querySelectorAll('#__digdeep_bar__ .ddm').forEach(function(m){ m.classList.remove('active'); });
      var panel = document.getElementById('__ddp_' + name + '__');
      var metric = document.getElementById('__ddm_' + name + '__');
      if (activePanel === name && panels.style.display !== 'none') {
        panels.style.display = 'none';
        activePanel = null;
        return;
      }
      if (panel) {
        panel.classList.add('active');
        panels.style.display = 'block';
        label.textContent = panelLabels[name] || name;
        activePanel = name;
        try { localStorage.setItem(LS_TAB_KEY, name); } catch(e) {}
      }
      if (metric) { metric.classList.add('active'); }
    },
    closePanel: function() {
      panels.style.display = 'none';
      document.querySelectorAll('#__digdeep_bar__ .ddm').forEach(function(m){ m.classList.remove('active'); });
      activePanel = null;
      try { localStorage.removeItem(LS_TAB_KEY); } catch(e) {}
    },
    hide: function() {
      applyMinimized();
      activePanel = null;
      localStorage.setItem(LS_KEY, '1');
    },
    restore: function() {
      applyRestored();
      localStorage.removeItem(LS_KEY);
    },
    exportPage: async function(triggerBtn) {
      var btn    = triggerBtn || document.getElementById('__dd_export_page_btn__');
      var label  = document.getElementById('__dd_export_label__');
      var sub    = document.getElementById('__dd_export_sub__');
      var prog   = document.getElementById('__dd_export_progress__');
      var step   = document.getElementById('__dd_export_step__');
      var bar    = document.getElementById('__dd_export_bar__');
      var setStep = function(msg, pct) {
        if (step) { step.textContent = msg; }
        if (bar)  { bar.style.width  = (pct || 0) + '%'; }
      };
      var setLoading = function(on) {
        if (btn)  { btn.disabled = on; btn.style.opacity = on ? '0.7' : ''; }
        if (prog) { prog.style.display = on ? 'block' : 'none'; }
        if (label){ label.textContent = on ? 'Exporting…' : 'Export Current Page as HTML'; }
        if (sub && !on) { sub.textContent = 'CSS, fonts, and JS inlined — fully self-contained'; }
      };

      setLoading(true);
      setStep('Reading asset manifest…', 5);

      try {
        var origin = window.location.origin;
        var cssChunks = []; // [{ content, base }]
        var jsChunks  = []; // [{ content }]

        // ── 1. Parse Vite manifest to find built CSS + JS ──────────────────────
        var manifest = null;
        try {
          var mr = await fetch(origin + '/build/manifest.json');
          if (mr.ok) { manifest = await mr.json(); }
        } catch(e) {}

        var mainCssPath = null;
        var mainJsPath  = null;
        var pageCssPaths = [];
        var pageJsPath  = null;

        if (manifest) {
          var entry = manifest['resources/js/app.ts'] || manifest['resources/js/app.js']
                   || Object.values(manifest).find(function(v) { return v.isEntry; });
          if (entry) {
            if (entry.css && entry.css.length) { mainCssPath = '/build/' + entry.css[0]; }
            if (entry.file) { mainJsPath = '/build/' + entry.file; }
          }
          // Find current page component chunk from Inertia's data-page
          var appEl = document.getElementById('app');
          if (appEl && appEl.dataset && appEl.dataset.page) {
            try {
              var pd = JSON.parse(appEl.dataset.page);
              var compKey = 'resources/js/pages/' + pd.component + '.vue';
              var compEntry = manifest[compKey];
              if (compEntry) {
                if (compEntry.css)  { pageCssPaths = compEntry.css.map(function(f){ return '/build/' + f; }); }
                if (compEntry.file) { pageJsPath = '/build/' + compEntry.file; }
              }
            } catch(e) {}
          }
        }

        // ── 2. Fetch CSS ───────────────────────────────────────────────────────
        setStep('Fetching styles (Tailwind, fonts)…', 15);

        // 2a. Production Vite CSS bundle
        if (mainCssPath) {
          try {
            var cr = await fetch(origin + mainCssPath);
            if (cr.ok) { cssChunks.push({ content: await cr.text(), base: origin + mainCssPath }); }
          } catch(e) {}
        }

        // 2b. Page-specific CSS chunks
        for (var pcPath of pageCssPaths) {
          try {
            var pcr = await fetch(origin + pcPath);
            if (pcr.ok) { cssChunks.push({ content: await pcr.text(), base: origin + pcPath }); }
          } catch(e) {}
        }

        // 2c. Existing <link rel="stylesheet"> tags (bunny.net fonts, etc.)
        var liveLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
        for (var lnk of liveLinks) {
          var lhref = lnk.href;
          if (mainCssPath && lhref.includes(mainCssPath.split('/').pop())) { continue; }
          try {
            var lkr = await fetch(lhref);
            if (lkr.ok) { cssChunks.push({ content: await lkr.text(), base: lhref }); }
          } catch(e) {}
        }

        // 2d. Inline font binaries in all CSS
        setStep('Inlining fonts as base64…', 30);
        for (var ci = 0; ci < cssChunks.length; ci++) {
          cssChunks[ci].content = await __dd._inlineFontsInCss(cssChunks[ci].content, cssChunks[ci].base);
        }

        // ── 3. Fetch JS ────────────────────────────────────────────────────────
        setStep('Fetching JavaScript bundles…', 55);

        // 3a. Main app bundle (Vue + Inertia + shared code)
        if (mainJsPath) {
          try {
            var jr = await fetch(origin + mainJsPath);
            if (jr.ok) {
              var jsText = await jr.text();
              // Patch relative dynamic chunk imports → absolute URLs pointing back to server
              // Vite generates: import("./About-xxx.js") or import('./About-xxx.js')
              jsText = jsText.replace(/\bimport\((["'])\.\/([^"']+\.js)\1\)/g,
                'import($1' + origin + '/build/assets/$2$1)');
              jsChunks.push({ content: jsText });
            }
          } catch(e) {}
        }

        // 3b. Current page component chunk (ensures current page is interactive offline)
        if (pageJsPath) {
          try {
            var pjr = await fetch(origin + pageJsPath);
            if (pjr.ok) { jsChunks.push({ content: await pjr.text() }); }
          } catch(e) {}
        }

        // ── 4. Build HTML ──────────────────────────────────────────────────────
        setStep('Building self-contained document…', 75);

        var clone = document.documentElement.cloneNode(true);

        // Remove debugbar
        ['__digdeep_bar__', '__digdeep_panels__', '__digdeep_fab__'].forEach(function(id) {
          var el = clone.querySelector('#' + id);
          if (el) { el.remove(); }
        });
        var bodyEl = clone.querySelector('body');
        if (bodyEl) { bodyEl.style.removeProperty('padding-bottom'); }

        // Remove Vite scripts (dev server + built)
        Array.from(clone.querySelectorAll('script[src]')).forEach(function(s) { s.remove(); });

        // Remove stylesheet links (replaced with inlined styles)
        Array.from(clone.querySelectorAll('link[rel="stylesheet"]')).forEach(function(l) { l.remove(); });

        // Remove Vite preload / prefetch hints (they'd fail anyway)
        Array.from(clone.querySelectorAll('link[rel="modulepreload"], link[rel="preload"], link[rel="prefetch"]')).forEach(function(l) { l.remove(); });

        // Inject inlined CSS into <head>
        var headEl = clone.querySelector('head');
        if (headEl && cssChunks.length) {
          var styleEl = document.createElement('style');
          styleEl.textContent = cssChunks.map(function(c) { return c.content; }).join('\n\n');
          headEl.appendChild(styleEl);
        }

        // Inject inlined JS before </body>
        for (var jObj of jsChunks) {
          var scriptEl = document.createElement('script');
          scriptEl.setAttribute('type', 'module');
          scriptEl.textContent = jObj.content;
          if (bodyEl) { bodyEl.appendChild(scriptEl); }
        }

        // Watermark
        var stampEl = document.createElement('div');
        stampEl.setAttribute('style', 'position:fixed;bottom:8px;right:8px;background:rgba(33,34,44,.9);color:#6272a4;font-size:9px;padding:3px 8px;border-radius:4px;border:1px solid #44475a;font-family:monospace;z-index:999999;pointer-events:none;');
        stampEl.textContent = 'DigDeep export · ' + new Date().toLocaleString();
        if (bodyEl) { bodyEl.appendChild(stampEl); }

        // ── 5. Download ────────────────────────────────────────────────────────
        setStep('Generating file…', 92);

        var filename = 'page' + window.location.pathname.replace(/\/$/, '').replace(/\//g, '-').replace(/^-/, '') + '.html';
        var htmlStr  = '<!DOCTYPE html>\n' + clone.outerHTML;
        var blob     = new Blob([htmlStr], { type: 'text/html;charset=utf-8' });
        var dlUrl    = URL.createObjectURL(blob);
        var dlA      = document.createElement('a');
        dlA.href     = dlUrl;
        dlA.download = filename;
        document.body.appendChild(dlA);
        dlA.click();
        setTimeout(function() { document.body.removeChild(dlA); URL.revokeObjectURL(dlUrl); }, 200);

        setStep('Done!', 100);
        setTimeout(function() { setLoading(false); }, 800);

      } catch(err) {
        setLoading(false);
        console.error('[DigDeep] exportPage failed:', err);
      }
    },
    _inlineFontsInCss: async function(css, baseUrl) {
      // Find all font file references inside url(...)
      var re = /url\((['"]?)([^'")\s]+\.(?:woff2?|ttf|otf|eot)(?:[?#][^'")\s]*)?)\1\)/gi;
      var matches = [];
      var m;
      while ((m = re.exec(css)) !== null) {
        matches.push({ full: m[0], url: m[2] });
      }
      var seen = {};
      for (var item of matches) {
        if (seen[item.url]) { continue; }
        seen[item.url] = true;
        var fUrl = item.url;
        if (fUrl.startsWith('//')) { fUrl = 'https:' + fUrl; }
        else if (!fUrl.startsWith('http') && !fUrl.startsWith('data:')) {
          try { fUrl = new URL(fUrl, baseUrl).href; } catch(e) { continue; }
        }
        if (fUrl.startsWith('data:')) { continue; }
        try {
          var fr = await fetch(fUrl);
          if (!fr.ok) { continue; }
          var buf  = await fr.arrayBuffer();
          var bytes = new Uint8Array(buf);
          // Chunked btoa to avoid call-stack overflow on large font files
          var b64 = '', chunkSz = 32768;
          for (var i = 0; i < bytes.length; i += chunkSz) {
            b64 += String.fromCharCode.apply(null, bytes.subarray(i, Math.min(i + chunkSz, bytes.length)));
          }
          b64 = btoa(b64);
          var lc = fUrl.split('?')[0].toLowerCase();
          var mime = lc.endsWith('.woff2') ? 'font/woff2'
                   : lc.endsWith('.woff')  ? 'font/woff'
                   : lc.endsWith('.ttf')   ? 'font/truetype' : 'font/opentype';
          css = css.split(item.url).join('data:' + mime + ';base64,' + b64);
        } catch(e) {}
      }
      return css;
    },
    escHtml: function(str) {
      return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    },
    // ── AI cache ─────────────────────────────────────────────────────────────
    _aiCacheTtl: 86400000, // 24 hours
    simpleHash: function(str) {
      var h = 0;
      for (var i = 0; i < str.length; i++) { h = Math.imul(31, h) + str.charCodeAt(i) | 0; }
      return Math.abs(h).toString(36);
    },
    aiCacheKey: function(type, id) {
      return 'dd:ai:' + type + ':' + __dd.simpleHash(id);
    },
    aiCacheGet: function(key) {
      try {
        var raw = localStorage.getItem(key);
        if (!raw) { return null; }
        var entry = JSON.parse(raw);
        if (Date.now() - entry.ts > __dd._aiCacheTtl) { localStorage.removeItem(key); return null; }
        return entry.data;
      } catch(e) { return null; }
    },
    aiCacheSet: function(key, data) {
      try { localStorage.setItem(key, JSON.stringify({ ts: Date.now(), data: data })); } catch(e) {}
    },
    // ── Shared AI result renderer ─────────────────────────────────────────────
    renderAiResult: function(resultEl, data) {
      if (!resultEl) { return; }
      var html = '';
      if (data.analysis) {
        html += '<span class="ai-section">Analysis</span>' + __dd.escHtml(data.analysis) + '\n\n';
      }
      if (data.root_cause) {
        html += '<span class="ai-section">Root Cause</span>' + __dd.escHtml(data.root_cause) + '\n\n';
      }
      var text = (data.suggestion || '');
      html += text
        .replace(/^PROBLEM:/mg, '<span class="ai-section">Problem</span>')
        .replace(/^FIX:/mg, '<span class="ai-section">Fix</span>')
        .replace(/^WHY:/mg, '<span class="ai-section">Why</span>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');
      if (data.file_path && data.old_code && data.new_code) {
        html += '\n\n<div style="margin-top:6px;padding:6px;background:rgba(80,250,123,.06);border:1px solid rgba(80,250,123,.2);border-radius:3px;">'
          + '<span style="color:#50fa7b;font-size:9px;font-weight:700;">● FILE CHANGE READY</span> '
          + '<span style="color:#6272a4;font-size:10px;">' + __dd.escHtml(data.file_path) + '</span>'
          + '</div>';
        resultEl.dataset.filePath = data.file_path;
        resultEl.dataset.oldCode = data.old_code;
        resultEl.dataset.newCode = data.new_code;
        html += '\n<button class="q-ai-btn ai" style="margin-top:4px;" onclick="__dd.applyFix(this)">'
          + '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>'
          + ' Apply Fix</button>';
      }
      resultEl.className = 'q-ai-result';
      resultEl.innerHTML = html;
      resultEl.style.display = 'block';
    },
    // ── Pre-populate cached results on page load ──────────────────────────────
    initAiCache: function() {
      // Query buttons
      document.querySelectorAll('.q-ai-btn.ai[data-sql]').forEach(function(btn) {
        var key = __dd.aiCacheKey('q', (btn.getAttribute('data-type') || '') + ':' + (btn.getAttribute('data-sql') || ''));
        var cached = __dd.aiCacheGet(key);
        if (!cached) { return; }
        var resultEl = document.getElementById(btn.getAttribute('data-result') || '');
        __dd.renderAiResult(resultEl, cached);
        btn.disabled = true;
        btn.innerHTML = '✓ Cached';
        btn.title = 'Result loaded from cache (24h). Click again to refresh.';
        btn.onclick = function() {
          btn.disabled = false;
          btn.onclick = function() { __dd.aiSuggestFromEl(btn); };
          __dd.aiSuggestFromEl(btn);
        };
      });
      // Exception button
      var excBtn = document.querySelector('[data-exc-class]');
      if (excBtn) {
        var excKey = __dd.aiCacheKey('exc',
          (excBtn.getAttribute('data-exc-class') || '') + ':' +
          (excBtn.getAttribute('data-exc-file') || '') + ':' +
          (excBtn.getAttribute('data-exc-line') || '')
        );
        var excCached = __dd.aiCacheGet(excKey);
        if (excCached) {
          __dd.renderAiResult(document.getElementById('__dd_exc_ai_result__'), excCached);
          excBtn.disabled = true;
          excBtn.innerHTML = '✓ Investigated (cached)';
          excBtn.title = 'Result loaded from cache (24h). Click again to refresh.';
          excBtn.onclick = function() {
            excBtn.disabled = false;
            excBtn.onclick = function() { __dd.investigateExceptionFromEl(excBtn); };
            __dd.investigateExceptionFromEl(excBtn);
          };
        }
      }
    },
    applyFix: function(btn) {
      var resultEl = btn.closest('.q-ai-result');
      if (!resultEl) { return; }
      var filePath = resultEl.dataset.filePath;
      var oldCode  = resultEl.dataset.oldCode;
      var newCode  = resultEl.dataset.newCode;
      if (!filePath || !oldCode || !newCode) { return; }
      btn.disabled = true;
      btn.textContent = 'Applying…';
      fetch('/digdeep/api/ai-apply', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '' },
        body: JSON.stringify({ file_path: filePath, old_code: oldCode, new_code: newCode })
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.status === 'ok') {
          btn.innerHTML = '✓ Applied!';
          btn.style.background = 'rgba(80,250,123,.15)';
          btn.style.color = '#50fa7b';
          btn.disabled = true;
        } else {
          btn.disabled = false;
          btn.textContent = 'Apply Fix';
          var errEl = document.createElement('div');
          errEl.style.color = '#ff5555';
          errEl.style.fontSize = '10px';
          errEl.style.marginTop = '4px';
          errEl.textContent = data.error || 'Apply failed.';
          btn.after(errEl);
        }
      })
      .catch(function(e) {
        btn.disabled = false;
        btn.textContent = 'Apply Fix';
      });
    },
    investigateExceptionFromEl: function(btn) {
      var excClass = btn.getAttribute('data-exc-class') || '';
      var excFile  = btn.getAttribute('data-exc-file') || '';
      var excLine  = btn.getAttribute('data-exc-line') || '';
      var cacheKey = __dd.aiCacheKey('exc', excClass + ':' + excFile + ':' + excLine);
      var resultEl = document.getElementById('__dd_exc_ai_result__');
      var cached   = __dd.aiCacheGet(cacheKey);
      if (cached) {
        __dd.renderAiResult(resultEl, cached);
        btn.disabled = true;
        btn.innerHTML = '✓ Investigated (cached)';
        btn.title = 'Loaded from cache. Click again to refresh.';
        btn.onclick = function() { btn.disabled = false; btn.onclick = function() { __dd.investigateExceptionFromEl(btn); }; __dd.investigateExceptionFromEl(btn); };
        return;
      }
      btn.disabled = true;
      btn.textContent = 'Investigating…';
      if (resultEl) { resultEl.style.display = 'none'; resultEl.innerHTML = ''; }
      var trace = [];
      try { trace = JSON.parse(btn.getAttribute('data-exc-trace') || '[]'); } catch(e) {}
      fetch('/digdeep/api/ai-investigate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '' },
        body: JSON.stringify({ class: excClass, message: btn.getAttribute('data-exc-message'), file: excFile, line: parseInt(excLine || '0'), trace: trace })
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg> ✓ Investigated';
        if (data.error) {
          if (resultEl) { resultEl.className = 'q-ai-result'; resultEl.style.color = '#ff5555'; resultEl.textContent = data.error; resultEl.style.display = 'block'; }
          return;
        }
        __dd.aiCacheSet(cacheKey, data);
        __dd.renderAiResult(resultEl, data);
      })
      .catch(function(e) {
        btn.disabled = false;
        btn.textContent = 'Investigate with AI';
        if (resultEl) { resultEl.className = 'q-ai-result'; resultEl.style.color = '#ff5555'; resultEl.textContent = 'Request failed: ' + e.message; resultEl.style.display = 'block'; }
      });
    },
    aiSuggestFromEl: function(btn) {
      var sql    = btn.getAttribute('data-sql') || '';
      var issue  = btn.getAttribute('data-type') || '';
      var cacheKey = __dd.aiCacheKey('q', issue + ':' + sql);
      var cached = __dd.aiCacheGet(cacheKey);
      if (cached) {
        __dd.renderAiResult(document.getElementById(btn.getAttribute('data-result') || ''), cached);
        btn.disabled = true;
        btn.innerHTML = '✓ Cached';
        btn.title = 'Loaded from cache. Click again to refresh.';
        btn.onclick = function() { btn.disabled = false; btn.onclick = function() { __dd.aiSuggestFromEl(btn); }; __dd.aiSuggestFromEl(btn); };
        return;
      }
      __dd.aiSuggest(btn, btn.getAttribute('data-result'), sql, issue, btn.getAttribute('data-caller') || '', parseFloat(btn.getAttribute('data-time') || '0'), cacheKey);
    },
    copyPromptFromEl: function(btn) {
      __dd.copyPrompt(btn.getAttribute('data-prompt'), btn);
    },
    aiSuggest: function(btn, resultId, sql, issue, caller, timeMs, cacheKey) {
      btn.disabled = true;
      btn.textContent = 'Thinking…';
      var resultEl = document.getElementById(resultId);
      if (resultEl) { resultEl.style.display = 'none'; resultEl.innerHTML = ''; }
      fetch('/digdeep/api/ai-suggest', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '' },
        body: JSON.stringify({ sql: sql, issue: issue, caller: caller, time_ms: timeMs })
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '✓ AI Fix';
        if (data.error) {
          if (resultEl) { resultEl.className = 'q-ai-result'; resultEl.style.color = '#ff5555'; resultEl.textContent = data.error; resultEl.style.display = 'block'; }
          return;
        }
        if (cacheKey) { __dd.aiCacheSet(cacheKey, data); }
        __dd.renderAiResult(resultEl, data);
      })
      .catch(function(e) {
        btn.disabled = false;
        btn.textContent = 'Fix with AI';
        if (resultEl) { resultEl.className = 'q-ai-result'; resultEl.style.color = '#ff5555'; resultEl.textContent = 'Request failed: ' + e.message; resultEl.style.display = 'block'; }
      });
    },
    aiFixAll: function() {
      // Click all unfired "Fix with AI" buttons in the AI panel sequentially
      var btns = document.querySelectorAll('#__ddp_ai__ .q-ai-btn.ai');
      var delay = 0;
      btns.forEach(function(btn) {
        if (!btn.disabled && btn.textContent.indexOf('✓') === -1) {
          setTimeout(function() { btn.click(); }, delay);
          delay += 800; // stagger requests
        }
      });
    },
    filterQueries: function(text) {
      text = text.toLowerCase().trim();
      document.querySelectorAll('#__ddp_queries__ tbody tr[data-sql]').forEach(function(row) {
        var sql = row.getAttribute('data-sql') || '';
        row.style.display = text === '' || sql.includes(text) ? '' : 'none';
      });
      // Always show expand rows (no data-sql) for visible parents
      document.querySelectorAll('#__ddp_queries__ tbody tr:not([data-sql])').forEach(function(row) {
        row.style.display = '';
      });
    },
    copyPrompt: function(prompt, btn) {
      navigator.clipboard.writeText(prompt).then(function() {
        var orig = btn.innerHTML;
        btn.textContent = '✓ Copied!';
        btn.style.color = '#50fa7b';
        setTimeout(function() { btn.innerHTML = orig; btn.style.color = ''; }, 2000);
      }).catch(function() {
        // Fallback
        var ta = document.createElement('textarea');
        ta.value = prompt; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
        var orig = btn.innerHTML;
        btn.textContent = '✓ Copied!';
        setTimeout(function() { btn.innerHTML = orig; }, 2000);
      });
    }
  };

  // Restore cached AI results on page load
  __dd.initAiCache();

  // Keyboard shortcuts
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && activePanel) { window.__dd.closePanel(); }
    // Ctrl/Cmd+Shift+D → toggle debugbar
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
      e.preventDefault();
      if (bar.style.display === 'none') { window.__dd.restore(); }
      else { window.__dd.hide(); }
    }
  });

  // Resize handle
  (function() {
    var handle = document.getElementById('__dd_resize__');
    var inner  = document.getElementById('__digdeep_panels_inner__');
    if (!handle) { return; }
    var resizing = false, startY = 0, startH = 380;
    handle.addEventListener('mousedown', function(e) {
      resizing = true;
      startY = e.clientY;
      startH = panels.offsetHeight;
      handle.classList.add('active');
      document.body.style.userSelect = 'none';
      e.preventDefault();
    });
    document.addEventListener('mousemove', function(e) {
      if (!resizing) { return; }
      var newH = Math.min(800, Math.max(120, startH + startY - e.clientY));
      panels.style.maxHeight = newH + 'px';
      panels.style.height = newH + 'px';
      if (inner) { inner.style.maxHeight = (newH - 5) + 'px'; inner.style.height = (newH - 5) + 'px'; }
    });
    document.addEventListener('mouseup', function() {
      if (!resizing) { return; }
      resizing = false;
      handle.classList.remove('active');
      document.body.style.userSelect = '';
      try { localStorage.setItem(LS_HEIGHT_KEY, String(panels.offsetHeight)); } catch(e2) {}
    });
  })();

  // AJAX / Inertia navigation tracking
  var ajaxBtn    = document.getElementById('__ddm_ajax__');
  var ajaxCount  = document.getElementById('__dd_ajax_count__');
  var ajaxEmpty  = document.getElementById('__dd_ajax_empty__');
  var ajaxSplit  = document.getElementById('__dd_ajax_split__');
  var ajaxList   = document.getElementById('__dd_ajax_list__');
  var ajaxDetail = document.getElementById('__dd_ajax_detail__');
  var ajaxItems  = [];
  var ajaxN      = 0;
  var ajaxSelected = null;
  var methodColors = { GET:'#50fa7b', POST:'#8be9fd', PUT:'#ffb86c', PATCH:'#ffb86c', DELETE:'#ff5555' };

  function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function renderAjaxDetail(item) {
    var sColor  = item.status >= 500 ? '#ff5555' : item.status >= 400 ? '#ffb86c' : '#50fa7b';
    var msColor = item.ms > 1000 ? '#ff5555' : item.ms > 500 ? '#ffb86c' : '#50fa7b';
    var mColor  = methodColors[item.method] || '#bd93f9';
    var html = '<div style="margin-bottom:10px;">'
      + '<span style="color:' + mColor + ';font-weight:700;font-size:12px;">' + item.method + '</span>'
      + '<span style="color:#6272a4;margin:0 6px;">·</span>'
      + '<span style="color:#f8f8f2;font-size:11px;word-break:break-all;">' + escHtml(item.url) + '</span>'
      + '</div>'
      + '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">'
      + '<span style="background:rgba(0,0,0,.2);color:' + sColor + ';font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;">' + item.status + '</span>'
      + '<span style="color:' + msColor + ';font-size:11px;font-weight:600;">' + item.ms.toFixed(0) + 'ms</span>'
      + (item.type ? '<span style="background:rgba(189,147,249,.1);color:#bd93f9;font-size:10px;padding:2px 7px;border-radius:3px;">' + escHtml(item.type) + '</span>' : '')
      + '</div>';
    if (item.component) {
      html += '<div style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #44475a;">'
        + '<div style="color:#6272a4;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-bottom:4px;">Vue Component</div>'
        + '<div style="color:#50fa7b;font-size:12px;font-weight:600;">' + escHtml(item.component) + '</div>'
        + '</div>';
    }
    // Queries section
    if (item.profile && item.profile.queries && item.profile.queries.length) {
      var qs = item.profile.queries;
      var qTime = item.profile.performance ? item.profile.performance.query_time_ms : 0;
      html += '<div style="color:#6272a4;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-bottom:6px;">'
        + 'Queries (' + qs.length + ' · ' + qTime.toFixed(1) + 'ms)</div>'
        + '<table style="width:100%;border-collapse:collapse;font-size:10.5px;margin-bottom:12px;">'
        + '<thead><tr>'
        + '<th style="color:#6272a4;font-size:9px;text-align:left;padding:3px 6px;border-bottom:1px solid #44475a;">#</th>'
        + '<th style="color:#6272a4;font-size:9px;text-align:left;padding:3px 6px;border-bottom:1px solid #44475a;">Query</th>'
        + '<th style="color:#6272a4;font-size:9px;text-align:right;padding:3px 6px;border-bottom:1px solid #44475a;">ms</th>'
        + '</tr></thead><tbody>';
      qs.forEach(function(q, qi) {
        var qMs = q.time_ms || 0;
        var qColor = qMs >= 100 ? '#ff5555' : qMs >= 50 ? '#ffb86c' : '#6272a4';
        var hasStar = q.sql && q.sql.toUpperCase().indexOf('SELECT *') !== -1;
        html += '<tr style="border-bottom:1px solid rgba(68,71,90,.3);">'
          + '<td style="color:#6272a4;padding:4px 6px;text-align:right;">' + (qi+1) + '</td>'
          + '<td style="padding:4px 6px;color:#f8f8f2;word-break:break-all;max-width:340px;">'
          +   escHtml(q.sql || '')
          +   (hasStar ? '<span style="display:inline-block;margin-left:4px;padding:0 3px;font-size:9px;font-weight:700;background:rgba(241,250,140,.15);color:#f1fa8c;border-radius:2px;">SELECT *</span>' : '')
          + '</td>'
          + '<td style="color:' + qColor + ';text-align:right;padding:4px 6px;white-space:nowrap;">' + qMs.toFixed(2) + '</td>'
          + '</tr>';
      });
      html += '</tbody></table>';
    } else if (item.profile) {
      html += '<div style="color:#6272a4;font-size:11px;margin-bottom:12px;">No queries recorded for this request.</div>';
    } else {
      html += '<div style="color:#44475a;font-size:11px;font-style:italic;margin-bottom:12px;">Fetching profile data…</div>';
    }
    if (item.props && Object.keys(item.props).length) {
      html += '<div style="color:#6272a4;font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-bottom:6px;border-top:1px solid #44475a;padding-top:10px;">Props</div>'
        + '<table style="width:100%;border-collapse:collapse;font-size:10.5px;">'
        + '<thead><tr>'
        + '<th style="color:#6272a4;font-size:9px;text-align:left;padding:3px 6px;border-bottom:1px solid #44475a;">Key</th>'
        + '<th style="color:#6272a4;font-size:9px;text-align:left;padding:3px 6px;border-bottom:1px solid #44475a;">Value</th>'
        + '</tr></thead><tbody>';
      Object.keys(item.props).forEach(function(k) {
        var v = item.props[k];
        var preview = v === null ? '<span style="color:#6272a4;">null</span>'
          : typeof v === 'boolean' ? '<span style="color:#bd93f9;">' + v + '</span>'
          : typeof v === 'number' ? '<span style="color:#f1fa8c;">' + v + '</span>'
          : typeof v === 'string' ? '<span style="color:#f8f8f2;">"' + escHtml(v.substring(0, 80)) + (v.length > 80 ? '…' : '') + '"</span>'
          : Array.isArray(v) ? '<span style="color:#6272a4;">[' + v.length + ' items]</span>'
          : '<span style="color:#6272a4;">{' + Object.keys(v).length + ' keys}</span>';
        html += '<tr><td style="color:#8be9fd;padding:4px 6px;border-bottom:1px solid rgba(68,71,90,.3);white-space:nowrap;">' + escHtml(k) + '</td>'
          + '<td style="padding:4px 6px;border-bottom:1px solid rgba(68,71,90,.3);">' + preview + '</td></tr>';
      });
      html += '</tbody></table>';
    }
    ajaxDetail.innerHTML = html;
  }

  function addAjaxEntry(method, url, status, ms, data) {
    ajaxN++;
    ajaxCount.textContent = ajaxN;
    ajaxBtn.style.display = 'flex';
    panelLabels['ajax'] = 'AJAX / Inertia Navigation (' + ajaxN + ')';
    if (ajaxEmpty) { ajaxEmpty.style.display = 'none'; }
    if (ajaxSplit) { ajaxSplit.style.display = 'flex'; }

    var item = Object.assign({ method: method, url: url, status: status, ms: ms, index: ajaxN }, data || {});
    ajaxItems.push(item);

    var color   = methodColors[method] || '#bd93f9';
    var sColor  = status >= 500 ? '#ff5555' : status >= 400 ? '#ffb86c' : '#50fa7b';
    var msColor = ms > 1000 ? '#ff5555' : ms > 500 ? '#ffb86c' : '#50fa7b';

    var row = document.createElement('div');
    row.dataset.idx = String(ajaxN - 1);
    row.style.cssText = 'display:grid;grid-template-columns:44px 1fr 44px 52px;align-items:center;gap:6px;padding:6px 10px;border-bottom:1px solid rgba(68,71,90,.35);font-size:11px;cursor:pointer;transition:background .1s;';
    row.innerHTML =
      '<span style="color:' + color + ';font-weight:700;font-size:10px;">' + method + '</span>'
      + '<span style="overflow:hidden;font-size:10.5px;" title="' + escHtml(url) + '">'
      +   (item.component ? '<span style="color:#50fa7b;display:block;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(item.component) + '</span>' : '')
      +   '<span style="color:#6272a4;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(url) + '</span>'
      +   '<span class="__dd_q_badge__" style="display:none;font-size:9px;color:#8be9fd;margin-top:1px;"></span>'
      + '</span>'
      + '<span style="color:' + sColor + ';text-align:right;font-weight:600;">' + status + '</span>'
      + '<span style="color:' + msColor + ';text-align:right;">' + ms.toFixed(0) + 'ms</span>';

    row.addEventListener('mouseenter', function() {
      if (ajaxSelected !== row) { row.style.background = 'rgba(68,71,90,.2)'; }
    });
    row.addEventListener('mouseleave', function() {
      if (ajaxSelected !== row) { row.style.background = ''; }
    });
    row.addEventListener('click', function() {
      if (ajaxSelected) { ajaxSelected.style.background = ''; }
      ajaxSelected = row;
      row.style.background = 'rgba(189,147,249,.12)';
      renderAjaxDetail(ajaxItems[parseInt(row.dataset.idx)]);
    });

    ajaxList.appendChild(row);

    // Fetch profile data for this request (slight delay so server has stored it)
    setTimeout(function() { fetchAndAttachProfile(item, row); }, 300);

    // Auto-select latest entry
    row.click();
  }

  function fetchAndAttachProfile(item, rowEl) {
    // Fetch the latest profile for this URL from the DigDeep API
    fetch('/digdeep/api/profiles?route=' + encodeURIComponent(item.url) + '&per_page=1', {
      headers: { 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(data) {
      var profiles = data.profiles || [];
      if (profiles.length) {
        item.profile = profiles[0];
        var qCount = (item.profile.queries || []).length;
        // Update row query count badge
        var badge = rowEl.querySelector('.__dd_q_badge__');
        if (badge && qCount > 0) {
          badge.textContent = qCount + 'q';
          badge.style.display = 'inline';
        }
        // Re-render detail if this item is selected
        if (ajaxSelected === rowEl) { renderAjaxDetail(item); }
      }
    }).catch(function() {});
  }

  // Inertia v2 events
  document.addEventListener('inertia:start', function(e) {
    var v = e.detail && e.detail.visit;
    if (v) { v.__dd_start = performance.now(); }
  });

  document.addEventListener('inertia:finish', function(e) {
    var v = e.detail && e.detail.visit;
    if (!v) { return; }
    var ms = v.__dd_start ? (performance.now() - v.__dd_start) : 0;
    var pageData = {};
    try {
      var appEl = document.getElementById('app');
      if (appEl && appEl.__vue_app__) {
        var page = appEl.__vue_app__.config.globalProperties.$page;
        if (page) { pageData = { component: page.component, props: page.props }; }
      }
    } catch(e2) {}
    addAjaxEntry(
      (v.method || 'GET').toUpperCase(),
      v.url ? (typeof v.url === 'object' ? v.url.href : v.url) : window.location.pathname,
      v.completed ? (v.response ? v.response.status : 200) : (v.failed ? 500 : 0),
      ms,
      Object.assign({ type: 'Inertia' }, pageData)
    );
  });

  // Fallback: intercept fetch for non-Inertia AJAX
  (function() {
    var origFetch = window.fetch;
    window.fetch = function(input, init) {
      var url = typeof input === 'string' ? input : (input.url || '');
      if (url.indexOf('/digdeep') !== -1) { return origFetch.apply(this, arguments); }
      var method = (init && init.method || 'GET').toUpperCase();
      var headers = {};
      if (init && init.headers) {
        var h = init.headers;
        if (h['X-Inertia'] || (h.get && h.get('X-Inertia'))) { return origFetch.apply(this, arguments); }
        try { if (typeof h.forEach === 'function') { h.forEach(function(v,k){ headers[k]=v; }); } else { headers = Object.assign({}, h); } } catch(e2) {}
      }
      var t = performance.now();
      return origFetch.apply(this, arguments).then(function(res) {
        addAjaxEntry(method, url, res.status, performance.now() - t, { type: 'fetch', requestHeaders: headers });
        return res;
      });
    };
  })();
})();
</script>
</div>
