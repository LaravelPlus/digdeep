<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LaravelPlus\DigDeep\Events\ThresholdExceeded;
use LaravelPlus\DigDeep\Models\DigDeepProfile;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

function storeProfile(string $id, array $overrides = []): void
{
    app(DigDeepStorage::class)->store($id, array_replace_recursive([
        'request' => ['method' => 'GET', 'url' => '/users'],
        'response' => ['status_code' => 200],
        'performance' => ['duration_ms' => 10, 'memory_peak_mb' => 4, 'query_count' => 1, 'query_time_ms' => 1],
    ], $overrides));
}

it('stores and finds a profile', function (): void {
    storeProfile('p1');

    expect(app(DigDeepStorage::class)->find('p1'))
        ->toMatchArray(['id' => 'p1', 'method' => 'GET', 'url' => '/users', 'status_code' => 200])
        ->and(app(DigDeepStorage::class)->find('missing'))->toBeNull();
});

it('counts route visits', function (): void {
    storeProfile('a');
    storeProfile('b');

    expect(app(DigDeepStorage::class)->topRoutes()[0])->toMatchArray(['url' => '/users', 'visit_count' => 2]);
});

it('prunes to max_profiles', function (): void {
    $this->app->forgetInstance(DigDeepStorage::class);
    $this->app->singleton(DigDeepStorage::class, fn () => new DigDeepStorage(3));

    foreach (range(1, 5) as $i) {
        storeProfile("p{$i}");
    }

    expect(DigDeepProfile::query()->count())->toBe(3);
});

it('tags and fires an event when thresholds are exceeded', function (): void {
    Event::fake([ThresholdExceeded::class]);
    config(['digdeep.thresholds.duration_ms' => 100]);

    storeProfile('slow', ['performance' => ['duration_ms' => 900]]);

    expect(app(DigDeepStorage::class)->find('slow')['tags'])->toContain('slow');
    Event::assertDispatched(ThresholdExceeded::class);
});

it('filters by status, method, route and tag', function (): void {
    storeProfile('ok');
    storeProfile('err', ['request' => ['method' => 'POST', 'url' => '/orders'], 'response' => ['status_code' => 500]]);
    app(DigDeepStorage::class)->updateTags('err', 'checkout');

    $storage = app(DigDeepStorage::class);
    $ids = fn (array $c) => collect($storage->filter($c))->pluck('id')->all();

    expect($ids(['status_min' => 500]))->toBe(['err'])
        ->and($ids(['method' => 'post']))->toBe(['err'])
        ->and($ids(['route' => 'orders']))->toBe(['err'])
        ->and($ids(['tag' => 'checkout']))->toBe(['err']);
});

it('bulk tags, bulk deletes and clears', function (): void {
    storeProfile('a');
    storeProfile('b');
    storeProfile('c');
    $storage = app(DigDeepStorage::class);

    expect($storage->bulkTag(['a', 'b'], 'review'))->toBe(2)
        ->and($storage->find('a')['tags'])->toContain('review')
        ->and($storage->bulkDelete(['a']))->toBe(1);

    $storage->clear();

    expect(DigDeepProfile::query()->count())->toBe(0);
});

it('reports stats', function (): void {
    storeProfile('a', ['performance' => ['duration_ms' => 10]]);
    storeProfile('b', ['performance' => ['duration_ms' => 30]]);

    expect(app(DigDeepStorage::class)->stats())->toMatchArray(['total' => 2]);
});

it('truncates oversized profiles', function (): void {
    config(['digdeep.max_profile_size_kb' => 1]);

    storeProfile('big', ['request' => ['body' => str_repeat('x', 50_000)]]);

    expect(strlen(json_encode(app(DigDeepStorage::class)->find('big')['data'])))->toBeLessThan(50_000);
});
