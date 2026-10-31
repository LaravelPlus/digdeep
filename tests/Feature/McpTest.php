<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LaravelPlus\DigDeep\Mcp\Resources\DigDeepConfig;
use LaravelPlus\DigDeep\Mcp\Resources\DigDeepOverview;
use LaravelPlus\DigDeep\Mcp\Servers\DigDeepServer;
use LaravelPlus\DigDeep\Mcp\Tools\AnalyzeQueries;
use LaravelPlus\DigDeep\Mcp\Tools\GetPerformance;
use LaravelPlus\DigDeep\Mcp\Tools\GetProfile;
use LaravelPlus\DigDeep\Mcp\Tools\GetSlowestRoutes;
use LaravelPlus\DigDeep\Mcp\Tools\GetStats;
use LaravelPlus\DigDeep\Mcp\Tools\GetTrends;
use LaravelPlus\DigDeep\Mcp\Tools\ListProfiles;
use LaravelPlus\DigDeep\Mcp\Tools\ProfileUrl;
use LaravelPlus\DigDeep\Models\DigDeepProfile;

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

it('serves the overview and config resources', function (): void {
    DigDeepServer::resource(DigDeepOverview::class)->assertOk()->assertSee('DigDeep Profiler');
    DigDeepServer::resource(DigDeepConfig::class)->assertOk()->assertSee('thresholds');
});

it('reports missing parameters and profiles', function (string $tool, array $args, string $message): void {
    DigDeepServer::tool($tool, $args)->assertHasErrors([$message]);
})->with([
    'get without id' => [GetProfile::class, ['id' => ''], 'The "id" parameter is required.'],
    'get unknown' => [GetProfile::class, ['id' => 'nope'], 'Profile not found: nope'],
    'analyze without id' => [AnalyzeQueries::class, ['id' => ''], 'The "id" parameter is required.'],
    'analyze unknown' => [AnalyzeQueries::class, ['id' => 'nope'], 'Profile not found: nope'],
    'profile without url' => [ProfileUrl::class, ['url' => ''], 'The "url" parameter is required.'],
]);

it('analyzes a profile with and without queries', function (): void {
    DigDeepServer::tool(AnalyzeQueries::class, ['id' => $this->id])->assertSee('"query_count":0');

    $id = $this->profile('/q', function () {
        DB::select('select * from digdeep_profiles');

        return 'ok';
    })->id;

    DigDeepServer::tool(AnalyzeQueries::class, ['id' => $id])->assertSee('hints');
});

it('filters profiles, trends and performance', function (): void {
    $this->profile('/boom', fn () => throw new RuntimeException('boom'));

    DigDeepServer::tool(ListProfiles::class, ['status_min' => 500, 'method' => 'GET', 'limit' => 1])->assertSee('"count":1');

    foreach (['hour', 'day', 'week'] as $range) {
        DigDeepServer::tool(GetTrends::class, ['route' => 'boom', 'range' => $range])->assertSee('/boom');
        DigDeepServer::tool(GetPerformance::class, ['range' => $range])->assertSee('"total":2');
    }
});

it('profiles a url that blows up and uses the requested method', function (): void {
    Route::post('/mcp-boom', fn () => throw new RuntimeException('kaput'));
    $this->withoutExceptionHandling();

    DigDeepServer::tool(ProfileUrl::class, ['url' => '/mcp-boom', 'method' => 'post'])->assertOk()->assertSee('500')->assertSee('POST');
});

it('profiles a url that matches no route', function (): void {
    DigDeepServer::tool(ProfileUrl::class, ['url' => '/nowhere'])->assertOk()->assertSee('"status_code":404');
});

it('describes the input of every tool', function (string $tool): void {
    expect(app($tool)->schema(new JsonSchemaTypeFactory))->toBeArray();
})->with([ListProfiles::class, GetStats::class, GetProfile::class, AnalyzeQueries::class, GetTrends::class, GetPerformance::class, GetSlowestRoutes::class, ProfileUrl::class]);

it('reports empty performance without profiles', function (): void {
    DigDeepProfile::query()->delete();

    DigDeepServer::tool(GetPerformance::class)->assertSee('"global":[]');
});

it('includes the matched route when profiling a url', function (): void {
    Route::get('/mcp-named', fn () => 'ok')->name('mcp.named');

    DigDeepServer::tool(ProfileUrl::class, ['url' => '/mcp-named'])->assertOk();

    expect(DigDeepProfile::query()->where('url', '/mcp-named')->first()->data['route']['name'])->toBe('mcp.named');
});
