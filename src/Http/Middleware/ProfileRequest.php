<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LaravelPlus\DigDeep\DigDeep;
use LaravelPlus\DigDeep\DigDeepCollector;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;
use LaravelPlus\DigDeep\Support\Redactor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ProfileRequest
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

        $sampleRate = (float) config('digdeep.sample_rate', 1.0);
        if ($sampleRate < 1.0 && mt_rand() / mt_getrandmax() >= $sampleRate) {
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
            $requestBody = mb_substr($this->redactBody($request, $contentType), 0, 8192);
        }

        $this->collector->setRequest(
            $request->method(),
            '/'.$path,
            Redactor::headers($request->headers->all()),
            Redactor::keys($request->all()),
            $requestBody,
            $request->ip(),
        );

        $this->collector->setAjax($isAjax);

        $middlewareStart = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->collector->setException($e);

            throw $e;
        }

        // Downloads and streams (SSE, StreamedJsonResponse) have no capturable body; don't store them.
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
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
        if ($responseContent && config('digdeep.capture_response_bodies', true) && (str_contains($responseContentType, 'json') || str_contains($responseContentType, 'text') || str_contains($responseContentType, 'xml') || str_contains($responseContentType, 'html'))) {
            $responseBody = mb_substr(Redactor::body($responseContent, $responseContentType), 0, 16384);
        }

        $this->collector->setResponse(
            $response->getStatusCode(),
            Redactor::headers($response->headers->all()),
            mb_strlen($responseContent),
            $responseBody,
        );

        // Capture exception if response indicates an error
        if ($response->getStatusCode() >= 400 && isset($response->exception)) {
            $this->collector->setException($response->exception);
        }

        // Capture authenticated user (available after auth middleware)
        $this->collector->setAuthUser($request->user());

        // Collect Inertia data from the response
        $this->collector->collectInertia($response);

        $this->collector->markLifecyclePhase('response_ready');

        $profileData = $this->collector->finishRequest();

        $totalElapsed = (microtime(true) - $profilingStart) * 1000;
        $requestDuration = $profileData['performance']['duration_ms'] ?? 0;
        // Overhead is the extra time DigDeep added on top of the real request duration.
        $profileData['performance']['profiling_overhead_ms'] = round(max(0.0, $totalElapsed - $requestDuration), 2);

        $profileData['session'] = $this->collectSession($request);
        $profileData['dev_fixtures'] = DigDeep::flushFixtures();

        $profileId = Str::uuid()->toString();
        $this->storage->store($profileId, $profileData);

        if (config('digdeep.show_debugbar', true) && ! $isAjax && DigDeep::check($request)) {
            $response = $this->injectDebugbar($response, $profileData, $profileId);
        }

        return $response;
    }

    private function injectDebugbar(Response $response, array $profileData, string $profileId): Response
    {
        $contentType = $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! $content || ! str_contains($content, '</body>')) {
            return $response;
        }

        try {
            $debugbarHtml = view('digdeep::debugbar', [
                'profile' => $profileData,
                'profileId' => $profileId,
            ])->render();

            // Preserve the original view reference so Inertia test assertions still work.
            // setContent() replaces $response->original, which breaks assertViewHas('page').
            /** @var \Illuminate\Http\Response $response */
            $original = $response->original ?? null;
            $response->setContent(str_replace('</body>', $debugbarHtml.'</body>', $content));
            if ($original !== null) {
                $response->original = $original;
            }
        } catch (Throwable) {
            // Never break the response if debugbar fails to render
        }

        return $response;
    }

    /** @return array<string, array{type: string, value: string}> */
    private function collectSession(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        try {
            $session = $request->session();

            if (! $session->isStarted()) {
                return [];
            }

            $data = [];

            foreach ($session->all() as $key => $value) {
                if (str_starts_with((string) $key, '_')) {
                    continue;
                }

                if (Redactor::isSensitiveKey((string) $key)) {
                    $data[(string) $key] = ['type' => gettype($value), 'value' => '[redacted]'];

                    continue;
                }

                if (is_array($value)) {
                    $value = Redactor::keys($value);
                }

                $data[(string) $key] = [
                    'type' => gettype($value),
                    'value' => is_scalar($value)
                        ? (string) $value
                        : mb_substr((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 200),
                ];
            }

            return $data;
        } catch (Throwable) {
            return [];
        }
    }

    private function redactBody(Request $request, string $contentType): string
    {
        if (str_contains($contentType, 'json')) {
            $decoded = json_decode((string) $request->getContent(), true);

            return is_array($decoded) ? (string) json_encode(Redactor::keys($decoded)) : '[body omitted]';
        }

        if (str_contains($contentType, 'form')) {
            return (string) json_encode(Redactor::keys($request->post()));
        }

        // xml/text bodies cannot be reliably scrubbed
        return '[body omitted]';
    }
}
