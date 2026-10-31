<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Storage;

use Illuminate\Support\Facades\DB;
use LaravelPlus\DigDeep\Events\ThresholdExceeded;
use LaravelPlus\DigDeep\Models\DigDeepProfile;
use LaravelPlus\DigDeep\Models\DigDeepRouteVisit;

final class DigDeepStorage
{
    public function __construct(private readonly int $maxProfiles = 100) {}

    public function store(string $id, array $data): void
    {
        $data = $this->enforceMaxSize($data);

        $performance = $data['performance'] ?? [];

        DigDeepProfile::query()->create([
            'id' => $id,
            'method' => $data['request']['method'] ?? 'GET',
            'url' => $data['request']['url'] ?? '',
            'status_code' => $data['response']['status_code'] ?? 0,
            'duration_ms' => $performance['duration_ms'] ?? 0,
            'memory_peak_mb' => $performance['memory_peak_mb'] ?? 0,
            'query_count' => $performance['query_count'] ?? 0,
            'query_time_ms' => $performance['query_time_ms'] ?? 0,
            'is_ajax' => (bool) ($data['is_ajax'] ?? false),
            'data' => $data,
        ]);

        $this->incrementRouteVisit(
            $data['request']['url'] ?? '',
            $data['request']['method'] ?? 'GET',
        );

        // Check thresholds and auto-tag
        $exceeded = $this->checkThresholds($id, $data);
        if (! empty($exceeded)) {
            $tagMap = [
                'duration_ms' => 'slow',
                'query_count' => 'query-heavy',
                'memory_peak_mb' => 'memory-hog',
                'query_time_ms' => 'slow-queries',
            ];

            $tags = array_map(fn ($key) => $tagMap[$key] ?? $key, $exceeded);
            $this->updateTags($id, implode(', ', $tags));

            ThresholdExceeded::dispatch($id, $exceeded, [
                'duration_ms' => $performance['duration_ms'] ?? 0,
                'memory_peak_mb' => $performance['memory_peak_mb'] ?? 0,
                'query_count' => $performance['query_count'] ?? 0,
                'query_time_ms' => $performance['query_time_ms'] ?? 0,
            ]);
        }

        [$chance, $outOf] = config('digdeep.prune_lottery', [1, 10]);
        if (random_int(1, (int) $outOf) <= (int) $chance) {
            $this->prune();
        }
    }

    /**
     * Check if a profile exceeds configured thresholds.
     *
     * @return array<int, string> List of exceeded threshold keys
     */
    public function checkThresholds(string $id, array $data): array
    {
        $thresholds = config('digdeep.thresholds', []);
        $performance = $data['performance'] ?? [];
        $exceeded = [];

        foreach ($thresholds as $key => $limit) {
            if ($limit === null || $limit <= 0) {
                continue;
            }

            $value = $performance[$key] ?? 0;

            if ($value > $limit) {
                $exceeded[] = $key;
            }
        }

        return $exceeded;
    }

    public function find(string $id): ?array
    {
        $profile = DigDeepProfile::query()->find($id);

        if (! $profile) {
            return null;
        }

        return $this->profileToArray($profile);
    }

    public function all(): array
    {
        return DigDeepProfile::query()
            ->latest()
            ->get(['id', 'method', 'url', 'status_code', 'duration_ms', 'memory_peak_mb', 'query_count', 'query_time_ms', 'is_ajax', 'tags', 'created_at'])
            ->map(fn (DigDeepProfile $profile) => [
                'id' => $profile->id,
                'method' => $profile->method,
                'url' => $profile->url,
                'status_code' => $profile->status_code,
                'duration_ms' => $profile->duration_ms,
                'memory_peak_mb' => $profile->memory_peak_mb,
                'query_count' => $profile->query_count,
                'query_time_ms' => $profile->query_time_ms,
                'is_ajax' => $profile->is_ajax ? 1 : 0,
                'tags' => $profile->tags,
                'created_at' => $profile->created_at->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Return all profiles with full data in a single query.
     * Avoids N+1 from calling all() then find() on each profile.
     *
     * @return array<int, array>
     */
    public function allWithData(int $limit = 200): array
    {
        return DigDeepProfile::query()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DigDeepProfile $profile) => $this->profileToArray($profile))
            ->all();
    }

    /**
     * Return only error profiles (status >= 400) with full data.
     * More efficient than loading all profiles when only errors are needed.
     *
     * @return array<int, array>
     */
    public function allErrorsWithData(int $limit = 200): array
    {
        return DigDeepProfile::query()
            ->where('status_code', '>=', 400)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DigDeepProfile $profile) => $this->profileToArray($profile))
            ->all();
    }

    /**
     * Filter profiles by criteria.
     *
     * @param  array{status_min?: int, status_max?: int, duration_min?: float, duration_max?: float, tag?: string, date_from?: string, date_to?: string, route?: string, has_errors?: bool, method?: string}  $criteria
     * @return array<int, array>
     */
    public function filter(array $criteria): array
    {
        $query = DigDeepProfile::query()->latest();

        if (isset($criteria['status_min'])) {
            $query->where('status_code', '>=', $criteria['status_min']);
        }

        if (isset($criteria['status_max'])) {
            $query->where('status_code', '<=', $criteria['status_max']);
        }

        if (isset($criteria['duration_min'])) {
            $query->where('duration_ms', '>=', $criteria['duration_min']);
        }

        if (isset($criteria['duration_max'])) {
            $query->where('duration_ms', '<=', $criteria['duration_max']);
        }

        if (isset($criteria['tag']) && $criteria['tag'] !== '') {
            $query->where('tags', 'LIKE', '%'.$criteria['tag'].'%');
        }

        if (isset($criteria['date_from'])) {
            $query->where('created_at', '>=', $criteria['date_from']);
        }

        if (isset($criteria['date_to'])) {
            $query->where('created_at', '<=', $criteria['date_to']);
        }

        if (isset($criteria['route']) && $criteria['route'] !== '') {
            $query->where('url', 'LIKE', '%'.$criteria['route'].'%');
        }

        if (isset($criteria['has_errors']) && $criteria['has_errors']) {
            $query->where('status_code', '>=', 400);
        }

        if (isset($criteria['method']) && $criteria['method'] !== '') {
            $query->where('method', mb_strtoupper($criteria['method']));
        }

        return $query
            ->get(['id', 'method', 'url', 'status_code', 'duration_ms', 'memory_peak_mb', 'query_count', 'query_time_ms', 'is_ajax', 'tags', 'created_at'])
            ->map(fn (DigDeepProfile $profile) => [
                'id' => $profile->id,
                'method' => $profile->method,
                'url' => $profile->url,
                'status_code' => $profile->status_code,
                'duration_ms' => $profile->duration_ms,
                'memory_peak_mb' => $profile->memory_peak_mb,
                'query_count' => $profile->query_count,
                'query_time_ms' => $profile->query_time_ms,
                'is_ajax' => $profile->is_ajax ? 1 : 0,
                'tags' => $profile->tags,
                'created_at' => $profile->created_at->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Bulk delete profiles by IDs.
     */
    public function bulkDelete(array $ids): int
    {
        return DigDeepProfile::query()->whereIn('id', $ids)->delete();
    }

    /**
     * Bulk add a tag to profiles by IDs.
     */
    public function bulkTag(array $ids, string $tag): int
    {
        $count = 0;

        DigDeepProfile::query()->whereIn('id', $ids)->chunkById(50, function ($profiles) use ($tag, &$count): void {
            foreach ($profiles as $profile) {
                $existing = $profile->tags ?? '';
                $existingTags = array_filter(array_map('trim', explode(',', $existing)));

                if (! in_array($tag, $existingTags)) {
                    $existingTags[] = $tag;
                    $profile->update(['tags' => implode(', ', $existingTags)]);
                }

                $count++;
            }
        });

        return $count;
    }

    public function prune(): void
    {
        $this->pruneKeeping($this->maxProfiles);
    }

    public function pruneKeeping(int $keep): void
    {
        $count = DigDeepProfile::query()->count();

        if ($count <= $keep) {
            return;
        }

        // Chunked so a large backlog stays under the driver's bound-parameter limit (SQLite: 999 on old builds).
        DigDeepProfile::query()
            ->oldest()
            ->limit($count - $keep)
            ->pluck('id')
            ->chunk(500)
            ->each(fn ($ids) => DigDeepProfile::query()->whereIn('id', $ids)->delete());
    }

    public function delete(string $id): void
    {
        DigDeepProfile::query()->where('id', $id)->delete();
    }

    public function stats(): array
    {
        $result = DigDeepProfile::query()->selectRaw('
            COUNT(*) as total,
            COALESCE(AVG(duration_ms), 0) as avg_duration,
            COALESCE(MIN(duration_ms), 0) as min_duration,
            COALESCE(MAX(duration_ms), 0) as max_duration,
            COALESCE(AVG(query_count), 0) as avg_queries,
            COALESCE(AVG(memory_peak_mb), 0) as avg_memory,
            COALESCE(MAX(duration_ms), 0) as slowest_duration,
            COALESCE(MAX(query_count), 0) as most_queries
        ')->first();

        return $result->toArray();
    }

    public function clear(): void
    {
        DigDeepProfile::query()->delete();
    }

    public function incrementRouteVisit(string $url, string $method = 'GET'): void
    {
        DigDeepRouteVisit::query()->upsert(
            [['url' => $url, 'method' => $method, 'visit_count' => 1, 'last_visited_at' => now()]],
            ['url', 'method'],
            ['visit_count' => DB::raw('digdeep_route_visits.visit_count + 1'), 'last_visited_at' => now()],
        );
    }

    public function updateTags(string $id, string $tags): void
    {
        DigDeepProfile::query()->where('id', $id)->update(['tags' => $tags]);
    }

    public function updateNotes(string $id, string $notes): void
    {
        DigDeepProfile::query()->where('id', $id)->update(['notes' => $notes]);
    }

    /** @return array<int, array{url: string, method: string, visit_count: int, last_visited_at: string}> */
    public function topRoutes(int $limit = 20): array
    {
        return DigDeepRouteVisit::query()
            ->orderByDesc('visit_count')
            ->limit($limit)
            ->get(['url', 'method', 'visit_count', 'last_visited_at'])
            ->map(fn (DigDeepRouteVisit $visit) => [
                'url' => $visit->url,
                'method' => $visit->method,
                'visit_count' => $visit->visit_count,
                'last_visited_at' => $visit->last_visited_at->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Shrink a profile until its JSON fits in `max_profile_size_kb`: shorten bodies,
     * drop bindings, then empty the largest sections one by one.
     */
    private function enforceMaxSize(array $data): array
    {
        $maxBytes = (int) config('digdeep.max_profile_size_kb', 512) * 1024;
        $fits = fn (array $data): bool => strlen((string) json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR)) <= $maxBytes;

        if ($fits($data)) {
            return $data;
        }

        $data['_truncated'] = true;

        foreach (['request', 'response'] as $part) {
            if (isset($data[$part]['body']) && strlen($data[$part]['body']) > 1024) {
                $data[$part]['body'] = mb_strcut($data[$part]['body'], 0, 1024).'... [truncated]';
            }
        }

        if ($fits($data)) {
            return $data;
        }

        foreach ($data['queries'] ?? [] as $i => $query) {
            $data['queries'][$i]['bindings'] = [];
        }

        $sizes = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && ! in_array($key, ['request', 'response', 'route', 'performance'], true)) {
                $sizes[$key] = strlen((string) json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
        }
        arsort($sizes);

        foreach (array_keys($sizes) as $key) {
            if ($fits($data)) {
                break;
            }

            $data['_dropped'][$key] = 'size';
            $data[$key] = [];
        }

        return $data;
    }

    private function profileToArray(DigDeepProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'method' => $profile->method,
            'url' => $profile->url,
            'status_code' => $profile->status_code,
            'duration_ms' => $profile->duration_ms,
            'memory_peak_mb' => $profile->memory_peak_mb,
            'query_count' => $profile->query_count,
            'query_time_ms' => $profile->query_time_ms,
            'is_ajax' => $profile->is_ajax ? 1 : 0,
            'tags' => $profile->tags,
            'notes' => $profile->notes,
            'data' => $profile->data,
            'created_at' => $profile->created_at->toDateTimeString(),
        ];
    }
}
