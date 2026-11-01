<?php

declare(strict_types=1);

namespace Training\Integration\Tests;

use Training\Integration\HttpTransport;
use Training\Integration\TransportException;

/**
 * Управляемый транспорт для тестов без сети.
 *
 * Позволяет задать очередь ответов и/или исключений.
 */
final class FakeHttpTransport implements HttpTransport
{
    /** @var list<array{0:string,1:?string,2:array,3:array}> */
    public array $calls = [];

    /** @var list<array<string, mixed>|TransportException> */
    private array $queue = [];

    public function pushResponse(
        int $httpCode,
        string $body = '',
        bool $jsonValid = null
    ): void {
        $decoded = null;
        $valid = false;

        if ($body !== '') {
            $decoded = json_decode($body, true);
            $valid = json_last_error() === JSON_ERROR_NONE;
        }

        if ($jsonValid !== null) {
            $valid = $jsonValid;
        }

        $this->queue[] = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'httpCode' => $httpCode,
            'body' => $body,
            'data' => $valid ? $decoded : null,
            'jsonValid' => $valid,
            'duration' => 1.0,
        ];
    }

    public function pushException(TransportException $e): void
    {
        $this->queue[] = $e;
    }

    public function get(
        string $endpoint,
        array $params = [],
        array $headers = []
    ): array {
        $this->calls[] = ['GET', $endpoint, $params, $headers];
        return $this->next();
    }

    public function post(
        string $endpoint,
        array $data,
        array $headers = []
    ): array {
        $this->calls[] = ['POST', $endpoint, $data, $headers];
        return $this->next();
    }

    private function next(): array
    {
        if ($this->queue === []) {
            throw new \RuntimeException('FakeHttpTransport: очередь пуста');
        }

        $item = array_shift($this->queue);

        if ($item instanceof TransportException) {
            throw $item;
        }

        return $item;
    }
}