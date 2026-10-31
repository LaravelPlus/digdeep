<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

beforeEach(function (): void {
    $this->profile = $this->profile('/orders', function () {
        foreach (range(1, 5) as $i) {
            DB::select("select * from digdeep_profiles where id = '{$i}'");
        }

        return response('<html><body>orders</body></html>')->header('Content-Type', 'text/html');
    });
});

it('renders every dashboard page', function (string $uri): void {
    $this->get($uri)->assertOk();
})->with([
    '/digdeep', '/digdeep/pipeline', '/digdeep/security', '/digdeep/audits', '/digdeep/urls',
    '/digdeep/database', '/digdeep/errors', '/digdeep/compare', '/digdeep/profiler', '/digdeep/export',
    '/digdeep/trends', '/digdeep/performance', '/digdeep/cache', '/digdeep/logs',
]);

it('renders the dashboard with no profiles', function (): void {
    $this->post('/digdeep/api/clear');

    $this->get('/digdeep')->assertOk();
});

it('renders a profile and 404s for unknown ones', function (): void {
    $this->get('/digdeep/profile/'.$this->profile->id)->assertOk()->assertSee('/orders');
    $this->get('/digdeep/profile/does-not-exist')->assertNotFound();
});

it('rejects log path traversal', function (): void {
    $this->get('/digdeep/logs?file=../../.env')->assertOk()->assertDontSee('APP_KEY');
});

/** Store a profile with every kind of data the dashboard pages know how to summarise. */
function richProfile(string $id, array $overrides = []): void
{
    app(DigDeepStorage::class)->store($id, array_replace_recursive([
        'request' => ['method' => 'POST', 'url' => '/items/42'],
        'response' => ['status_code' => 500, 'headers' => []],
        'route' => ['name' => 'items.store', 'action' => 'ItemController@store', 'middleware' => ['web']],
        'performance' => ['duration_ms' => 1200, 'memory_peak_mb' => 80, 'query_count' => 30, 'query_time_ms' => 300],
        'queries' => [
            ['sql' => 'select * from items where id = 1; drop table items', 'time_ms' => 9, 'caller' => 'app/A.php:1'],
            ['sql' => 'insert into items (a) values (1)', 'time_ms' => 1, 'caller' => 'app/A.php:2'],
            ['sql' => 'update items set a = 2', 'time_ms' => 1, 'caller' => 'app/A.php:3'],
            ['sql' => 'create table x (id int)', 'time_ms' => 1, 'caller' => 'app/A.php:4'],
            ['sql' => 'pragma table_info(items)', 'time_ms' => 1, 'caller' => 'app/A.php:5'],
        ],
        'cache' => [
            ['type' => 'hit', 'key' => 'user:1'],
            ['type' => 'miss', 'key' => 'config.a'],
            ['type' => 'miss', 'key' => 'config.a'],
            ['type' => 'write', 'key' => 'plain'],
        ],
        'exception' => ['class' => 'RuntimeException', 'message' => 'boom', 'code' => 0, 'file' => 'app/A.php', 'line' => 1, 'trace' => [], 'previous' => null],
        'lifecycle' => ['phases' => [['name' => 'boot', 'duration_ms' => 1]]],
    ], $overrides));
}

it('summarises rich profiles across the analysis pages', function (string $uri): void {
    richProfile('r1');
    richProfile('r2', ['request' => ['method' => 'GET', 'url' => '/items/7'], 'response' => ['status_code' => 200, 'headers' => ['x-content-type-options' => 'nosniff']], 'route' => ['name' => null, 'action' => null, 'middleware' => []], 'performance' => ['duration_ms' => 5], 'exception' => null, 'lifecycle' => ['phases' => []]]);
    richProfile('r3', ['request' => ['url' => '/items/9'], 'route' => ['name' => null, 'action' => 'ItemController@store', 'middleware' => ['csrf']]]);
    richProfile('r4', ['request' => ['url' => '/items'], 'route' => ['name' => null, 'action' => null]]);
    richProfile('r5', ['request' => ['url' => '/items/42'], 'exception' => null]);
    richProfile('r6', ['request' => ['url' => '/items/43'], 'exception' => null, 'response' => ['status_code' => 500]]);
    Route::post('/items', fn () => 'ok')->name('items.store');
    Route::get('/items/{item}', fn () => 'ok');

    $this->get($uri)->assertOk();
})->with(['/digdeep', '/digdeep/pipeline', '/digdeep/security', '/digdeep/audits', '/digdeep/errors', '/digdeep/database', '/digdeep/cache', '/digdeep/performance']);

it('lists, reads and tolerates empty log files', function (): void {
    $dir = storage_path('logs');
    File::ensureDirectoryExists($dir);
    File::put("$dir/digdeep-test.log", "[2026-01-01 10:00:00] testing.ERROR: Boom {\"a\":1}\n[stacktrace]\n#0 x\n[2026-01-01 10:00:01] testing.INFO: plain\n");
    File::put("$dir/digdeep-empty.log", '');

    $this->get('/digdeep/logs?file=digdeep-test.log')->assertOk()->assertSee('Boom')->assertSee('plain');
    $this->get('/digdeep/logs?file=digdeep-empty.log')->assertOk();
    $this->get('/digdeep/logs')->assertOk();

    File::delete(["$dir/digdeep-test.log", "$dir/digdeep-empty.log"]);
});
