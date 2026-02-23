<?php

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;

class HttpClientCollector
{
    /** @var array<int, array{method: string, url: string, status: int, duration_ms: float}> */
    private array $requests = [];

    public function listen(): void
    {
        Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
            $request = $event->request;
            $response = $event->response;

            $this->requests[] = [
                'method' => $request->method(),
                'url' => $request->url(),
                'status' => $response->status(),
                'duration_ms' => $response->transferStats?->getTransferTime() ? round($response->transferStats->getTransferTime() * 1000, 2) : 0,
            ];
        });
    }

    /** @return array<int, array{method: string, url: string, status: int, duration_ms: float}> */
    public function getData(): array
    {
        return $this->requests;
    }
}
