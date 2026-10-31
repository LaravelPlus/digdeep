<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\DigDeep\DigDeep;
use LaravelPlus\DigDeep\DigDeepServiceProvider;
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

    protected function getPackageProviders($app): array
    {
        return [DigDeepServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['env'] = $this->appEnv;
        $app['config']->set('digdeep.enabled', true);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }
}
