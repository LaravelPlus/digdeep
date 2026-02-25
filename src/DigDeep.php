<?php

namespace LaravelPlus\DigDeep;

use Closure;
use Illuminate\Http\Request;

class DigDeep
{
    /**
     * The callback that should be used to authenticate DigDeep users.
     *
     * @var (Closure(Request): bool)|null
     */
    protected static ?Closure $authUsing = null;

    /**
     * Register the DigDeep authentication callback.
     */
    public static function auth(Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * Determine if the given request can access the DigDeep dashboard.
     */
    public static function check(Request $request): bool
    {
        // Always allow in local/testing
        if (app()->environment('local', 'testing')) {
            return true;
        }

        // Use custom auth callback if registered
        if (static::$authUsing) {
            return (static::$authUsing)($request);
        }

        return false;
    }
}
