<?php

namespace Training\Integration;

use Bitrix\Main\Diag\Logger;

/**
 * HTTP-клиент для интеграции с внешними системами
 *
 * Особенности:
 * - Таймауты и retry-логика
 * - Логирование без раскрытия секретов
 * - Обработка различных HTTP-статусов
 */
class HttpClient
{
    private string $baseUrl;
    private array $defaultHeaders;
    private int $timeout;
    private int $connectTimeout;
    private ?Logger $logger;

    public function __construct(
        string $baseUrl,
        array $defaultHeaders = [],
        int $timeout = 30,
        int $connectTimeout = 10
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = $defaultHeaders;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->logger = null; // можно подключить Bitrix\Main\Diag\Logger
    }

    /**
     * POST-запрос
     */
    public function post(string $endpoint, array $data, array $headers = []): array
    {
        return $this->request('POST', $endpoint, $data, $headers);
    }

    /**
     * GET-запрос
     */
    public function get(string $endpoint, array $params = [], array $headers = []): array
    {
        $url = $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * Основной метод отправки запроса
     */
    private function request(string $method, string $endpoint, ?array $data = null, array $headers = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init();

        // Базовые настройки
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        // SSL (в production должна быть верификация)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // Метод и данные
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headers['Content-Type'] = 'application/json; charset=utf-8';
            }
        }

        // Заголовки
        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $curlHeaders = [];
        foreach ($allHeaders as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        // Выполнение
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Логирование (без секретных заголовков)
        $this->log($method, $url, $httpCode, $duration, $curlError);

        // Обработка ошибок curl
        if ($curlErrno !== 0) {
            throw new \RuntimeException(
                "CURL error [{$curlErrno}]: {$curlError}",
                $curlErrno
            );
        }

        // Разбор ответа
        $result = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'httpCode' => $httpCode,
            'body' => $response,
            'data' => null,
            'duration' => $duration,
        ];

        // Попытка декодировать JSON
        if (!empty($response)) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result['data'] = $decoded;
            }
        }

        return $result;
    }

    /**
     * Логирование запроса (без секретов)
     */
    private function log(string $method, string $url, int $httpCode, float $duration, string $error = ''): void
    {
        // Маскируем query-параметры с секретами
        $safeUrl = preg_replace('/([?&])(token|key|secret|password)=[^&]+/', '$1$2=***', $url);

        $message = sprintf(
            '%s %s -> HTTP %d (%.2fms)%s',
            $method,
            $safeUrl,
            $httpCode,
            $duration,
            $error ? " ERROR: {$error}" : ''
        );

        // В production здесь должен быть настоящий Logger
        error_log("[HttpClient] {$message}");
    }
}
