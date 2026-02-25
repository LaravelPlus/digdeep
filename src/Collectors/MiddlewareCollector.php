<?php

namespace LaravelPlus\DigDeep\Collectors;

class MiddlewareCollector
{
    /** @var array<int, array{name: string, duration_ms: float, is_estimated: bool}> */
    private array $middleware = [];

    private float $totalPipelineMs = 0;

    public function recordMiddleware(string $name, float $durationMs, bool $isEstimated = false): void
    {
        $this->middleware[] = [
            'name' => $name,
            'duration_ms' => round($durationMs, 3),
            'is_estimated' => $isEstimated,
        ];
    }

    public function setTotalPipelineTime(float $ms): void
    {
        $this->totalPipelineMs = round($ms, 3);
    }

    public function getTotalPipelineTime(): float
    {
        return $this->totalPipelineMs;
    }

    /** @return array<int, array{name: string, duration_ms: float, is_estimated: bool}> */
    public function getData(): array
    {
        return $this->middleware;
    }
}
