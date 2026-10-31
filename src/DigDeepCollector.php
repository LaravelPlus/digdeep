<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep;

use LaravelPlus\DigDeep\Analyzers\QueryAnalyzer;
use LaravelPlus\DigDeep\Collectors\CacheCollector;
use LaravelPlus\DigDeep\Collectors\CommandCollector;
use LaravelPlus\DigDeep\Collectors\EventCollector;
use LaravelPlus\DigDeep\Collectors\HttpClientCollector;
use LaravelPlus\DigDeep\Collectors\InertiaCollector;
use LaravelPlus\DigDeep\Collectors\JobCollector;
use LaravelPlus\DigDeep\Collectors\LifecycleCollector;
use LaravelPlus\DigDeep\Collectors\LogCollector;
use LaravelPlus\DigDeep\Collectors\MailCollector;
use LaravelPlus\DigDeep\Collectors\MiddlewareCollector;
use LaravelPlus\DigDeep\Collectors\ModelCollector;
use LaravelPlus\DigDeep\Collectors\NotificationCollector;
use LaravelPlus\DigDeep\Collectors\QueryCollector;
use LaravelPlus\DigDeep\Collectors\ScheduledTaskCollector;
use LaravelPlus\DigDeep\Collectors\ViewCollector;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class DigDeepCollector
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

    private LogCollector $logCollector;

    private bool $listening = false;

    public function startRequest(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
        $this->requestData = $this->responseData = $this->routeData = $this->inertiaData = [];
        $this->isAjax = false;
        $this->exceptionData = null;

        $requestStart = defined('LARAVEL_START') ? LARAVEL_START : $this->startTime;

        $fresh = [
            'lifecycleCollector' => new LifecycleCollector($requestStart, $this->startTime, $this->startMemory),
            'modelCollector' => new ModelCollector,
            'queryCollector' => new QueryCollector($this->startTime),
            'eventCollector' => new EventCollector,
            'viewCollector' => new ViewCollector,
            'cacheCollector' => new CacheCollector,
            'mailCollector' => new MailCollector,
            'httpClientCollector' => new HttpClientCollector,
            'jobCollector' => new JobCollector,
            'commandCollector' => new CommandCollector,
            'scheduledTaskCollector' => new ScheduledTaskCollector,
            'notificationCollector' => new NotificationCollector,
            'logCollector' => new LogCollector($this->startTime),
            'inertiaCollector' => new InertiaCollector,
            'middlewareCollector' => new MiddlewareCollector,
        ];

        // Long-lived workers (Octane, FrankenPHP) call this once per request: listeners are
        // registered only the first time, later requests just reset the collectors' state.
        foreach ($fresh as $property => $collector) {
            if ($this->listening) {
                self::resetFrom($this->{$property}, $collector);
            } else {
                $this->{$property} = $collector;

                if (method_exists($collector, 'listen')) {
                    $collector->listen();
                }
            }
        }

        $this->listening = true;
    }

    /**
     * Copy a fresh instance's state into the one the listeners are bound to.
     */
    private static function resetFrom(object $target, object $fresh): void
    {
        (function (object $fresh): void {
            foreach (get_object_vars($fresh) as $property => $value) {
                $this->{$property} = $value;
            }
        })->call($target, $fresh);
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

    public function setRequest(string $method, string $url, array $headers = [], array $payload = [], string $body = '', ?string $ip = null): void
    {
        $this->requestData = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'payload' => $payload,
            'body' => $body,
            'ip' => $ip,
        ];
    }

    public function setAuthUser(mixed $user): void
    {
        if ($user === null) {
            return;
        }

        $this->requestData['auth_user'] = match (true) {
            method_exists($user, 'getAuthIdentifier') => [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
            ],
            is_scalar($user) => $user,
            default => null,
        };
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

    public function setException(Throwable $e): void
    {
        $this->exceptionData = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice(array_map(fn ($frame) => [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? null,
            ], $e->getTrace()), 0, 30),
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

        return self::cap([
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
            'logs' => $this->logCollector->getData(),
            'performance' => [
                'duration_ms' => round($durationMs, 2),
                'memory_peak_mb' => $memoryPeakMb,
                'query_count' => count($queries),
                'query_time_ms' => round($queryTimeMs, 2),
            ],
        ]);
    }

    private const array LIST_SECTIONS = ['queries', 'events', 'views', 'cache', 'mail', 'http_client', 'jobs', 'models', 'commands', 'scheduled_tasks', 'notifications', 'logs'];

    /**
     * Keep at most `limits.max_entries` per collector and shorten long strings.
     * Totals (query_count, n_plus_one) are computed before this, from the full data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function cap(array $data): array
    {
        $maxEntries = (int) config('digdeep.limits.max_entries', 500);
        $maxLength = (int) config('digdeep.limits.max_string_length', 10_000);

        foreach (self::LIST_SECTIONS as $section) {
            if (count($data[$section]) > $maxEntries) {
                $data['_dropped'][$section] = count($data[$section]) - $maxEntries;
                $data[$section] = array_slice($data[$section], 0, $maxEntries);
            }

            array_walk_recursive($data[$section], function (mixed &$value) use ($maxLength): void {
                if (is_string($value) && strlen($value) > $maxLength) {
                    $value = mb_strcut($value, 0, $maxLength).'... [truncated]';
                }
            });
        }

        return $data;
    }
}
