<?php

namespace LaravelPlus\DigDeep\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

#[IsReadOnly]
#[Description('List and filter captured DigDeep profiles. Returns summary data (no full query/event details). Use get-profile for full detail.')]
class ListProfiles extends Tool
{
    public function __construct(private DigDeepStorage $storage) {}

    public function handle(Request $request): Response
    {
        $criteria = array_filter([
            'status_min' => $request->get('status_min'),
            'status_max' => $request->get('status_max'),
            'duration_min' => $request->get('duration_min'),
            'duration_max' => $request->get('duration_max'),
            'method' => $request->get('method'),
            'route' => $request->get('route'),
            'tag' => $request->get('tag'),
        ], fn ($v) => $v !== null);

        $limit = (int) ($request->get('limit') ?? 50);

        if (empty($criteria)) {
            $profiles = $this->storage->all();
        } else {
            $profiles = $this->storage->filter($criteria);
        }

        $profiles = array_slice($profiles, 0, $limit);

        return Response::json([
            'count' => count($profiles),
            'profiles' => $profiles,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status_min' => $schema->integer()->description('Minimum HTTP status code to filter by.'),
            'status_max' => $schema->integer()->description('Maximum HTTP status code to filter by.'),
            'duration_min' => $schema->number()->description('Minimum duration in ms.'),
            'duration_max' => $schema->number()->description('Maximum duration in ms.'),
            'method' => $schema->string()->description('HTTP method filter (GET, POST, etc.).'),
            'route' => $schema->string()->description('URL pattern to filter by (substring match).'),
            'tag' => $schema->string()->description('Tag to filter by (substring match).'),
            'limit' => $schema->integer()->description('Max number of profiles to return. Default 50.'),
        ];
    }
}
