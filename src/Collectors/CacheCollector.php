<?php

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;

class CacheCollector
{
    /** @var array<int, array{type: string, key: string}> */
    private array $operations = [];

    public function listen(): void
    {
        Event::listen(CacheHit::class, function (CacheHit $event) {
            $this->operations[] = [
                'type' => 'hit',
                'key' => $event->key,
            ];
        });

        Event::listen(CacheMissed::class, function (CacheMissed $event) {
            $this->operations[] = [
                'type' => 'miss',
                'key' => $event->key,
            ];
        });

        Event::listen(KeyWritten::class, function (KeyWritten $event) {
            $this->operations[] = [
                'type' => 'write',
                'key' => $event->key,
            ];
        });
    }

    /** @return array<int, array{type: string, key: string}> */
    public function getData(): array
    {
        return $this->operations;
    }
}
