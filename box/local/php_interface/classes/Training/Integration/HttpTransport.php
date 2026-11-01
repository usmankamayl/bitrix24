<?php

declare(strict_types=1);

namespace Training\Integration;

/**
 * Минимальный контракт транспорта для тестируемости бизнес-слоя.
 *
 * Реализация по умолчанию — HttpClient. В тестах — FakeHttpTransport.
 */
interface HttpTransport
{
    /**
     * @param array<string, scalar> $params
     * @param array<string, string> $headers
     * @return array{
     *     success: bool,
     *     httpCode: int,
     *     body: string,
     *     data: mixed,
     *     jsonValid: bool,
     *     duration: float
     * }
     */
    public function get(
        string $endpoint,
        array $params = [],
        array $headers = []
    ): array;

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     * @return array{
     *     success: bool,
     *     httpCode: int,
     *     body: string,
     *     data: mixed,
     *     jsonValid: bool,
     *     duration: float
     * }
     */
    public function post(
        string $endpoint,
        array $data,
        array $headers = []
    ): array;
}