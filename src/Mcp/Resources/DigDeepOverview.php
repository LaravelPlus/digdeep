<?php

namespace LaravelPlus\DigDeep\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Description('Overview of DigDeep profiler features, collectors, and capabilities.')]
#[Uri('digdeep://overview')]
#[MimeType('text/markdown')]
class DigDeepOverview extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'MARKDOWN'
# DigDeep Profiler

DigDeep is a comprehensive Laravel application profiler that captures detailed request execution data.

## Collectors (14)

DigDeep captures data from the following collectors during each request:

1. **Lifecycle** - Bootstrap phases and timing
2. **Query** - All database queries with SQL, bindings, caller, and timing
3. **Event** - Dispatched events and listeners
4. **View** - Rendered Blade views and their data
5. **Cache** - Cache hits, misses, and writes
6. **Mail** - Sent emails
7. **HTTP Client** - Outbound HTTP requests
8. **Job** - Dispatched queue jobs
9. **Inertia** - Inertia.js component and props data
10. **Model** - Eloquent model operations
11. **Middleware** - Middleware execution timing
12. **Command** - Artisan command execution
13. **Scheduled Task** - Scheduled task runs
14. **Notification** - Sent notifications

## Query Analyzer

The built-in query analyzer detects:
- **N+1 queries** - Repeated query patterns that indicate missing eager loading
- **SELECT \*** - Queries selecting all columns instead of specific ones
- **Missing indexes** - WHERE clauses on non-indexed columns

## Performance Metrics

- Per-route P50/P95/P99 latency percentiles
- Throughput (requests per minute)
- Error rates
- Memory usage tracking
- Query count and timing

## Threshold Alerts

Profiles are auto-tagged when they exceed configured thresholds:
- `slow` - Duration exceeds threshold
- `query-heavy` - Too many queries
- `memory-hog` - High memory usage
- `slow-queries` - Query time exceeds threshold

## Dashboard Pages

- **Overview** - Latest profiles, stats, top routes
- **Pipeline** - Routes with lifecycle and middleware data
- **Profiler** - On-demand URL profiling
- **Performance** - Route percentiles and throughput
- **Trends** - Time-series performance data
- **Database** - Query analysis, table access, slow queries
- **Cache** - Hit rates, key patterns, missed keys
- **Errors** - Exception tracking and grouping
- **Security** - CSRF checks, header validation
- **Audits** - Route performance aggregates
MARKDOWN);
    }
}
