<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use LaravelPlus\DigDeep\DigDeep;
use LaravelPlus\DigDeep\DigDeepCollector;
use LaravelPlus\DigDeep\Models\DigDeepProfile;
use Symfony\Component\HttpFoundation\Response;

it('records request, response and performance', function (): void {
    $data = $this->profile('/hello', fn () => response('hi', 201))->data;

    expect($data['request'])->toMatchArray(['method' => 'GET', 'url' => '/hello'])
        ->and($data['response']['status_code'])->toBe(201)
        ->and($data['performance'])->toHaveKeys(['duration_ms', 'memory_peak_mb', 'query_count']);
});

it('collects queries with their caller', function (): void {
    $data = $this->profile('/q', function () {
        DB::select('select 1 as one');

        return 'ok';
    })->data;

    expect(collect($data['queries'])->pluck('sql'))->toContain('select 1 as one')
        ->and($data['performance']['query_count'])->toBeGreaterThanOrEqual(1);
});

it('collects cache, logs, mail and http client calls', function (): void {
    Http::fake(['example.test/*' => Http::response(['ok' => true])]);
    Mail::fake();

    $data = $this->profile('/all', function () {
        Cache::put('k', 'v', 60);
        Cache::get('k');
        Log::warning('careful', ['a' => 1]);
        Http::get('https://example.test/ping');

        return 'ok';
    })->data;

    expect($data['cache'])->not->toBeEmpty()
        ->and(collect($data['logs'])->pluck('message'))->toContain('careful')
        ->and(collect($data['http_client'])->pluck('url')->implode(' '))->toContain('example.test/ping');
});

it('records exceptions rendered by the handler', function (): void {
    $data = $this->profile('/boom', fn () => throw new RuntimeException('kaput'))->data;

    expect($data['response']['status_code'])->toBe(500)
        ->and($data['exception'])->toMatchArray(['class' => RuntimeException::class, 'message' => 'kaput']);
});

it('handles plain Symfony error responses', function (): void {
    $data = $this->profile('/sym', fn () => new Response('nope', 500))->data;

    expect($data['response']['status_code'])->toBe(500);
});

it('skips ignored paths', function (): void {
    config(['digdeep.ignored_paths' => ['health']]);
    Route::get('/health', fn () => 'ok');

    $this->get('/health')->assertOk();

    expect(DigDeepProfile::query()->count())->toBe(0);
});

it('skips sub-requests marked as already profiled', function (): void {
    Route::get('/inner', fn () => 'ok');

    $this->get('/inner', ['X-DigDeep-Profile' => '1']);

    expect(DigDeepProfile::query()->count())->toBe(0);
});

it('injects the debugbar into html pages only', function (): void {
    Route::get('/page', fn () => response('<html><body>x</body></html>')->header('Content-Type', 'text/html'));
    Route::get('/json', fn () => ['x' => 1]);

    expect($this->get('/page')->getContent())->toContain('__dd')
        ->and($this->get('/json')->getContent())->not->toContain('__dd')
        ->and($this->get('/page', ['X-Requested-With' => 'XMLHttpRequest'])->getContent())->not->toContain('__dd');
});

it('respects show_debugbar=false', function (): void {
    config(['digdeep.show_debugbar' => false]);
    Route::get('/page', fn () => response('<html><body>x</body></html>')->header('Content-Type', 'text/html'));

    expect($this->get('/page')->getContent())->not->toContain('__dd');
});

it('stores dev fixtures and resets them per request', function (): void {
    $data = $this->profile('/fx', function () {
        DigDeep::fixture('login', 'Login', [['field' => 'email', 'label' => 'Email', 'value' => fn () => 'a@b.test']]);

        return 'ok';
    })->data;

    expect($data['dev_fixtures']['login']['fields'][0]['value'])->toBe('a@b.test')
        ->and(DigDeep::flushFixtures())->toBe([]);
});

it('registers listeners once and resets state between requests in long-lived workers', function (): void {
    $collector = app(DigDeepCollector::class);

    $collector->startRequest();
    DB::select('select 1');
    $collector->setException(new RuntimeException('first request'));
    $listeners = count(Event::getListeners(QueryExecuted::class));

    $collector->startRequest();
    DB::select('select 2');
    $data = $collector->finishRequest();

    expect($data['queries'])->toHaveCount(1)
        ->and($data['queries'][0]['sql'])->toBe('select 2')
        ->and($data['exception'])->toBeNull()
        ->and(Event::getListeners(QueryExecuted::class))->toHaveCount($listeners);
});

it('caps entries per collector and shortens long strings, but counts everything', function (): void {
    config(['digdeep.limits.max_entries' => 3, 'digdeep.limits.max_string_length' => 20]);

    $data = $this->profile('/many', function () {
        foreach (range(1, 5) as $i) {
            DB::select('select '.$i);
        }
        Log::info(str_repeat('x', 100));

        return 'ok';
    })->data;

    expect($data['queries'])->toHaveCount(3)
        ->and($data['performance']['query_count'])->toBe(5)
        ->and($data['_dropped']['queries'])->toBe(2)
        ->and($data['logs'][0]['message'])->toBe(str_repeat('x', 20).'... [truncated]');
});
