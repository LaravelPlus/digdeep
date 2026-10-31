<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\McpServiceProvider;
use LaravelPlus\DigDeep\DigDeep;
use LaravelPlus\DigDeep\DigDeepServiceProvider;
use LaravelPlus\DigDeep\Models\DigDeepProfile;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** Environment the app boots in; tests override to check production behaviour. */
    protected string $appEnv = 'testing';

    protected function setUp(): void
    {
        parent::setUp();

        DigDeep::auth(fn (): bool => false);
    }

    /** Laravel 12 calls it ValidateCsrfToken, 13 renamed it PreventRequestForgery. */
    protected function withoutCsrf(): static
    {
        return $this->withoutMiddleware([
            'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
            'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
        ]);
    }

    /**
     * Hit a throwaway route through the profiling middleware and return the stored profile row.
     */
    protected function profile(string $uri = '/probe', ?\Closure $action = null, string $method = 'get'): DigDeepProfile
    {
        Route::$method(parse_url($uri, PHP_URL_PATH), $action ?? fn () => 'ok');
        $this->{$method}($uri);

        return DigDeepProfile::query()->where('url', parse_url($uri, PHP_URL_PATH))->latest()->firstOrFail();
    }

    protected function getPackageProviders($app): array
    {
        return [McpServiceProvider::class, DigDeepServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['env'] = $this->appEnv;
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('digdeep.enabled', true);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }
}
