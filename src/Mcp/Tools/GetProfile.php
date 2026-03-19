<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;
use Override;

#[IsReadOnly]
#[Description('Get full profile detail by ID, including all queries, events, views, cache operations, performance metrics, and more.')]
final class GetProfile extends Tool
{
    public function __construct(private DigDeepStorage $storage) {}

    #[Override]
    public function handle(Request $request): Response
    {
        $id = $request->get('id');

        if (!$id) {
            return Response::error('The "id" parameter is required.');
        }

        $profile = $this->storage->find($id);

        if (!$profile) {
            return Response::error("Profile not found: {$id}");
        }

        return Response::json($profile);
    }

    /**
     * @return array<string, JsonSchema>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The profile UUID to retrieve.')->required(),
        ];
    }
}
