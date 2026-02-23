<?php

namespace LaravelPlus\DigDeep;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
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
                config('digdeep.storage_path'),
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

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'digdeep');

        $this->publishes([
            __DIR__.'/../config/digdeep.php' => config_path('digdeep.php'),
        ], 'digdeep-config');

        // Register auto-profiling middleware globally
        if (config('digdeep.auto_profile', true)) {
            $kernel = $this->app->make(Kernel::class);
            $kernel->pushMiddleware(ProfileRequest::class);
        }
    }

    private function isEnabled(): bool
    {
        if (! config('digdeep.enabled', true)) {
            return false;
        }

        // Explicitly set via env var — honour regardless of environment
        if (env('DIGDEEP_ENABLED') !== null) {
            return (bool) env('DIGDEEP_ENABLED');
        }

        return $this->app->environment('local', 'testing');
    }
}
