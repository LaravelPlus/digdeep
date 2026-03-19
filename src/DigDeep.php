<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep;

use Closure;
use Illuminate\Http\Request;

final class DigDeep
{
    /**
     * The callback that should be used to authenticate DigDeep users.
     *
     * @var (Closure(Request): bool)|null
     */
    private static ?Closure $authUsing = null;

    /**
     * Register the DigDeep authentication callback.
     */
    public static function auth(Closure $callback): void
    {
        self::$authUsing = $callback;
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
        if (self::$authUsing) {
            return (self::$authUsing)($request);
        }

        return false;
    }
}
