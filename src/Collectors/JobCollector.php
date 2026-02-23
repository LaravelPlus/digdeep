<?php

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;

class JobCollector
{
    /** @var array<int, array{job: string, queue: string}> */
    private array $jobs = [];

    public function listen(): void
    {
        Event::listen(JobQueued::class, function (JobQueued $event) {
            $this->jobs[] = [
                'job' => is_object($event->job) ? get_class($event->job) : (string) $event->job,
                'queue' => $event->queue ?? 'default',
            ];
        });
    }

    /** @return array<int, array{job: string, queue: string}> */
    public function getData(): array
    {
        return $this->jobs;
    }
}
