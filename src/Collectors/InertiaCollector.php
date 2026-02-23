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
                    $this->data = [
                        'component' => $decoded['component'] ?? null,
                        'props' => $this->summarizeProps($decoded['props'] ?? []),
                        'url' => $decoded['url'] ?? null,
                        'version' => $decoded['version'] ?? null,
                    ];

                    return;
                }
            }
        }

        // Check HTML page for initial Inertia page object
        $content = $response->getContent();
        if ($content && preg_match('/data-page="([^"]+)"/', $content, $matches)) {
            $decoded = json_decode(html_entity_decode($matches[1]), true);
            if (is_array($decoded)) {
                $this->data = [
                    'component' => $decoded['component'] ?? null,
                    'props' => $this->summarizeProps($decoded['props'] ?? []),
                    'url' => $decoded['url'] ?? null,
                    'version' => $decoded['version'] ?? null,
                ];
            }
        }
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
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
