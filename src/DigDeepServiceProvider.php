<?php

namespace LaravelPlus\DigDeep;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use LaravelPlus\DigDeep\Commands\ClearCommand;
use LaravelPlus\DigDeep\Commands\PruneCommand;
use LaravelPlus\DigDeep\Commands\StatusCommand;
use LaravelPlus\DigDeep\Mcp\Servers\DigDeepServer;
use LaravelPlus\DigDeep\Middleware\ProfileRequest;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

class DigDeepServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/digdeep.php', 'digdeep');

        if (! $this->isEnabled()) {
            return;
        }

        $this->app->singleton(DigDeepStorage::class, function () {
            return new DigDeepStorage(
                config('digdeep.max_profiles'),
            );
        });

        $this->app->singleton(DigDeepCollector::class);
    }

    public function boot(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'digdeep');

        $this->publishes([
            __DIR__.'/../config/digdeep.php' => config_path('digdeep.php'),
        ], 'digdeep-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/digdeep'),
        ], 'digdeep-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearCommand::class,
                PruneCommand::class,
                StatusCommand::class,
            ]);
        }

        // Register auto-profiling middleware globally
        if (config('digdeep.auto_profile', true)) {
            $kernel = $this->app->make(Kernel::class);
            $kernel->pushMiddleware(ProfileRequest::class);
        }

        // Register MCP server if laravel/mcp is installed
        if (class_exists(Mcp::class)) {
            Mcp::local('digdeep', DigDeepServer::class);
        }
    }

    private function isEnabled(): bool
    {
        $enabled = config('digdeep.enabled');

        if ($enabled === false) {
            return false;
        }

        if ($enabled === true) {
            return true;
        }

        return $this->app->environment('local', 'testing');
    }
}
