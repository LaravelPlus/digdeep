<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LaravelPlus\DigDeep\DigDeepCollector;
use LaravelPlus\DigDeep\Models\DigDeepProfile;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ApiController extends Controller
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

        $url = mb_trim($request->input('url', '/'));

        // Strip scheme + host so full URLs like http://127.0.0.1:8000/foo become /foo
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            $url = ($parsed['path'] ?? '/').
                (isset($parsed['query']) ? '?'.$parsed['query'] : '').
                (isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');
        }

        if (!str_starts_with($url, '/')) {
            $url = '/'.$url;
        }

        $method = mb_strtoupper($request->input('method', 'GET'));

        $subRequest = Request::create($url, $method);
        $subRequest->headers->set('X-DigDeep-Profile', '1');

        $this->collector->startRequest();

        $this->collector->setRequest(
            $method,
            $url,
        );

        try {
            $response = app()->handle($subRequest);
        } catch (Throwable $e) {
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
            mb_strlen($response->getContent() ?: ''),
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

        if (!$profile) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $filename = "digdeep-{$profile['method']}-".Str::slug($profile['url'])."-{$id}.json";

        return response()->streamDownload(function () use ($profile): void {
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

        if (!$profile) {
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
        } catch (Throwable $e) {
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
            mb_strlen($response->getContent() ?: ''),
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

        $trimmed = mb_trim($sql);

        if (mb_strlen($trimmed) > 10000) {
            return response()->json(['error' => 'SQL query exceeds maximum length'], 400);
        }

        // Only allow EXPLAIN on SELECT queries
        if (!preg_match('/^\s*SELECT\b/i', $trimmed)) {
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
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function aiSuggest(Request $request): JsonResponse
    {
        $request->validate([
            'sql'    => ['required', 'string', 'max:10000'],
            'issue'  => ['required', 'string', 'in:n1,slow,select_star,duplicate'],
            'caller' => ['nullable', 'string', 'max:500'],
            'time_ms'=> ['nullable', 'numeric'],
        ]);

        $hasSdk   = class_exists(\LaravelPlus\DigDeep\Ai\Agents\QueryFixerAgent::class)
            && function_exists('Laravel\Ai\agent');
        $apiKey   = config('digdeep.ai_key');
        $provider = config('digdeep.ai_provider', 'openai');

        if (!$hasSdk && !$apiKey) {
            return response()->json(['error' => 'No AI configured. Set DIGDEEP_AI_KEY in your .env file.'], 501);
        }

        $sql    = $request->input('sql');
        $issue  = $request->input('issue');
        $caller = $request->input('caller', '');
        $timeMs = $request->input('time_ms');

        $issueLabels = [
            'n1'          => 'N+1 Query Pattern — this query appears to run in a loop, once per parent record.',
            'slow'        => 'Slow Query — this query took '.round((float) $timeMs, 1).'ms, which exceeds the threshold.',
            'select_star' => 'SELECT * — this query fetches all columns unnecessarily.',
            'duplicate'   => 'Duplicate Query — this exact query runs multiple times in the same request.',
        ];

        $prompt = <<<PROMPT
Fix this Laravel database query issue.

Issue: {$issueLabels[$issue]}
SQL: {$sql}
Caller: {$caller}
PROMPT;

        try {
            if ($hasSdk) {
                // If a DigDeep-specific key is configured, inject it into the provider config
                // so the SDK uses it instead of the app's default key.
                if ($apiKey) {
                    config(["ai.providers.{$provider}.key" => $apiKey]);
                }

                $response = (new \LaravelPlus\DigDeep\Ai\Agents\QueryFixerAgent())->prompt(
                    $prompt,
                    provider: $apiKey ? $provider : null,
                );

                return response()->json([
                    'analysis'   => $response['analysis'],
                    'suggestion' => $response['suggestion'],
                    'file_path'  => $response['file_path'] ?? null,
                    'old_code'   => $response['old_code'] ?? null,
                    'new_code'   => $response['new_code'] ?? null,
                ]);
            }

            $text = $this->callAiDirectly($provider, $apiKey, $prompt);

            return response()->json(['suggestion' => $text]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function aiInvestigateException(Request $request): JsonResponse
    {
        $request->validate([
            'class'   => ['required', 'string', 'max:500'],
            'message' => ['required', 'string', 'max:5000'],
            'file'    => ['nullable', 'string', 'max:500'],
            'line'    => ['nullable', 'integer'],
            'trace'   => ['nullable', 'array', 'max:10'],
        ]);

        $hasSdk   = class_exists(\LaravelPlus\DigDeep\Ai\Agents\ExceptionInvestigatorAgent::class)
            && function_exists('Laravel\Ai\agent');
        $apiKey   = config('digdeep.ai_key');
        $provider = config('digdeep.ai_provider', 'openai');

        if (!$hasSdk && !$apiKey) {
            return response()->json(['error' => 'No AI configured. Set DIGDEEP_AI_KEY in your .env file.'], 501);
        }

        $class   = $request->input('class');
        $message = $request->input('message');
        $file    = $request->input('file', '');
        $line    = $request->input('line', '');
        $trace   = collect($request->input('trace', []))
            ->map(fn (array $f): string => ($f['file'] ?? '').':'.($f['line'] ?? '').' → '.($f['class'] ?? '').($f['function'] ? '::'.$f['function'].'()' : ''))
            ->implode("\n");

        $prompt = <<<PROMPT
Investigate this Laravel exception.

Exception: {$class}
Message: {$message}
File: {$file}
Line: {$line}

Stack Trace (top frames):
{$trace}
PROMPT;

        try {
            if ($hasSdk) {
                if ($apiKey) {
                    config(["ai.providers.{$provider}.key" => $apiKey]);
                }

                $response = (new \LaravelPlus\DigDeep\Ai\Agents\ExceptionInvestigatorAgent())->prompt(
                    $prompt,
                    provider: $apiKey ? $provider : null,
                );

                return response()->json([
                    'analysis'   => $response['analysis'],
                    'root_cause' => $response['root_cause'],
                    'suggestion' => $response['suggestion'],
                    'file_path'  => $response['file_path'] ?? null,
                    'old_code'   => $response['old_code'] ?? null,
                    'new_code'   => $response['new_code'] ?? null,
                ]);
            }

            $text = $this->callAiDirectly($provider, $apiKey, $prompt);

            return response()->json(['suggestion' => $text]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function aiApplyFix(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => ['required', 'string', 'max:500'],
            'old_code'  => ['required', 'string', 'max:10000'],
            'new_code'  => ['required', 'string', 'max:10000'],
        ]);

        $tool = new \LaravelPlus\DigDeep\Ai\Tools\WriteSourceFileTool();

        $fakeRequest = new \Laravel\Ai\Tools\Request([
            'path'     => $request->input('file_path'),
            'old_code' => $request->input('old_code'),
            'new_code' => $request->input('new_code'),
        ]);

        $result = $tool->handle($fakeRequest);

        if (str_starts_with((string) $result, 'Error:')) {
            return response()->json(['error' => (string) $result], 422);
        }

        return response()->json(['status' => 'ok', 'message' => (string) $result]);
    }

    private function callAiDirectly(string $provider, string $apiKey, string $prompt): string
    {
        if ($provider === 'anthropic') {
            $model = config('digdeep.ai_model', 'claude-haiku-4-5-20251001');

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 500,
                'system' => 'You are a Laravel database performance expert. Be concise and practical. Respond with PROBLEM:, FIX:, and WHY: sections.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $body = $response->json();

            if ($response->failed() || empty($body['content'][0]['text'])) {
                throw new RuntimeException($body['error']['message'] ?? 'Anthropic API request failed.');
            }

            return $body['content'][0]['text'];
        }

        // Default: OpenAI
        $model = config('digdeep.ai_model', 'gpt-4o-mini');

        $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a Laravel database performance expert. Be concise and practical. Respond with PROBLEM:, FIX:, and WHY: sections.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 500,
                'temperature' => 0.3,
            ]);

        $body = $response->json();

        if ($response->failed() || empty($body['choices'][0]['message']['content'])) {
            throw new RuntimeException($body['error']['message'] ?? 'OpenAI API request failed.');
        }

        return $body['choices'][0]['message']['content'];
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

        $profiles = !empty($criteria) ? $this->storage->filter($criteria) : $this->storage->all();

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

            if (!isset($routeMap[$key])) {
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

        return response()->streamDownload(function () use ($profiles): void {
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
            $tag = strip_tags(mb_trim($request->input('tag', '')));
            if (empty($tag) || mb_strlen($tag) > 500) {
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

    public function htmlExport(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate([
            'template' => ['required', 'string', 'in:dashboard,performance,profile'],
            'id' => ['nullable', 'string'],
        ]);

        $template = $request->input('template');
        $exportedAt = now()->toDateTimeString();
        $appName = config('app.name', 'Laravel');

        if ($template === 'dashboard') {
            $allProfiles = $this->storage->all();
            $count = count($allProfiles);
            $stats = $this->computeExportStats($allProfiles);
            $topRoutes = $this->computeTopRoutes($allProfiles);

            $data = [
                'exportedAt' => $exportedAt,
                'appName' => $appName,
                'stats' => $stats,
                'topRoutes' => $topRoutes,
                'profiles' => array_slice($allProfiles, 0, 100),
            ];

            $html = view('digdeep::exports.dashboard', compact('data'))->render();
            $filename = 'digdeep-dashboard-'.now()->format('Y-m-d-His').'.html';
        } elseif ($template === 'performance') {
            $allProfiles = $this->storage->all();
            ['routes' => $routes, 'global' => $global] = $this->computeExportPerformance($allProfiles);

            $data = [
                'exportedAt' => $exportedAt,
                'appName' => $appName,
                'routes' => $routes,
                'global' => $global,
            ];

            $html = view('digdeep::exports.performance', compact('data'))->render();
            $filename = 'digdeep-performance-'.now()->format('Y-m-d-His').'.html';
        } else {
            $id = $request->input('id');

            if (! $id) {
                return response()->json(['error' => 'Profile ID required'], 400);
            }

            $profile = $this->storage->find($id);

            if (! $profile) {
                return response()->json(['error' => 'Profile not found'], 404);
            }

            $data = [
                'exportedAt' => $exportedAt,
                'appName' => $appName,
                'profile' => $profile,
            ];

            $html = view('digdeep::exports.profile', compact('data'))->render();
            $filename = 'digdeep-profile-'.now()->format('Y-m-d-His').'.html';
        }

        return response()->streamDownload(function () use ($html): void {
            echo $html;
        }, $filename, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     * @return array<string, mixed>
     */
    private function computeExportStats(array $profiles): array
    {
        $count = count($profiles);

        if ($count === 0) {
            return ['total' => 0, 'avg_duration' => 0, 'error_rate' => 0, 'success_rate' => 100, 'avg_memory' => 0, 'avg_queries' => 0];
        }

        $durations = array_map(fn ($p) => (float) $p['duration_ms'], $profiles);
        $errors = count(array_filter($profiles, fn ($p) => (int) $p['status_code'] >= 400));
        $memories = array_map(fn ($p) => (float) $p['memory_peak_mb'], $profiles);
        $queries = array_map(fn ($p) => (int) $p['query_count'], $profiles);

        return [
            'total' => $count,
            'avg_duration' => round(array_sum($durations) / $count, 1),
            'error_rate' => round($errors / $count * 100, 1),
            'success_rate' => round((1 - $errors / $count) * 100, 1),
            'avg_memory' => round(array_sum($memories) / $count, 1),
            'avg_queries' => round(array_sum($queries) / $count, 1),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     * @return array<int, array<string, mixed>>
     */
    private function computeTopRoutes(array $profiles): array
    {
        $routeMap = [];

        foreach ($profiles as $p) {
            $key = $p['method'].' '.$p['url'];

            if (! isset($routeMap[$key])) {
                $routeMap[$key] = ['method' => $p['method'], 'url' => $p['url'], 'count' => 0, 'durations' => [], 'errors' => 0];
            }

            $routeMap[$key]['count']++;
            $routeMap[$key]['durations'][] = (float) $p['duration_ms'];

            if ((int) $p['status_code'] >= 400) {
                $routeMap[$key]['errors']++;
            }
        }

        $routes = array_map(fn ($r) => [
            'method' => $r['method'],
            'url' => $r['url'],
            'count' => $r['count'],
            'avg_duration' => round(array_sum($r['durations']) / count($r['durations']), 1),
            'error_rate' => round($r['errors'] / $r['count'] * 100, 1),
        ], array_values($routeMap));

        usort($routes, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($routes, 0, 20);
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     * @return array{routes: array<int, array<string, mixed>>, global: array<string, mixed>}
     */
    private function computeExportPerformance(array $profiles): array
    {
        $routeMap = [];
        $allDurations = [];
        $totalErrors = 0;

        foreach ($profiles as $p) {
            $key = $p['method'].' '.$p['url'];
            $duration = (float) $p['duration_ms'];
            $status = (int) $p['status_code'];
            $allDurations[] = $duration;

            if ($status >= 400) {
                $totalErrors++;
            }

            if (! isset($routeMap[$key])) {
                $routeMap[$key] = ['method' => $p['method'], 'url' => $p['url'], 'durations' => [], 'statuses' => [], 'queries' => [], 'memories' => [], 'timestamps' => []];
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
            $cnt = count($durations);
            $errors = count(array_filter($route['statuses'], fn ($s) => $s >= 400));

            $timestamps = $route['timestamps'];
            sort($timestamps);
            $spanMinutes = max((strtotime(end($timestamps)) - strtotime($timestamps[0])) / 60, 1);

            $routes[] = [
                'method' => $route['method'],
                'url' => $route['url'],
                'count' => $cnt,
                'p50' => $this->percentile($durations, 0.50),
                'p95' => $this->percentile($durations, 0.95),
                'p99' => $this->percentile($durations, 0.99),
                'avg_duration' => round(array_sum($durations) / $cnt, 1),
                'throughput_rpm' => round($cnt / $spanMinutes, 1),
                'error_rate' => round($errors / $cnt * 100, 1),
                'avg_queries' => round(array_sum($route['queries']) / $cnt, 1),
                'avg_memory' => round(array_sum($route['memories']) / $cnt, 1),
            ];
        }

        usort($routes, fn ($a, $b) => $b['p95'] <=> $a['p95']);

        $global = [];

        if (count($allDurations) > 0) {
            sort($allDurations);
            $global = [
                'total' => count($allDurations),
                'p50' => $this->percentile($allDurations, 0.50),
                'p95' => $this->percentile($allDurations, 0.95),
                'p99' => $this->percentile($allDurations, 0.99),
                'error_rate' => round($totalErrors / count($allDurations) * 100, 1),
            ];
        }

        return ['routes' => $routes, 'global' => $global];
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
