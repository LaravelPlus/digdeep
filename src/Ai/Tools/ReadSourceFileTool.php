<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Override;
use Stringable;

final class ReadSourceFileTool implements Tool
{
    private const array ALLOWED_PREFIXES = ['app/', 'resources/js/'];

    private const int MAX_BYTES = 51200; // 50 KB

    /**
     * Get the description of the tool's purpose.
     */
    #[Override]
    public function description(): Stringable|string
    {
        return 'Read a PHP or JS source file from the application to understand the code causing the query issue. Provide the path relative to the application root (e.g. app/Models/Hotel.php).';
    }

    /**
     * Execute the tool.
     */
    #[Override]
    public function handle(Request $request): Stringable|string
    {
        $path = $this->sanitizePath($request['path']);

        if (!$path) {
            return 'Error: Access denied. Only files under app/ or resources/js/ may be read.';
        }

        $fullPath = base_path($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return "Error: File not found: {$path}";
        }

        if (filesize($fullPath) > self::MAX_BYTES) {
            return "Error: File is too large to read (max 50 KB): {$path}";
        }

        $content = file_get_contents($fullPath);
        $lines = explode("\n", $content);

        $numbered = array_map(
            fn (int $i, string $line): string => ($i + 1).': '.$line,
            array_keys($lines),
            $lines,
        );

        return $path."\n".str_repeat('-', 60)."\n".implode("\n", $numbered);
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
