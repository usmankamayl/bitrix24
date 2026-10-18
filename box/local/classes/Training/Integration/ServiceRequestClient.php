<?php

namespace Training\Integration;

/**
 * Клиент для отправки B2B-заявок во внешнюю систему
 *
 * Обеспечивает:
 * - Идемпотентность через внешний ID (externalId)
 * - Валидацию данных перед отправкой
 * - Обработку различных сценариев ответа
 */
class ServiceRequestClient
{
    private HttpClient $httpClient;

    public function __construct(string $apiUrl, string $apiKey)
    {
        $this->httpClient = new HttpClient(
            $apiUrl,
            [
                'X-API-Key' => $apiKey,
                'User-Agent' => 'Bitrix24-Integration/1.0',
            ],
            timeout: 30,
            connectTimeout: 10
        );
    }

    /**
     * Отправить заявку во внешнюю систему
     *
     * @param int $requestId ID заявки в Bitrix24
     * @param array $requestData Данные заявки
     * @return array Результат отправки
     */
    public function sendRequest(int $requestId, array $requestData): array
    {
        // Валидация обязательных полей
        $this->validateRequestData($requestData);

        // externalId для идемпотентности
        $payload = [
            'externalId' => "bitrix24_request_{$requestId}",
            'source' => 'bitrix24',
            'timestamp' => date('c'),
            'data' => $requestData,
        ];

        try {
            $response = $this->httpClient->post('post', $payload);

            return [
                'success' => $response['success'],
                'httpCode' => $response['httpCode'],
                'externalId' => $payload['externalId'],
                'response' => $response['data'] ?? $response['body'],
                'duration' => $response['duration'],
            ];
        } catch (\RuntimeException $e) {
            // Ошибка сети/таймаут
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errorCode' => $e->getCode(),
                'externalId' => $payload['externalId'],
            ];
        }
    }

    /**
     * Проверить статус заявки во внешней системе
     */
    public function checkStatus(int $requestId): array
    {
        $externalId = "bitrix24_request_{$requestId}";

        try {
            $response = $this->httpClient->get('get', ['externalId' => $externalId]);

            return [
                'success' => $response['success'],
                'httpCode' => $response['httpCode'],
                'data' => $response['data'],
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Валидация данных заявки
     */
    private function validateRequestData(array $data): void
    {
        $required = ['title', 'description', 'customerEmail'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Обязательное поле отсутствует: {$field}");
            }
        }

        if (!filter_var($data['customerEmail'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Некорректный email');
        }
    }
}
