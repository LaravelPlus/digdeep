<?php

namespace LaravelPlus\DigDeep\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LaravelPlus\DigDeep\DigDeepCollector;
use LaravelPlus\DigDeep\Models\DigDeepProfile;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiController extends Controller
{
    public function __construct(
        private DigDeepStorage $storage,
        private DigDeepCollector $collector,
    ) {}

    public function trigger(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['nullable', 'string', 'max:2000'],
            'method' => ['nullable', 'string', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],
        ]);

        $url = trim($request->input('url', '/'));

        // Strip scheme + host so full URLs like http://127.0.0.1:8000/foo become /foo
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            $url = ($parsed['path'] ?? '/').
                (isset($parsed['query']) ? '?'.$parsed['query'] : '').
                (isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');
        }

        if (! str_starts_with($url, '/')) {
            $url = '/'.$url;
        }

        $method = strtoupper($request->input('method', 'GET'));

        $subRequest = Request::create($url, $method);
        $subRequest->headers->set('X-DigDeep-Profile', '1');

        $this->collector->startRequest();

        $this->collector->setRequest(
            $method,
            $url,
        );

        try {
            $response = app()->handle($subRequest);
        } catch (\Throwable $e) {
            $this->collector->setException($e);
            $this->collector->setResponse(500, [], 0);

            $profileData = $this->collector->finishRequest();
            $profileId = Str::uuid()->toString();
            $this->storage->store($profileId, $profileData);

            $performance = $profileData['performance'] ?? [];

            return response()->json([
                'profile_id' => $profileId,
                'redirect' => '/digdeep/profile/'.$profileId,
                'status_code' => 500,
                'duration_ms' => round($performance['duration_ms'] ?? 0, 1),
                'query_count' => $performance['query_count'] ?? 0,
                'memory_peak_mb' => round($performance['memory_peak_mb'] ?? 0, 1),
            ]);
        }

        $route = $subRequest->route();

        if ($route) {
            $this->collector->setRoute([
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'parameters' => $route->parameters(),
                'middleware' => $route->gatherMiddleware(),
            ]);
        }

        $this->collector->setResponse(
            $response->getStatusCode(),
            $this->sanitizeHeaders($response->headers->all()),
            strlen($response->getContent() ?: ''),
        );

        // Capture exception if response indicates an error
        if ($response->getStatusCode() >= 400 && $response->exception ?? null) {
            $this->collector->setException($response->exception);
        }

        // Collect Inertia data from the response
        $this->collector->collectInertia($response);

        $profileData = $this->collector->finishRequest();

        $profileId = Str::uuid()->toString();
        $this->storage->store($profileId, $profileData);

        $performance = $profileData['performance'] ?? [];

        return response()->json([
            'profile_id' => $profileId,
            'redirect' => '/digdeep/profile/'.$profileId,
            'status_code' => $profileData['response']['status_code'] ?? 0,
            'duration_ms' => round($performance['duration_ms'] ?? 0, 1),
            'query_count' => $performance['query_count'] ?? 0,
            'memory_peak_mb' => round($performance['memory_peak_mb'] ?? 0, 1),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->storage->delete($id);

        return response()->json(['status' => 'ok']);
    }

    public function clear(): JsonResponse
    {
        $this->storage->clear();

        return response()->json(['status' => 'ok']);
    }

    public function export(string $id): StreamedResponse|JsonResponse
    {
        $profile = $this->storage->find($id);

        if (! $profile) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $filename = "digdeep-{$profile['method']}-".Str::slug($profile['url'])."-{$id}.json";

        return response()->streamDownload(function () use ($profile) {
            echo json_encode([
                'version' => '1.0',
                'generator' => 'DigDeep Laravel Profiler',
                'exported_at' => now()->toIso8601String(),
                'profile' => [
                    'id' => $profile['id'],
                    'method' => $profile['method'],
                    'url' => $profile['url'],
                    'status_code' => $profile['status_code'],
                    'duration_ms' => $profile['duration_ms'],
                    'memory_peak_mb' => $profile['memory_peak_mb'],
                    'query_count' => $profile['query_count'],
                    'created_at' => $profile['created_at'],
                    'tags' => $profile['tags'] ?? '',
                    'notes' => $profile['notes'] ?? '',
                    'data' => $profile['data'],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function replay(string $id): JsonResponse
    {
        $profile = $this->storage->find($id);

        if (! $profile) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $method = $profile['method'];
        $url = $profile['url'];

        // Re-trigger the same request
        $subRequest = Request::create($url, $method);
        $subRequest->headers->set('X-DigDeep-Profile', '1');

        $this->collector->startRequest();
        $this->collector->setRequest($method, $url);

        try {
            $response = app()->handle($subRequest);
        } catch (\Throwable $e) {
            $this->collector->setException($e);
            $this->collector->setResponse(500, [], 0);

            $profileData = $this->collector->finishRequest();
            $newId = Str::uuid()->toString();
            $this->storage->store($newId, $profileData);

            return response()->json([
                'profile_id' => $newId,
                'original_id' => $id,
                'redirect' => '/digdeep/compare?a='.$id.'&b='.$newId,
            ]);
        }

        $route = $subRequest->route();
        if ($route) {
            $this->collector->setRoute([
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'parameters' => $route->parameters(),
                'middleware' => $route->gatherMiddleware(),
            ]);
        }

        $this->collector->setResponse(
            $response->getStatusCode(),
            $this->sanitizeHeaders($response->headers->all()),
            strlen($response->getContent() ?: ''),
        );

        if ($response->getStatusCode() >= 400 && $response->exception ?? null) {
            $this->collector->setException($response->exception);
        }

        $this->collector->collectInertia($response);

        $profileData = $this->collector->finishRequest();
        $newId = Str::uuid()->toString();
        $this->storage->store($newId, $profileData);

        return response()->json([
            'profile_id' => $newId,
            'original_id' => $id,
            'redirect' => '/digdeep/compare?a='.$id.'&b='.$newId,
        ]);
    }

    public function updateTags(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $tags = strip_tags($request->input('tags', ''));
        $this->storage->updateTags($id, $tags);

        return response()->json(['status' => 'ok']);
    }

    public function updateNotes(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $notes = strip_tags($request->input('notes', ''));
        $this->storage->updateNotes($id, $notes);

        return response()->json(['status' => 'ok']);
    }

    public function explain(Request $request): JsonResponse
    {
        $sql = $request->input('sql', '');

        if (empty($sql)) {
            return response()->json(['error' => 'No SQL provided'], 400);
        }

        $trimmed = trim($sql);

        if (strlen($trimmed) > 10000) {
            return response()->json(['error' => 'SQL query exceeds maximum length'], 400);
        }

        // Only allow EXPLAIN on SELECT queries
        if (! preg_match('/^\s*SELECT\b/i', $trimmed)) {
            return response()->json(['error' => 'EXPLAIN only supports SELECT queries'], 400);
        }

        // Reject queries containing dangerous keywords
        $dangerous = '/\b(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|EXEC|EXECUTE|INTO\s+(?:OUTFILE|DUMPFILE|TABLE))\b/i';
        if (preg_match($dangerous, $trimmed)) {
            return response()->json(['error' => 'SQL query contains disallowed keywords'], 400);
        }

        // Cache EXPLAIN plans for 5 minutes to avoid repeated queries
        $cacheKey = 'digdeep:explain:'.md5($trimmed);
        $cached = cache()->get($cacheKey);

        if ($cached !== null) {
            return response()->json(['plan' => $cached, 'cached' => true]);
        }

        try {
            $results = DB::select('EXPLAIN QUERY PLAN '.$trimmed);
            $plan = array_map(fn ($row) => (array) $row, $results);

            cache()->put($cacheKey, $plan, 300);

            return response()->json(['plan' => $plan]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function profiles(Request $request): JsonResponse
    {
        $after = $request->input('after');
        $perPage = min((int) $request->input('per_page', 50), 200);
        $page = max((int) $request->input('page', 1), 1);

        // Build filter criteria from request
        $criteria = array_filter([
            'status_min' => $request->input('status_min') ? (int) $request->input('status_min') : null,
            'status_max' => $request->input('status_max') ? (int) $request->input('status_max') : null,
            'duration_min' => $request->input('duration_min') ? (float) $request->input('duration_min') : null,
            'duration_max' => $request->input('duration_max') ? (float) $request->input('duration_max') : null,
            'tag' => $request->input('tag'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'route' => $request->input('route'),
            'has_errors' => $request->boolean('has_errors') ?: null,
            'method' => $request->input('method'),
        ], fn ($v) => $v !== null);

        $profiles = ! empty($criteria) ? $this->storage->filter($criteria) : $this->storage->all();

        if ($after) {
            $profiles = array_values(array_filter($profiles, fn ($p) => $p['created_at'] > $after));
        }

        $total = count($profiles);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($profiles, $offset, $perPage);

        return response()->json([
            'profiles' => $paged,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => ($offset + $perPage) < $total,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $route = $request->input('route', '');
        $range = $request->input('range', 'all');

        $query = DigDeepProfile::query()->latest();

        if ($route) {
            $query->where('url', $route);
        }

        // Apply time range
        if ($range === 'hour') {
            $query->where('created_at', '>=', now()->subHour());
        } elseif ($range === 'day') {
            $query->where('created_at', '>=', now()->subDay());
        } elseif ($range === 'week') {
            $query->where('created_at', '>=', now()->subWeek());
        }

        $profiles = $query->get(['id', 'url', 'duration_ms', 'memory_peak_mb', 'query_count', 'query_time_ms', 'created_at']);

        $series = $profiles->map(fn ($p) => [
            'id' => $p->id,
            'duration_ms' => $p->duration_ms,
            'memory_peak_mb' => $p->memory_peak_mb,
            'query_count' => $p->query_count,
            'query_time_ms' => $p->query_time_ms,
            'created_at' => $p->created_at->toDateTimeString(),
        ])->values()->all();

        // Compute stats
        $durations = array_column($series, 'duration_ms');
        $memories = array_column($series, 'memory_peak_mb');
        $queryCounts = array_column($series, 'query_count');

        $stats = [];
        if (count($durations) > 0) {
            sort($durations);
            $p95Idx = (int) ceil(count($durations) * 0.95) - 1;
            $stats = [
                'avg_duration' => round(array_sum($durations) / count($durations), 1),
                'p95_duration' => round($durations[max(0, $p95Idx)], 1),
                'min_duration' => round(min($durations), 1),
                'max_duration' => round(max($durations), 1),
                'avg_memory' => count($memories) > 0 ? round(array_sum($memories) / count($memories), 1) : 0,
                'avg_queries' => count($queryCounts) > 0 ? round(array_sum($queryCounts) / count($queryCounts), 1) : 0,
                'count' => count($series),
            ];
        }

        // Get distinct routes for the selector
        $routes = DigDeepProfile::query()
            ->select('url')
            ->distinct()
            ->orderBy('url')
            ->pluck('url')
            ->all();

        return response()->json([
            'series' => $series,
            'stats' => $stats,
            'routes' => $routes,
        ]);
    }

    public function performanceData(Request $request): JsonResponse
    {
        $range = $request->input('range', 'all');

        $query = DigDeepProfile::query()->latest();

        if ($range === 'hour') {
            $query->where('created_at', '>=', now()->subHour());
        } elseif ($range === 'day') {
            $query->where('created_at', '>=', now()->subDay());
        } elseif ($range === 'week') {
            $query->where('created_at', '>=', now()->subWeek());
        }

        $profiles = $query->get(['id', 'method', 'url', 'status_code', 'duration_ms', 'memory_peak_mb', 'query_count', 'created_at']);

        $routeMap = [];
        $allDurations = [];
        $totalErrors = 0;

        foreach ($profiles as $p) {
            $key = $p->method.' '.$p->url;
            $duration = (float) $p->duration_ms;
            $status = (int) $p->status_code;
            $allDurations[] = $duration;

            if ($status >= 400) {
                $totalErrors++;
            }

            if (! isset($routeMap[$key])) {
                $routeMap[$key] = [
                    'method' => $p->method,
                    'url' => $p->url,
                    'durations' => [],
                    'statuses' => [],
                    'queries' => [],
                    'memories' => [],
                    'timestamps' => [],
                ];
            }

            $routeMap[$key]['durations'][] = $duration;
            $routeMap[$key]['statuses'][] = $status;
            $routeMap[$key]['queries'][] = (int) $p->query_count;
            $routeMap[$key]['memories'][] = (float) $p->memory_peak_mb;
            $routeMap[$key]['timestamps'][] = $p->created_at->toDateTimeString();
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

        $global = [];
        if (count($allDurations) > 0) {
            sort($allDurations);

            $allTimestamps = $profiles->pluck('created_at')->map(fn ($t) => $t->toDateTimeString())->sort()->values()->all();
            $first = strtotime($allTimestamps[0]);
            $last = strtotime(end($allTimestamps));
            $spanMinutes = max(($last - $first) / 60, 1);

            $global = [
                'total' => count($allDurations),
                'p50' => $this->percentile($allDurations, 0.50),
                'p95' => $this->percentile($allDurations, 0.95),
                'p99' => $this->percentile($allDurations, 0.99),
                'throughput_rpm' => round(count($allDurations) / $spanMinutes, 1),
                'error_rate' => round($totalErrors / count($allDurations) * 100, 1),
            ];
        }

        return response()->json([
            'routes' => $routes,
            'global' => $global,
        ]);
    }

    public function bulkExport(Request $request): StreamedResponse|JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['error' => 'No profile IDs provided'], 400);
        }

        $profiles = [];
        foreach ($ids as $id) {
            $profile = $this->storage->find($id);
            if ($profile) {
                $profiles[] = $profile;
            }
        }

        if (empty($profiles)) {
            return response()->json(['error' => 'No profiles found'], 404);
        }

        $filename = 'digdeep-export-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(function () use ($profiles) {
            echo json_encode([
                'version' => '1.0',
                'generator' => 'DigDeep Laravel Profiler',
                'exported_at' => now()->toIso8601String(),
                'count' => count($profiles),
                'profiles' => $profiles,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $action = $request->input('action');
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['error' => 'No profile IDs provided'], 400);
        }

        if ($action === 'delete') {
            $count = $this->storage->bulkDelete($ids);

            return response()->json(['status' => 'ok', 'deleted' => $count]);
        }

        if ($action === 'tag') {
            $tag = strip_tags(trim($request->input('tag', '')));
            if (empty($tag) || strlen($tag) > 500) {
                return response()->json(['error' => 'Tag must be between 1 and 500 characters'], 400);
            }

            $count = $this->storage->bulkTag($ids, $tag);

            return response()->json(['status' => 'ok', 'tagged' => $count]);
        }

        return response()->json(['error' => 'Unknown action'], 400);
    }

    public function compareData(Request $request): JsonResponse
    {
        $idA = $request->input('a');
        $idB = $request->input('b');

        $profileA = $idA ? $this->storage->find($idA) : null;
        $profileB = $idB ? $this->storage->find($idB) : null;

        return response()->json([
            'a' => $profileA,
            'b' => $profileB,
        ]);
    }

    private function percentile(array $sorted, float $p): float
    {
        if (empty($sorted)) {
            return 0.0;
        }

        $idx = (int) ceil(count($sorted) * $p) - 1;

        return round($sorted[max(0, $idx)], 1);
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'set-cookie'];

        foreach ($sensitive as $key) {
            if (isset($headers[$key])) {
                $headers[$key] = ['[redacted]'];
            }
        }

        return $headers;
    }
}
