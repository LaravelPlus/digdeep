<?php

namespace LaravelPlus\DigDeep\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelPlus\DigDeep\DigDeep;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DigDeep::check($request)) {
            abort(403);
        }

        return $next($request);
    }
}
