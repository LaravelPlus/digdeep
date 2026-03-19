<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;
use Override;

#[Description('Current DigDeep configuration values including enabled state, thresholds, ignored paths, and max profile settings.')]
#[Uri('digdeep://config')]
#[MimeType('application/json')]
final class DigDeepConfig extends Resource
{
    #[Override]
    public function handle(Request $request): Response
    {
        return Response::json(config('digdeep', []));
    }
}
