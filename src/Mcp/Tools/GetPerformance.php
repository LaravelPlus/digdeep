<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelPlus\DigDeep\Models\DigDeepProfile;

#[IsReadOnly]
#[Description('Get per-route performance metrics: P50/P95/P99 latency percentiles, throughput (RPM), error rates, and average queries/memory. Also returns global application-wide metrics.')]
final class GetPerformance extends Tool
{
    public function handle(Request $request): Response
    {
        $range = $request->get('range', 'all');

        $query = DigDeepProfile::query()->latest();

        match ($range) {
            'hour' => $query->where('created_at', '>=', now()->subHour()),
            'day' => $query->where('created_at', '>=', now()->subDay()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            default => null,
        };

        $profiles = $query
            ->get(['method', 'url', 'status_code', 'duration_ms', 'memory_peak_mb', 'query_count', 'created_at'])
            ->map(fn (DigDeepProfile $p) => [
                'method' => $p->method,
                'url' => $p->url,
                'status_code' => $p->status_code,
                'duration_ms' => $p->duration_ms,
                'memory_peak_mb' => $p->memory_peak_mb,
                'query_count' => $p->query_count,
                'created_at' => $p->created_at->toDateTimeString(),
            ])
            ->all();

        return Response::json([
            'range' => $range,
            'routes' => $this->computeRoutePerformance($profiles),
            'global' => $this->computeGlobalPerformance($profiles),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'range' => $schema->string()->description('Time range: "hour", "day", "week", or "all" (default).'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     * @return array<int, array<string, mixed>>
     */
    private function computeRoutePerformance(array $profiles): array
    {
        $routeMap = [];

        foreach ($profiles as $p) {
            $key = $p['method'] . ' ' . $p['url'];

            if (!isset($routeMap[$key])) {
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

            $routeMap[$key]['durations'][] = (float) $p['duration_ms'];
            $routeMap[$key]['statuses'][] = (int) $p['status_code'];
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

        $totalErrors = count(array_filter($profiles, fn ($p) => (int) $p['status_code'] >= 400));

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
}
