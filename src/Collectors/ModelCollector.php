<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Support\Facades\Event;

final class ModelCollector
{
    /** @var array<string, array{class: string, retrieved: int, created: int, updated: int, deleted: int}> */
    private array $models = [];

    public function listen(): void
    {
        Event::listen('eloquent.retrieved:*', function (string $event): void {
            $this->record($event, 'retrieved');
        });

        Event::listen('eloquent.created:*', function (string $event): void {
            $this->record($event, 'created');
        });

        Event::listen('eloquent.updated:*', function (string $event): void {
            $this->record($event, 'updated');
        });

        Event::listen('eloquent.deleted:*', function (string $event): void {
            $this->record($event, 'deleted');
        });
    }

    private function record(string $event, string $operation): void
    {
        // Event format: "eloquent.retrieved: App\Models\User"
        $class = str_replace("eloquent.{$operation}: ", '', $event);

        if (!isset($this->models[$class])) {
            $this->models[$class] = [
                'class' => $class,
                'retrieved' => 0,
                'created' => 0,
                'updated' => 0,
                'deleted' => 0,
            ];
        }

        $this->models[$class][$operation]++;
    }

    /** @return array<int, array{class: string, retrieved: int, created: int, updated: int, deleted: int}> */
    public function getData(): array
    {
        return array_values($this->models);
    }
}
