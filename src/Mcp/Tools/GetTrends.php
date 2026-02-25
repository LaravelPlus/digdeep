<?php

namespace LaravelPlus\DigDeep\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelPlus\DigDeep\Models\DigDeepProfile;

#[IsReadOnly]
#[Description('Get time-series trend data showing duration, memory, and query count over time. Optionally filter by route.')]
class GetTrends extends Tool
{
    public function handle(Request $request): Response
    {
        $route = $request->get('route');
        $range = $request->get('range', 'all');

        $query = DigDeepProfile::query()->oldest();

        if ($route) {
            $query->where('url', 'LIKE', '%' . $route . '%');
        }

        match ($range) {
            'hour' => $query->where('created_at', '>=', now()->subHour()),
            'day' => $query->where('created_at', '>=', now()->subDay()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            default => null,
        };

        $profiles = $query
            ->get(['url', 'duration_ms', 'memory_peak_mb', 'query_count', 'query_time_ms', 'status_code', 'created_at'])
            ->map(fn (DigDeepProfile $p) => [
                'url' => $p->url,
                'duration_ms' => $p->duration_ms,
                'memory_peak_mb' => $p->memory_peak_mb,
                'query_count' => $p->query_count,
                'query_time_ms' => $p->query_time_ms,
                'status_code' => $p->status_code,
                'created_at' => $p->created_at->toDateTimeString(),
            ])
            ->all();

        return Response::json([
            'route' => $route,
            'range' => $range,
            'count' => count($profiles),
            'data_points' => $profiles,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'route' => $schema->string()->description('URL pattern to filter by (substring match). Omit for all routes.'),
            'range' => $schema->string()->description('Time range: "hour", "day", "week", or "all" (default).'),
        ];
    }
}
