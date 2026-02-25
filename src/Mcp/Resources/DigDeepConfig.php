<?php

namespace LaravelPlus\DigDeep\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Description('Current DigDeep configuration values including enabled state, thresholds, ignored paths, and max profile settings.')]
#[Uri('digdeep://config')]
#[MimeType('application/json')]
class DigDeepConfig extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::json(config('digdeep', []));
    }
}
