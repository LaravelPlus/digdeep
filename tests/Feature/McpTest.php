<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LaravelPlus\DigDeep\Mcp\Servers\DigDeepServer;
use LaravelPlus\DigDeep\Mcp\Tools\AnalyzeQueries;
use LaravelPlus\DigDeep\Mcp\Tools\GetPerformance;
use LaravelPlus\DigDeep\Mcp\Tools\GetProfile;
use LaravelPlus\DigDeep\Mcp\Tools\GetSlowestRoutes;
use LaravelPlus\DigDeep\Mcp\Tools\GetStats;
use LaravelPlus\DigDeep\Mcp\Tools\GetTrends;
use LaravelPlus\DigDeep\Mcp\Tools\ListProfiles;
use LaravelPlus\DigDeep\Mcp\Tools\ProfileUrl;

beforeEach(function (): void {
    $this->id = $this->profile('/orders')->id;
});

it('answers every read-only tool', function (string $tool, array $args): void {
    DigDeepServer::tool($tool, $args)->assertOk()->assertHasNoErrors();
})->with([
    'list' => [ListProfiles::class, []],
    'stats' => [GetStats::class, []],
    'performance' => [GetPerformance::class, []],
    'trends' => [GetTrends::class, []],
    'slowest' => [GetSlowestRoutes::class, ['limit' => 5]],
]);

it('returns a stored profile and analyzes its queries', function (): void {
    DigDeepServer::tool(GetProfile::class, ['id' => $this->id])->assertOk()->assertSee('/orders');
    DigDeepServer::tool(AnalyzeQueries::class, ['id' => $this->id])->assertOk();
});

it('profiles a url', function (): void {
    Route::get('/mcp-target', fn () => 'ok');

    DigDeepServer::tool(ProfileUrl::class, ['url' => '/mcp-target'])->assertOk()->assertSee('200');
});
