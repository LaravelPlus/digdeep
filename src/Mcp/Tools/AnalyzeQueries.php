<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelPlus\DigDeep\Analyzers\QueryAnalyzer;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;
use Throwable;

#[IsReadOnly]
#[Description('Analyze queries from a profile for N+1 patterns, SELECT * usage, and missing indexes. Returns actionable optimization hints.')]
final class AnalyzeQueries extends Tool
{
    public function __construct(private DigDeepStorage $storage) {}

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

        $queries = $profile['data']['queries'] ?? [];

        if (empty($queries)) {
            return Response::json([
                'profile_id' => $id,
                'query_count' => 0,
                'hints' => [],
            ]);
        }

        $schema = $this->getSchema();
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

    /**
     * @return array<int, array{name: string, columns: array, indexes: array}>
     */
    private function getSchema(): array
    {
        $schema = [];

        try {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

            foreach ($tables as $table) {
                $columns = DB::select("PRAGMA table_info(\"{$table->name}\")");
                $indexes = DB::select("PRAGMA index_list(\"{$table->name}\")");

                $indexDetails = [];
                foreach ($indexes as $idx) {
                    $idxCols = DB::select("PRAGMA index_info(\"{$idx->name}\")");
                    $indexDetails[] = [
                        'name' => $idx->name,
                        'unique' => (bool) $idx->unique,
                        'columns' => collect($idxCols)->pluck('name')->all(),
                    ];
                }

                $schema[] = [
                    'name' => $table->name,
                    'columns' => collect($columns)->map(fn ($c) => [
                        'name' => $c->name,
                        'type' => $c->type,
                        'nullable' => !$c->notnull,
                        'pk' => (bool) $c->pk,
                        'default' => $c->dflt_value,
                    ])->all(),
                    'indexes' => $indexDetails,
                ];
            }
        } catch (Throwable) {
            // Schema introspection may fail on non-SQLite DBs
        }

        return $schema;
    }
}
