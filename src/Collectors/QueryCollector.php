<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Support\Facades\DB;

final class QueryCollector
{
    /** @var array<int, array{sql: string, bindings: array<mixed>, time_ms: float, start_offset_ms: float, caller: string}> */
    private array $queries = [];

    public function listen(float $requestStartTime = 0.0): void
    {
        DB::listen(function ($query) use ($requestStartTime): void {
            $caller = $this->findCaller();

            // DB::listen fires after the query completes; subtract duration to get start offset
            $startOffsetMs = $requestStartTime > 0
                ? max(0.0, (microtime(true) - $requestStartTime) * 1000 - $query->time)
                : 0.0;

            $this->queries[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
                'start_offset_ms' => round($startOffsetMs, 2),
                'caller' => $caller,
            ];
        });
    }

    /** @return array<int, array{sql: string, bindings: array<mixed>, time_ms: float, start_offset_ms: float, caller: string}> */
    public function getData(): array
    {
        return $this->queries;
    }

    private function findCaller(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        $frame = array_find($trace, function ($frame) {
            $file = $frame['file'] ?? '';

            return !empty($file)
                && !str_contains($file, '/vendor/')
                && !str_contains($file, '/digdeep/');
        });

        return $frame !== null
            ? basename($frame['file']).':'.($frame['line'] ?? '?')
            : 'unknown';
    }
}
