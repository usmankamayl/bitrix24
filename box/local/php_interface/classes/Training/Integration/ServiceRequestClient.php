<?php

declare(strict_types=1);

namespace Training\Integration;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class ServiceRequestClient
{
    private HttpTransport $httpClient;

    private string $createEndpoint;
    private string $statusEndpoint;
    private string $sourceScope;

    public function __construct(
        string $apiUrl,
        string $apiKey,
        string $sourceScope,
        ?LoggerInterface $logger = null,
        string $createEndpoint = 'requests',
        string $statusEndpoint = 'requests/status',
        ?HttpTransport $httpClient = null,
        bool $allowHttp = false
    ) {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('API-ключ не задан');
        }

        if (!preg_match('/^[A-Za-z0-9_-]{1,48}$/D', $sourceScope)) {
            throw new InvalidArgumentException(
                'Некорректный sourceScope: [A-Za-z0-9_-]{1,48}'
            );
        }

        $this->validateEndpoint($createEndpoint);
        $this->validateEndpoint($statusEndpoint);

        $this->sourceScope = $sourceScope;
        $this->createEndpoint = $createEndpoint;
        $this->statusEndpoint = $statusEndpoint;

        $this->httpClient = $httpClient ?? new HttpClient(
                $apiUrl,
                [
                    'X-API-Key' => $apiKey,
                    'User-Agent' => 'Bitrix24-Integration/1.0',
                ],
                timeout: 30,
                connectTimeout: 10,
                logger: $logger,
                allowHttp: $allowHttp
            );
    }

    /**
     * Отправить заявку во внешнюю систему.
     *
     * outcome:
     * - accepted         — HTTP 2xx + валидный JSON-массив;
     * - not_sent         — запрос гарантированно не ушёл (сериализация,
     *                      настройка cURL). Повтор безопасен после исправления;
     * - unknown          — 5xx, таймаут, invalid JSON, сетевой сбой.
     *                      Повтор POST запрещён; нужен reconcile();
     * - http_error       — 4xx. Семантика уточняется по контракту API;
     * - invalid_response — 2xx без валидного JSON-массива.
     */
    public function sendRequest(int $requestId, array $requestData): array
    {
        $this->validateRequestId($requestId);
        $this->validateRequestData($requestData);

        $externalId = $this->buildExternalId($requestId);

        $payload = [
            'externalId' => $externalId,
            'source' => 'bitrix24',
            'timestamp' => date(DATE_ATOM),
            'data' => $requestData,
        ];

        try {
            $response = $this->httpClient->post(
                $this->createEndpoint,
                $payload,
                ['Idempotency-Key' => $externalId]
            );
        } catch (TransportException $e) {
            $notSent = $e->isNotSent();

            return $this->result(
                $externalId,
                $notSent ? 'not_sent' : 'unknown',
                [
                    'error' => 'transport_error',
                    'next_step' => $notSent ? 'fix_cause' : 'reconcile',
                ]
            );
        }

        $meta = [
            'httpCode' => $response['httpCode'],
            'duration' => $response['duration'],
        ];

        if ($response['httpCode'] >= 500) {
            return $this->result($externalId, 'unknown', $meta + [
                    'error' => 'http_server_error',
                    'next_step' => 'reconcile',
                ]);
        }

        if (!$response['success']) {
            return $this->result($externalId, 'http_error', $meta + [
                    'error' => 'http_client_error',
                ]);
        }

        if (!$response['jsonValid'] || !is_array($response['data'])) {
            return $this->result($externalId, 'invalid_response', $meta + [
                    'error' => 'invalid_response',
                    'next_step' => 'reconcile',
                ]);
        }

        return $this->result($externalId, 'accepted', $meta + [
                'data' => $response['data'],
            ]);
    }

    public function checkStatus(int $requestId): array
    {
        $this->validateRequestId($requestId);

        $externalId = $this->buildExternalId($requestId);

        try {
            $response = $this->httpClient->get(
                $this->statusEndpoint,
                ['externalId' => $externalId]
            );
        } catch (TransportException) {
            return $this->result($externalId, 'unknown', [
                'error' => 'transport_error',
            ]);
        }

        $meta = [
            'httpCode' => $response['httpCode'],
            'duration' => $response['duration'],
        ];

        if (!$response['success']) {
            return $this->result($externalId, 'http_error', $meta + [
                    'error' => 'http_error',
                ]);
        }

        if (!$response['jsonValid'] || !is_array($response['data'])) {
            return $this->result($externalId, 'invalid_response', $meta + [
                    'error' => 'invalid_response',
                ]);
        }

        return $this->result($externalId, 'received', $meta + [
                'data' => $response['data'],
            ]);
    }

    /**
     * Сверка после outcome=unknown / invalid_response.
     * Не повторяет POST. 404 и «не найдено» не разрешают новый POST.
     */
    public function reconcile(int $requestId): array
    {
        $status = $this->checkStatus($requestId);

        if ($status['outcome'] === 'received') {
            $status['outcome'] = 'reconciled';
            $status['success'] = true;
            return $status;
        }

        $status['success'] = false;
        $status['outcome'] = 'unknown';
        $status['next_step'] = 'manual_check_required';

        return $status;
    }

    private function buildExternalId(int $requestId): string
    {
        return 'b24_' . $this->sourceScope
            . '_service_request_' . $requestId;
    }

    private function validateRequestId(int $requestId): void
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException(
                'ID заявки должен быть положительным'
            );
        }
    }

    private function validateRequestData(array $data): void
    {
        $required = ['title', 'description', 'customerEmail'];

        foreach ($required as $field) {
            if (
                !isset($data[$field])
                || !is_string($data[$field])
                || trim($data[$field]) === ''
            ) {
                throw new InvalidArgumentException(
                    'Некорректное обязательное поле: ' . $field
                );
            }
        }

        if (!filter_var($data['customerEmail'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Некорректный email клиента');
        }
    }

    private function validateEndpoint(string $endpoint): void
    {
        HttpClient::validateEndpoint($endpoint);
    }

    /**
     * Единый формат результата для всех публичных методов.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function result(
        string $externalId,
        string $outcome,
        array $fields = []
    ): array {
        return array_replace(
            [
                'success' => in_array(
                    $outcome,
                    ['accepted', 'received', 'reconciled'],
                    true
                ),
                'outcome' => $outcome,
                'externalId' => $externalId,
                'httpCode' => null,
                'duration' => null,
                'data' => null,
                'error' => null,
                'next_step' => null,
            ],
            $fields
        );
    }
}