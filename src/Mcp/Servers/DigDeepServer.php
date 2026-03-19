<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use LaravelPlus\DigDeep\Mcp\Resources\DigDeepConfig;
use LaravelPlus\DigDeep\Mcp\Resources\DigDeepOverview;
use LaravelPlus\DigDeep\Mcp\Tools\AnalyzeQueries;
use LaravelPlus\DigDeep\Mcp\Tools\GetPerformance;
use LaravelPlus\DigDeep\Mcp\Tools\GetProfile;
use LaravelPlus\DigDeep\Mcp\Tools\GetSlowestRoutes;
use LaravelPlus\DigDeep\Mcp\Tools\GetStats;
use LaravelPlus\DigDeep\Mcp\Tools\GetTrends;
use LaravelPlus\DigDeep\Mcp\Tools\ListProfiles;
use LaravelPlus\DigDeep\Mcp\Tools\ProfileUrl;

#[Name('DigDeep Profiler')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
DigDeep is a Laravel application profiler that captures request profiles including queries, events, views, cache operations, and more.

Available tools:
- **list-profiles**: List and filter captured profiles by method, status, duration, route, or tag.
- **get-profile**: Get full detail of a specific profile by ID, including all queries, events, views, and performance data.
- **get-stats**: Get aggregate statistics across all profiles (totals, averages, min/max).
- **get-performance**: Get per-route P50/P95/P99 latency percentiles, throughput, and error rates.
- **get-trends**: Get time-series trend data for a specific route or all routes.
- **profile-url**: Trigger on-demand profiling of a URL by making a sub-request.
- **analyze-queries**: Analyze queries from a profile for N+1 patterns, SELECT *, and missing indexes.
- **get-slowest-routes**: Get the slowest routes ranked by P95 latency.

Available resources:
- **digdeep://overview**: Documentation describing DigDeep features and capabilities.
- **digdeep://config**: Current DigDeep configuration values.

Use list-profiles or get-stats first to understand the application's performance profile, then drill into specific profiles with get-profile or analyze-queries.
MARKDOWN)]
final class DigDeepServer extends Server
{
    /** @var array<int, class-string<Server\Tool>> */
    protected array $tools = [
        ListProfiles::class,
        GetProfile::class,
        GetStats::class,
        GetPerformance::class,
        GetTrends::class,
        ProfileUrl::class,
        AnalyzeQueries::class,
        GetSlowestRoutes::class,
    ];

    /** @var array<int, class-string<Server\Resource>> */
    protected array $resources = [
        DigDeepOverview::class,
        DigDeepConfig::class,
    ];

    /** @var array<int, class-string<Server\Prompt>> */
    protected array $prompts = [];
}
