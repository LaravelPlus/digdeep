<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Override;
use Stringable;

final class WriteSourceFileTool implements Tool
{
    private const array ALLOWED_PREFIXES = ['app/'];

    /**
     * Get the description of the tool's purpose.
     */
    #[Override]
    public function description(): Stringable|string
    {
        return 'Apply a targeted fix to a PHP source file by replacing an exact code snippet. The old_code must match exactly what is in the file.';
    }

    /**
     * Execute the tool.
     */
    #[Override]
    public function handle(Request $request): Stringable|string
    {
        $path = $this->sanitizePath($request['path']);

        if (!$path) {
            return 'Error: Access denied. Only files under app/ may be modified.';
        }

        $fullPath = base_path($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return "Error: File not found: {$path}";
        }

        $oldCode = $request['old_code'];
        $newCode = $request['new_code'];
        $content = file_get_contents($fullPath);

        if (!str_contains($content, $oldCode)) {
            return "Error: The specified old_code was not found in {$path}. Verify it matches exactly, including whitespace.";
        }

        $updated = str_replace($oldCode, $newCode, $content);
        file_put_contents($fullPath, $updated);

        return "Successfully updated {$path}";
    }

    /**
     * Get the tool's schema definition.
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('Relative path from the application root, e.g. app/Models/Hotel.php')
                ->required(),
            'old_code' => $schema->string()
                ->description('The exact code string to replace, including surrounding whitespace')
                ->required(),
            'new_code' => $schema->string()
                ->description('The replacement code string')
                ->required(),
        ];
    }

    private function sanitizePath(string $path): ?string
    {
        $normalized = mb_ltrim(str_replace('..', '', $path), '/');

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return $normalized;
            }
        }

        return null;
    }
}
