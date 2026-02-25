<?php

namespace LaravelPlus\DigDeep;

use LaravelPlus\DigDeep\Analyzers\QueryAnalyzer;
use LaravelPlus\DigDeep\Collectors\CacheCollector;
use LaravelPlus\DigDeep\Collectors\CommandCollector;
use LaravelPlus\DigDeep\Collectors\EventCollector;
use LaravelPlus\DigDeep\Collectors\HttpClientCollector;
use LaravelPlus\DigDeep\Collectors\InertiaCollector;
use LaravelPlus\DigDeep\Collectors\JobCollector;
use LaravelPlus\DigDeep\Collectors\LifecycleCollector;
use LaravelPlus\DigDeep\Collectors\MailCollector;
use LaravelPlus\DigDeep\Collectors\MiddlewareCollector;
use LaravelPlus\DigDeep\Collectors\ModelCollector;
use LaravelPlus\DigDeep\Collectors\NotificationCollector;
use LaravelPlus\DigDeep\Collectors\QueryCollector;
use LaravelPlus\DigDeep\Collectors\ScheduledTaskCollector;
use LaravelPlus\DigDeep\Collectors\ViewCollector;
use Symfony\Component\HttpFoundation\Response;

class DigDeepCollector
{
    private float $startTime;

    private int $startMemory;

    /** @var array<string, mixed> */
    private array $requestData = [];

    /** @var array<string, mixed> */
    private array $responseData = [];

    /** @var array<string, mixed> */
    private array $routeData = [];

    private bool $isAjax = false;

    /** @var array<string, mixed> */
    private array $inertiaData = [];

    /** @var array<string, mixed>|null */
    private ?array $exceptionData = null;

    private QueryCollector $queryCollector;

    private EventCollector $eventCollector;

    private ViewCollector $viewCollector;

    private CacheCollector $cacheCollector;

    private MailCollector $mailCollector;

    private HttpClientCollector $httpClientCollector;

    private JobCollector $jobCollector;

    private InertiaCollector $inertiaCollector;

    private ModelCollector $modelCollector;

    private LifecycleCollector $lifecycleCollector;

    private MiddlewareCollector $middlewareCollector;

    private CommandCollector $commandCollector;

    private ScheduledTaskCollector $scheduledTaskCollector;

    private NotificationCollector $notificationCollector;

    public function startRequest(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();

        $requestStart = defined('LARAVEL_START') ? LARAVEL_START : $this->startTime;
        $this->lifecycleCollector = new LifecycleCollector($requestStart, $this->startTime, $this->startMemory);

        $this->queryCollector = new QueryCollector;
        $this->eventCollector = new EventCollector;
        $this->viewCollector = new ViewCollector;
        $this->cacheCollector = new CacheCollector;
        $this->mailCollector = new MailCollector;
        $this->httpClientCollector = new HttpClientCollector;
        $this->jobCollector = new JobCollector;
        $this->inertiaCollector = new InertiaCollector;
        $this->modelCollector = new ModelCollector;
        $this->middlewareCollector = new MiddlewareCollector;
        $this->commandCollector = new CommandCollector;
        $this->scheduledTaskCollector = new ScheduledTaskCollector;
        $this->notificationCollector = new NotificationCollector;

        $this->lifecycleCollector->listen();
        $this->modelCollector->listen();
        $this->queryCollector->listen();
        $this->eventCollector->listen();
        $this->viewCollector->listen();
        $this->cacheCollector->listen();
        $this->mailCollector->listen();
        $this->httpClientCollector->listen();
        $this->jobCollector->listen();
        $this->commandCollector->listen();
        $this->scheduledTaskCollector->listen();
        $this->notificationCollector->listen();
    }

    public function markLifecyclePhase(string $name): void
    {
        if (isset($this->lifecycleCollector)) {
            $this->lifecycleCollector->markPhase($name);
        }
    }

    public function getMiddlewareCollector(): MiddlewareCollector
    {
        return $this->middlewareCollector;
    }

    public function setRequest(string $method, string $url, array $headers = [], array $payload = [], string $body = ''): void
    {
        $this->requestData = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'payload' => $payload,
            'body' => $body,
        ];
    }

    public function setRoute(array $routeData): void
    {
        $this->routeData = $routeData;
    }

    public function setResponse(int $statusCode, array $headers = [], int $size = 0, string $body = ''): void
    {
        $this->responseData = [
            'status_code' => $statusCode,
            'headers' => $headers,
            'size' => $size,
            'body' => $body,
        ];
    }

    public function setAjax(bool $isAjax): void
    {
        $this->isAjax = $isAjax;
    }

    public function collectInertia(Response $response): void
    {
        $this->inertiaCollector->collect($response);
        $this->inertiaData = $this->inertiaCollector->getData();
    }

    public function setException(\Throwable $e): void
    {
        $this->exceptionData = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice(array_map(function ($frame) {
                return [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'function' => $frame['function'] ?? null,
                ];
            }, $e->getTrace()), 0, 30),
            'previous' => $e->getPrevious() ? [
                'class' => get_class($e->getPrevious()),
                'message' => $e->getPrevious()->getMessage(),
            ] : null,
        ];
    }

    public function finishRequest(): array
    {
        $durationMs = (microtime(true) - $this->startTime) * 1000;
        $memoryPeakMb = round(memory_get_peak_usage() / 1024 / 1024, 2);
        $queries = $this->queryCollector->getData();
        $queryTimeMs = array_sum(array_column($queries, 'time_ms'));

        // Run N+1 detection
        $nPlusOne = QueryAnalyzer::detectNPlusOne($queries);

        return [
            'request' => $this->requestData,
            'route' => $this->routeData,
            'response' => $this->responseData,
            'queries' => $queries,
            'events' => $this->eventCollector->getData(),
            'views' => $this->viewCollector->getData(),
            'cache' => $this->cacheCollector->getData(),
            'mail' => $this->mailCollector->getData(),
            'http_client' => $this->httpClientCollector->getData(),
            'jobs' => $this->jobCollector->getData(),
            'models' => $this->modelCollector->getData(),
            'inertia' => $this->inertiaData,
            'lifecycle' => isset($this->lifecycleCollector) ? $this->lifecycleCollector->getData() : [],
            'exception' => $this->exceptionData,
            'is_ajax' => $this->isAjax,
            'n_plus_one' => $nPlusOne,
            'middleware_timing' => $this->middlewareCollector->getData(),
            'middleware_pipeline_ms' => $this->middlewareCollector->getTotalPipelineTime(),
            'commands' => $this->commandCollector->getData(),
            'scheduled_tasks' => $this->scheduledTaskCollector->getData(),
            'notifications' => $this->notificationCollector->getData(),
            'performance' => [
                'duration_ms' => round($durationMs, 2),
                'memory_peak_mb' => $memoryPeakMb,
                'query_count' => count($queries),
                'query_time_ms' => round($queryTimeMs, 2),
            ],
        ];
    }
}
