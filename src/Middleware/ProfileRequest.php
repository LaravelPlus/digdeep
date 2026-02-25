<?php

namespace LaravelPlus\DigDeep\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LaravelPlus\DigDeep\DigDeepCollector;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;
use Symfony\Component\HttpFoundation\Response;

class ProfileRequest
{
    public function __construct(
        private DigDeepCollector $collector,
        private DigDeepStorage $storage,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        foreach (config('digdeep.ignored_paths', []) as $ignored) {
            if (str_starts_with($path, $ignored)) {
                return $next($request);
            }
        }

        // Skip if already being profiled via sub-request trigger
        if ($request->headers->has('X-DigDeep-Profile')) {
            return $next($request);
        }

        $isAjax = $request->ajax()
            || $request->wantsJson()
            || $request->headers->has('X-Inertia');

        $profilingStart = microtime(true);

        $this->collector->startRequest();

        // Capture request body (truncated for storage)
        $requestBody = '';
        $contentType = $request->header('Content-Type', '');
        if ($request->getContent() && (str_contains($contentType, 'json') || str_contains($contentType, 'text') || str_contains($contentType, 'xml') || str_contains($contentType, 'form'))) {
            $requestBody = mb_substr((string) $request->getContent(), 0, 8192);
        }

        $this->collector->setRequest(
            $request->method(),
            '/'.$path,
            $this->sanitizeHeaders($request->headers->all()),
            $request->except(['password', 'password_confirmation']),
            $requestBody,
        );

        $this->collector->setAjax($isAjax);

        $middlewareStart = microtime(true);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->collector->setException($e);

            throw $e;
        }

        $this->collector->markLifecyclePhase('middleware_done');

        $route = $request->route();

        if ($route) {
            $middlewareList = $route->gatherMiddleware();

            $this->collector->setRoute([
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'parameters' => $route->parameters(),
                'middleware' => $middlewareList,
            ]);

            // Record middleware timing (aggregate — estimated per-middleware)
            $middlewareDuration = (microtime(true) - $middlewareStart) * 1000;
            $middlewareCollector = $this->collector->getMiddlewareCollector();
            $middlewareCollector->setTotalPipelineTime($middlewareDuration);

            foreach ($middlewareList as $mw) {
                $name = is_string($mw) ? $mw : get_class($mw);
                $perMiddleware = count($middlewareList) > 0 ? $middlewareDuration / count($middlewareList) : 0;
                $middlewareCollector->recordMiddleware($name, $perMiddleware, true);
            }
        }

        // Capture response body (truncated for storage)
        $responseContent = $response->getContent() ?: '';
        $responseBody = '';
        $responseContentType = $response->headers->get('Content-Type', '');
        if ($responseContent && (str_contains($responseContentType, 'json') || str_contains($responseContentType, 'text') || str_contains($responseContentType, 'xml') || str_contains($responseContentType, 'html'))) {
            $responseBody = mb_substr($responseContent, 0, 16384);
        }

        $this->collector->setResponse(
            $response->getStatusCode(),
            $this->sanitizeHeaders($response->headers->all()),
            strlen($responseContent),
            $responseBody,
        );

        // Capture exception if response indicates an error
        if ($response->getStatusCode() >= 400 && $response->exception ?? null) {
            $this->collector->setException($response->exception);
        }

        // Collect Inertia data from the response
        $this->collector->collectInertia($response);

        $this->collector->markLifecyclePhase('response_ready');

        $profileData = $this->collector->finishRequest();

        $profilingOverhead = (microtime(true) - $profilingStart) * 1000;
        $requestDuration = $profileData['performance']['duration_ms'] ?? 0;
        $profileData['performance']['profiling_overhead_ms'] = round($profilingOverhead - $requestDuration, 2);

        $this->storage->store(Str::uuid()->toString(), $profileData);

        return $response;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'set-cookie'];

        foreach ($sensitive as $key) {
            if (isset($headers[$key])) {
                $headers[$key] = ['[redacted]'];
            }
        }

        return $headers;
    }
}
