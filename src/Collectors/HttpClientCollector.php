<?php

namespace LaravelPlus\DigDeep\Collectors;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;

class HttpClientCollector
{
    /** @var array<int, array{method: string, url: string, status: int, duration_ms: float, request_headers?: array, request_body?: string, response_headers?: array, response_body?: string, response_size?: int}> */
    private array $requests = [];

    /** @var array<int, string> */
    private array $sensitiveHeaders = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token'];

    public function listen(): void
    {
        Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
            $request = $event->request;
            $response = $event->response;

            $requestHeaders = $this->sanitizeHeaders($request->headers());
            $responseHeaders = $this->sanitizeHeaders($response->headers());
            $responseBody = $response->body();
            $responseSize = strlen($responseBody);

            // Truncate bodies to prevent storage bloat
            $requestBody = mb_substr((string) $request->body(), 0, 4096);
            $responseBody = mb_substr($responseBody, 0, 8192);

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
        $sanitized = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, $this->sensitiveHeaders)) {
                $sanitized[$key] = '[redacted]';
            } else {
                $sanitized[$key] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $sanitized;
    }
}
