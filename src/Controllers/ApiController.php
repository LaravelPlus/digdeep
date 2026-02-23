<?php

namespace LaravelPlus\DigDeep\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use LaravelPlus\DigDeep\DigDeepCollector;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

class ApiController extends Controller
{
    public function __construct(
        private DigDeepStorage $storage,
        private DigDeepCollector $collector,
    ) {}

    public function trigger(Request $request): JsonResponse
    {
        $url = trim($request->input('url', '/'));

        // Strip scheme + host so full URLs like http://127.0.0.1:8000/foo become /foo
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            $url = ($parsed['path'] ?? '/').
                (isset($parsed['query']) ? '?'.$parsed['query'] : '').
                (isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');
        }

        if (! str_starts_with($url, '/')) {
            $url = '/'.$url;
        }

        $method = strtoupper($request->input('method', 'GET'));

        $subRequest = Request::create($url, $method);
        $subRequest->headers->set('X-DigDeep-Profile', '1');

        $this->collector->startRequest();

        $this->collector->setRequest(
            $method,
            $url,
        );

        try {
            $response = app()->handle($subRequest);
        } catch (\Throwable $e) {
            $this->collector->setException($e);
            $this->collector->setResponse(500, [], 0);

            $profileData = $this->collector->finishRequest();
            $profileId = Str::uuid()->toString();
            $this->storage->store($profileId, $profileData);

            $performance = $profileData['performance'] ?? [];

            return response()->json([
                'profile_id' => $profileId,
                'redirect' => '/digdeep/profile/'.$profileId,
                'status_code' => 500,
                'duration_ms' => round($performance['duration_ms'] ?? 0, 1),
                'query_count' => $performance['query_count'] ?? 0,
                'memory_peak_mb' => round($performance['memory_peak_mb'] ?? 0, 1),
            ]);
        }

        $route = $subRequest->route();

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

        $profileId = Str::uuid()->toString();
        $this->storage->store($profileId, $profileData);

        $performance = $profileData['performance'] ?? [];

        return response()->json([
            'profile_id' => $profileId,
            'redirect' => '/digdeep/profile/'.$profileId,
            'status_code' => $profileData['response']['status_code'] ?? 0,
            'duration_ms' => round($performance['duration_ms'] ?? 0, 1),
            'query_count' => $performance['query_count'] ?? 0,
            'memory_peak_mb' => round($performance['memory_peak_mb'] ?? 0, 1),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->storage->delete($id);

        return response()->json(['status' => 'ok']);
    }

    public function clear(): JsonResponse
    {
        $this->storage->clear();

        return response()->json(['status' => 'ok']);
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
