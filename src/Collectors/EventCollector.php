<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Support\Facades\Event;

final class EventCollector
{
    /** @var array<int, array{event: string, payload_summary: string}> */
    private array $events = [];

    /** @var array<int, string> */
    private array $ignoredPrefixes = [
        'Illuminate\\Database\\',
        'Illuminate\\Log\\',
        'Illuminate\\Foundation\\Http\\Events\\',
        'Illuminate\\Routing\\Events\\',
        'eloquent.',
        'composing:',
        'creating:',
        'bootstrapped:',
        'bootstrapping:',
    ];

    public function listen(): void
    {
        Event::listen('*', function (string $eventName, array $payload): void {
            if ($this->shouldIgnore($eventName)) {
                return;
            }

            $this->events[] = [
                'event' => $eventName,
                'payload_summary' => $this->summarizePayload($payload),
            ];
        });
    }

    /** @return array<int, array{event: string, payload_summary: string}> */
    public function getData(): array
    {
        return $this->events;
    }

    private function shouldIgnore(string $eventName): bool
    {
        return array_any($this->ignoredPrefixes, fn ($prefix) => str_starts_with($eventName, $prefix));
    }

    private function summarizePayload(array $payload): string
    {
        $parts = [];

        foreach ($payload as $item) {
            if (is_object($item)) {
                $parts[] = get_class($item);
            } elseif (is_string($item)) {
                $parts[] = mb_substr($item, 0, 100);
            }
        }

        return implode(', ', $parts) ?: '—';
    }
}
