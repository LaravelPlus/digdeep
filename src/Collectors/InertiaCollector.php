<?php

namespace LaravelPlus\DigDeep\Collectors;

use Symfony\Component\HttpFoundation\Response;

class InertiaCollector
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function collect(Response $response): void
    {
        // Check Inertia header for XHR responses
        if ($response->headers->has('X-Inertia')) {
            $content = $response->getContent();
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $this->data = $this->buildInertiaData($decoded);

                    return;
                }
            }
        }

        // Check HTML page for initial Inertia page object
        $content = $response->getContent();
        if ($content && preg_match('/data-page="([^"]+)"/', $content, $matches)) {
            $decoded = json_decode(html_entity_decode($matches[1]), true);
            if (is_array($decoded)) {
                $this->data = $this->buildInertiaData($decoded);
            }
        }
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    private function buildInertiaData(array $decoded): array
    {
        $props = $decoded['props'] ?? [];

        return [
            'component' => $decoded['component'] ?? null,
            'props' => $this->summarizeProps($props),
            'props_raw' => $this->truncateProps($props),
            'url' => $decoded['url'] ?? null,
            'version' => $decoded['version'] ?? null,
        ];
    }

    /**
     * Capture raw prop values, truncated for storage.
     *
     * @return array<string, mixed>
     */
    private function truncateProps(array $props, int $maxDepth = 3, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return ['...' => 'truncated'];
        }

        $result = [];

        foreach ($props as $key => $value) {
            if (is_array($value)) {
                if (isset($value[0]) && count($value) > 10) {
                    $result[$key] = array_map(
                        fn ($item) => is_array($item) ? $this->truncateProps($item, $maxDepth, $currentDepth + 1) : $item,
                        array_slice($value, 0, 10)
                    );
                    $result[$key][] = '... '.(count($value) - 10).' more items';
                } elseif (is_array($value)) {
                    $result[$key] = $this->truncateProps($value, $maxDepth, $currentDepth + 1);
                }
            } elseif (is_string($value) && strlen($value) > 500) {
                $result[$key] = mb_substr($value, 0, 500).'... (truncated)';
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @return array<string, string> */
    private function summarizeProps(array $props): array
    {
        $summary = [];

        foreach ($props as $key => $value) {
            if (is_array($value)) {
                $count = count($value);
                if (isset($value[0]) && is_array($value[0])) {
                    $summary[$key] = "array[{$count}] of objects";
                } else {
                    $summary[$key] = "array[{$count}]";
                }
            } elseif (is_object($value)) {
                $summary[$key] = get_class($value);
            } elseif (is_string($value)) {
                $summary[$key] = 'string('.strlen($value).')';
            } elseif (is_bool($value)) {
                $summary[$key] = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $summary[$key] = 'null';
            } else {
                $summary[$key] = (string) $value;
            }
        }

        return $summary;
    }
}
