<?php

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Support\Facades\DB;

class QueryCollector
{
    /** @var array<int, array{sql: string, bindings: array<mixed>, time_ms: float, caller: string}> */
    private array $queries = [];

    public function listen(): void
    {
        DB::listen(function ($query) {
            $caller = $this->findCaller();

            $this->queries[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
                'caller' => $caller,
            ];
        });
    }

    /** @return array<int, array{sql: string, bindings: array<mixed>, time_ms: float, caller: string}> */
    public function getData(): array
    {
        return $this->queries;
    }

    private function findCaller(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';

            if (str_contains($file, '/vendor/') || str_contains($file, '/digdeep/')) {
                continue;
            }

            if (! empty($file)) {
                return basename($file).':'.($frame['line'] ?? '?');
            }
        }

        return 'unknown';
    }
}
