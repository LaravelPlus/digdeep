<?php

namespace LaravelPlus\DigDeep\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

#[IsReadOnly]
#[Description('Get the slowest routes ranked by P95 latency. Useful for identifying performance bottlenecks.')]
class GetSlowestRoutes extends Tool
{
    public function __construct(private DigDeepStorage $storage) {}

    public function handle(Request $request): Response
    {
        $limit = (int) ($request->get('limit') ?? 10);
        $profiles = $this->storage->all();

        $routeMap = [];
        foreach ($profiles as $p) {
            $key = $p['method'] . ' ' . $p['url'];

            if (! isset($routeMap[$key])) {
                $routeMap[$key] = [
                    'method' => $p['method'],
                    'url' => $p['url'],
                    'durations' => [],
                    'statuses' => [],
                    'queries' => [],
                    'memories' => [],
                ];
            }

            $routeMap[$key]['durations'][] = (float) $p['duration_ms'];
            $routeMap[$key]['statuses'][] = (int) $p['status_code'];
            $routeMap[$key]['queries'][] = (int) $p['query_count'];
            $routeMap[$key]['memories'][] = (float) $p['memory_peak_mb'];
        }

        $routes = [];
        foreach ($routeMap as $route) {
            $durations = $route['durations'];
            sort($durations);
            $count = count($durations);
            $errors = count(array_filter($route['statuses'], fn ($s) => $s >= 400));

            $routes[] = [
                'method' => $route['method'],
                'url' => $route['url'],
                'count' => $count,
                'p50' => $this->percentile($durations, 0.50),
                'p95' => $this->percentile($durations, 0.95),
                'p99' => $this->percentile($durations, 0.99),
                'avg_duration' => round(array_sum($durations) / $count, 1),
                'error_rate' => round($errors / $count * 100, 1),
                'avg_queries' => round(array_sum($route['queries']) / $count, 1),
                'avg_memory' => round(array_sum($route['memories']) / $count, 1),
            ];
        }

        usort($routes, fn ($a, $b) => $b['p95'] <=> $a['p95']);

        return Response::json(array_slice($routes, 0, $limit));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Max number of routes to return. Default 10.'),
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
