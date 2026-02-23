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

        $this->collector->startRequest();

        $this->collector->setRequest(
            $request->method(),
            '/'.$path,
            $this->sanitizeHeaders($request->headers->all()),
            $request->except(['password', 'password_confirmation']),
        );

        $this->collector->setAjax($isAjax);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->collector->setException($e);

            throw $e;
        }

        $route = $request->route();

        if ($route) {
            $this->collector->setRoute([
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'parameters' => $route->parameters(),
                'middleware' => $route->gatherMiddleware(),
            ]);
        }

        $this->collector->setResponse(
            $response->getStatusCode(),
            $this->sanitizeHeaders($response->headers->all()),
            strlen($response->getContent() ?: ''),
        );

        // Capture exception if response indicates an error
        if ($response->getStatusCode() >= 400 && $response->exception ?? null) {
            $this->collector->setException($response->exception);
        }

        // Collect Inertia data from the response
        $this->collector->collectInertia($response);

        $profileData = $this->collector->finishRequest();

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
