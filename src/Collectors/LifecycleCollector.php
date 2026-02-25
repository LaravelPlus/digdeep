<?php

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;

class LifecycleCollector
{
    private float $requestStart;

    private float $appStart;

    private ?float $routeMatched = null;

    private ?float $middlewareDone = null;

    private ?float $responseReady = null;

    /** @var array<int, float> */
    private array $viewTimestamps = [];

    private int $appStartMemory;

    private ?int $routeMatchedMemory = null;

    private ?int $middlewareDoneMemory = null;

    private ?int $responseReadyMemory = null;

    /** @var array<int, int> */
    private array $viewMemorySnapshots = [];

    public function __construct(float $requestStart, float $appStart, int $appStartMemory)
    {
        $this->requestStart = $requestStart;
        $this->appStart = $appStart;
        $this->appStartMemory = $appStartMemory;
    }

    public function listen(): void
    {
        Event::listen(RouteMatched::class, function () {
            $this->routeMatched = microtime(true);
            $this->routeMatchedMemory = memory_get_usage();
        });

        Event::listen('composing:*', function () {
            $this->viewTimestamps[] = microtime(true);
            $this->viewMemorySnapshots[] = memory_get_usage();
        });
    }

    public function markPhase(string $name): void
    {
        match ($name) {
            'middleware_done' => $this->markMiddlewareDone(),
            'response_ready' => $this->markResponseReady(),
            default => null,
        };
    }

    private function markMiddlewareDone(): void
    {
        $this->middlewareDone = microtime(true);
        $this->middlewareDoneMemory = memory_get_usage();
    }

    private function markResponseReady(): void
    {
        $this->responseReady = microtime(true);
        $this->responseReadyMemory = memory_get_usage();
    }

    /**
     * @return array{phases: array<int, array{name: string, start_ms: float, end_ms: float, duration_ms: float, memory_bytes: int, memory_delta_bytes: int}>, request_start: float, app_start: float}
     */
    public function getData(): array
    {
        $phases = [];
        $now = microtime(true);
        $prevMemory = 0;

        // Bootstrap: LARAVEL_START → appStart
        $bootstrapEnd = $this->appStart;
        $bootstrapMemory = $this->appStartMemory;
        $phases[] = [
            'name' => 'bootstrap',
            'start_ms' => 0,
            'end_ms' => round(($bootstrapEnd - $this->requestStart) * 1000, 3),
            'duration_ms' => round(($bootstrapEnd - $this->requestStart) * 1000, 3),
            'memory_bytes' => $bootstrapMemory,
            'memory_delta_bytes' => $bootstrapMemory,
        ];
        $prevMemory = $bootstrapMemory;

        // Routing: appStart → routeMatched
        if ($this->routeMatched !== null) {
            $routingStart = $bootstrapEnd;
            $routingMemory = $this->routeMatchedMemory ?? $prevMemory;
            $phases[] = [
                'name' => 'routing',
                'start_ms' => round(($routingStart - $this->requestStart) * 1000, 3),
                'end_ms' => round(($this->routeMatched - $this->requestStart) * 1000, 3),
                'duration_ms' => round(($this->routeMatched - $routingStart) * 1000, 3),
                'memory_bytes' => $routingMemory,
                'memory_delta_bytes' => max(0, $routingMemory - $prevMemory),
            ];
            $prevMemory = $routingMemory;
        }

        // Controller + View rendering: routeMatched → middlewareDone
        if ($this->routeMatched !== null && $this->middlewareDone !== null) {
            $firstView = ! empty($this->viewTimestamps) ? min($this->viewTimestamps) : null;
            $lastView = ! empty($this->viewTimestamps) ? max($this->viewTimestamps) : null;
            $firstViewMemory = ! empty($this->viewMemorySnapshots) ? $this->viewMemorySnapshots[0] : null;
            $lastViewMemory = ! empty($this->viewMemorySnapshots) ? end($this->viewMemorySnapshots) : null;

            if ($firstView !== null && $lastView !== null) {
                // Controller: routeMatched → firstView
                $controllerMemory = $firstViewMemory ?? $prevMemory;
                $phases[] = [
                    'name' => 'controller',
                    'start_ms' => round(($this->routeMatched - $this->requestStart) * 1000, 3),
                    'end_ms' => round(($firstView - $this->requestStart) * 1000, 3),
                    'duration_ms' => round(($firstView - $this->routeMatched) * 1000, 3),
                    'memory_bytes' => $controllerMemory,
                    'memory_delta_bytes' => max(0, $controllerMemory - $prevMemory),
                ];
                $prevMemory = $controllerMemory;

                // View Rendering: firstView → lastView
                $viewEnd = max($lastView, $firstView + 0.0001);
                $viewMemory = $lastViewMemory ?? $this->middlewareDoneMemory ?? $prevMemory;
                $phases[] = [
                    'name' => 'view',
                    'start_ms' => round(($firstView - $this->requestStart) * 1000, 3),
                    'end_ms' => round(($viewEnd - $this->requestStart) * 1000, 3),
                    'duration_ms' => round(($viewEnd - $firstView) * 1000, 3),
                    'memory_bytes' => $viewMemory,
                    'memory_delta_bytes' => max(0, $viewMemory - $prevMemory),
                ];
                $prevMemory = $viewMemory;
            } else {
                // No views — entire segment is controller
                $controllerMemory = $this->middlewareDoneMemory ?? $prevMemory;
                $phases[] = [
                    'name' => 'controller',
                    'start_ms' => round(($this->routeMatched - $this->requestStart) * 1000, 3),
                    'end_ms' => round(($this->middlewareDone - $this->requestStart) * 1000, 3),
                    'duration_ms' => round(($this->middlewareDone - $this->routeMatched) * 1000, 3),
                    'memory_bytes' => $controllerMemory,
                    'memory_delta_bytes' => max(0, $controllerMemory - $prevMemory),
                ];
                $prevMemory = $controllerMemory;
            }
        }

        // Response: middlewareDone → responseReady
        if ($this->middlewareDone !== null) {
            $responseEnd = $this->responseReady ?? $now;
            $responseMemory = $this->responseReadyMemory ?? memory_get_usage();
            $phases[] = [
                'name' => 'response',
                'start_ms' => round(($this->middlewareDone - $this->requestStart) * 1000, 3),
                'end_ms' => round(($responseEnd - $this->requestStart) * 1000, 3),
                'duration_ms' => round(($responseEnd - $this->middlewareDone) * 1000, 3),
                'memory_bytes' => $responseMemory,
                'memory_delta_bytes' => max(0, $responseMemory - $prevMemory),
            ];
        }

        return [
            'phases' => $phases,
            'request_start' => $this->requestStart,
            'app_start' => $this->appStart,
        ];
    }
}
