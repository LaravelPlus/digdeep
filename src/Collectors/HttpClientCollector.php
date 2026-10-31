<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use LaravelPlus\DigDeep\Support\Redactor;

final class HttpClientCollector
{
    /** @var array<int, array{method: string, url: string, status: int, duration_ms: float, request_headers?: array, request_body?: string, response_headers?: array, response_body?: string, response_size?: int}> */
    private array $requests = [];

    public function listen(): void
    {
        Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
            $request = $event->request;
            $response = $event->response;

            $requestHeaders = $this->sanitizeHeaders($request->headers());
            $responseHeaders = $this->sanitizeHeaders($response->headers());
            $responseBody = $response->body();
            $responseSize = mb_strlen($responseBody);

            // Truncate bodies to prevent storage bloat
            $requestBody = mb_substr(Redactor::body((string) $request->body(), $request->header('Content-Type')[0] ?? ''), 0, 4096);
            $responseBody = mb_substr(Redactor::body($responseBody, $response->header('Content-Type')), 0, 8192);

            $this->requests[] = [
                'method' => $request->method(),
                'url' => $request->url(),
                'status' => $response->status(),
                'duration_ms' => $response->transferStats?->getTransferTime() ? round($response->transferStats->getTransferTime() * 1000, 2) : 0,
                'request_headers' => $requestHeaders,
                'request_body' => $requestBody,
                'response_headers' => $responseHeaders,
                'response_body' => $responseBody,
                'response_size' => $responseSize,
            ];
        });
    }

    /** @return array<int, array{method: string, url: string, status: int, duration_ms: float, request_headers?: array, request_body?: string, response_headers?: array, response_body?: string, response_size?: int}> */
    public function getData(): array
    {
        return $this->requests;
    }

    private function sanitizeHeaders(array $headers): array
    {
        return array_map(
            fn ($value) => is_array($value) ? implode(', ', $value) : $value,
            Redactor::headers($headers),
        );
    }
}
