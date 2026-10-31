<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelPlus\DigDeep\Analyzers\QueryAnalyzer;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

#[IsReadOnly]
#[Description('Analyze queries from a profile for N+1 patterns, SELECT * usage, and missing indexes. Returns actionable optimization hints.')]
final class AnalyzeQueries extends Tool
{
    public function __construct(private DigDeepStorage $storage) {}

    public function handle(Request $request): Response
    {
        $id = $request->get('id');

        if (! $id) {
            return Response::error('The "id" parameter is required.');
        }

        $profile = $this->storage->find($id);

        if (! $profile) {
            return Response::error("Profile not found: {$id}");
        }

        $queries = $profile['data']['queries'] ?? [];

        if (empty($queries)) {
            return Response::json([
                'profile_id' => $id,
                'query_count' => 0,
                'hints' => [],
            ]);
        }

        $schema = QueryAnalyzer::schema();
        $hints = QueryAnalyzer::generateHints($queries, $schema);

        return Response::json([
            'profile_id' => $id,
            'query_count' => count($queries),
            'hints' => $hints,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The profile UUID to analyze queries for.')->required(),
        ];
    }
}
