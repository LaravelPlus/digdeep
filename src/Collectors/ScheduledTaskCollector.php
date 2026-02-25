<?php

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;

class ScheduledTaskCollector
{
    /** @var array<int, array{command: string, expression: string, duration_s: float|null}> */
    private array $tasks = [];

    /** @var array<int, float> */
    private array $startTimes = [];

    public function listen(): void
    {
        Event::listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $event) {
            $this->startTimes[] = microtime(true);

            $this->tasks[] = [
                'command' => $event->task->command ?? $event->task->getSummaryForDisplay(),
                'expression' => $event->task->expression,
                'duration_s' => null,
            ];
        });

        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            $index = count($this->tasks) - 1;

            if ($index >= 0 && isset($this->startTimes[$index])) {
                $this->tasks[$index]['duration_s'] = round(microtime(true) - $this->startTimes[$index], 4);
            }
        });
    }

    /** @return array<int, array{command: string, expression: string, duration_s: float|null}> */
    public function getData(): array
    {
        return $this->tasks;
    }
}
