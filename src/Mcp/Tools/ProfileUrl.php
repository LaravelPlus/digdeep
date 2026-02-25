<?php

namespace LaravelPlus\DigDeep\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use LaravelPlus\DigDeep\DigDeepCollector;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

#[IsDestructive]
#[Description('Trigger on-demand profiling of a URL by making a sub-request. Returns the profile ID and summary metrics.')]
class ProfileUrl extends Tool
{
    public function __construct(private DigDeepStorage $storage) {}

    public function handle(Request $request): Response
    {
        $url = $request->get('url');

        if (! $url) {
            return Response::error('The "url" parameter is required.');
        }

        $method = strtoupper($request->get('method', 'GET'));

        $collector = new DigDeepCollector;
        $collector->startRequest();

        $httpRequest = HttpRequest::create($url, $method);
        $collector->setRequest($method, $url, [], [], '');

        try {
            $httpResponse = app()->handle($httpRequest);

            $collector->setResponse(
                $httpResponse->getStatusCode(),
                $httpResponse->headers->all(),
                strlen($httpResponse->getContent() ?: ''),
                mb_substr($httpResponse->getContent() ?: '', 0, 1024),
            );

            $routeData = [];
            $matchedRoute = app('router')->getRoutes()->match($httpRequest);
            if ($matchedRoute) {
                $routeData = [
                    'name' => $matchedRoute->getName(),
                    'action' => $matchedRoute->getActionName(),
                    'middleware' => $matchedRoute->gatherMiddleware(),
                ];
            }
            $collector->setRoute($routeData);
        } catch (\Throwable $e) {
            $collector->setException($e);
            $collector->setResponse(500, [], 0, '');
            $collector->setRoute([]);
        }

        $profileData = $collector->finishRequest();
        $profileId = Str::uuid()->toString();
        $this->storage->store($profileId, $profileData);

        $performance = $profileData['performance'] ?? [];

        return Response::json([
            'profile_id' => $profileId,
            'url' => $url,
            'method' => $method,
            'status_code' => $profileData['response']['status_code'] ?? 0,
            'duration_ms' => $performance['duration_ms'] ?? 0,
            'memory_peak_mb' => $performance['memory_peak_mb'] ?? 0,
            'query_count' => $performance['query_count'] ?? 0,
            'query_time_ms' => $performance['query_time_ms'] ?? 0,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('The relative URL to profile (e.g. "/", "/dashboard").')->required(),
            'method' => $schema->string()->description('HTTP method to use. Default: GET.'),
        ];
    }
}
