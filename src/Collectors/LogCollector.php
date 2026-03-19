<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Throwable;

final class LogCollector
{
    /** @var array<int, array{level: string, message: string, context: string, time_ms: float}> */
    private array $logs = [];

    public function __construct(private readonly float $startTime) {}

    public function listen(): void
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $contextStr = '';

            if (!empty($event->context)) {
                try {
                    $encoded = json_encode($event->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $contextStr = $encoded !== false ? $encoded : '';
                } catch (Throwable) {
                    $contextStr = '';
                }
            }

            $this->logs[] = [
                'level'   => $event->level,
                'message' => (string) $event->message,
                'context' => $contextStr,
                'time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
            ];
        });
    }

    /** @return array<int, array{level: string, message: string, context: string, time_ms: float}> */
    public function getData(): array
    {
        return $this->logs;
    }
}
