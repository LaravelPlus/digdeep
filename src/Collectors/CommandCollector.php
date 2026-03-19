<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;

final class CommandCollector
{
    /** @var array<int, array{command: string, exit_code: int|null, duration_ms: float}> */
    private array $commands = [];

    /** @var array<string, float> */
    private array $pending = [];

    public function listen(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $this->pending[$event->command ?? 'unknown'] = microtime(true);
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            $command = $event->command ?? 'unknown';
            $startTime = $this->pending[$command] ?? null;
            $durationMs = $startTime !== null ? (microtime(true) - $startTime) * 1000 : 0;

            unset($this->pending[$command]);

            $this->commands[] = [
                'command' => $command,
                'exit_code' => $event->exitCode,
                'duration_ms' => round($durationMs, 2),
            ];
        });
    }

    /** @return array<int, array{command: string, exit_code: int|null, duration_ms: float}> */
    public function getData(): array
    {
        return $this->commands;
    }
}
