<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Override;
use Stringable;

final class ListSourceFilesTool implements Tool
{
    private const array ALLOWED_PREFIXES = ['app/', 'resources/js/'];

    /**
     * Get the description of the tool's purpose.
     */
    #[Override]
    public function description(): Stringable|string
    {
        return 'List PHP files in a directory to locate the right model, controller, or service file. Useful when you are unsure of the exact filename.';
    }

    /**
     * Execute the tool.
     */
    #[Override]
    public function handle(Request $request): Stringable|string
    {
        $dir = $this->sanitizePath($request['directory']);

        if (!$dir) {
            return 'Error: Only app/ and resources/js/ directories may be listed.';
        }

        $fullPath = base_path($dir);

        if (!is_dir($fullPath)) {
            return "Error: Directory not found: {$dir}";
        }

        $files = glob($fullPath.'/*.php') ?: [];

        $relative = array_map(
            fn (string $f): string => str_replace(base_path().'/', '', $f),
            $files,
        );

        return empty($relative)
            ? "No PHP files found in {$dir}"
            : implode("\n", $relative);
    }

    /**
     * Get the tool's schema definition.
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'directory' => $schema->string()
                ->description('Directory to list, relative to the application root. E.g. app/Models or app/Http/Controllers')
                ->required(),
        ];
    }

    private function sanitizePath(string $path): ?string
    {
        $normalized = mb_rtrim(mb_ltrim(str_replace('..', '', $path), '/'), '/');

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($normalized.'/', $prefix)) {
                return $normalized;
            }
        }

        return null;
    }
}
