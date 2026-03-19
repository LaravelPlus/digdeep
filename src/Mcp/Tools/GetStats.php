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
#[Description('Get aggregate statistics across all captured profiles: total count, average/min/max duration, average queries, and average memory usage.')]
final class GetStats extends Tool
{
    public function __construct(private DigDeepStorage $storage) {}

    #[Override]
    public function handle(Request $request): Response
    {
        return Response::json($this->storage->stats());
    }

    /**
     * @return array<string, JsonSchema>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
