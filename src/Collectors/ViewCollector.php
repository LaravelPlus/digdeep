<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Support\Facades\Event;

final class ViewCollector
{
    /** @var array<int, array{name: string, path: string, data_keys: array<string>, rendered_at: float}> */
    private array $views = [];

    public function listen(): void
    {
        Event::listen('composing:*', function (string $eventName, array $payload): void {
            $view = $payload[0] ?? null;

            if (!$view instanceof \Illuminate\View\View) {
                return;
            }

            $this->views[] = [
                'name' => $view->name(),
                'path' => str_replace(base_path().'/', '', $view->getPath()),
                'data_keys' => array_keys($view->getData()),
                'rendered_at' => microtime(true),
            ];
        });
    }

    /** @return array<int, array{name: string, path: string, data_keys: array<string>, rendered_at: float}> */
    public function getData(): array
    {
        return $this->views;
    }
}
