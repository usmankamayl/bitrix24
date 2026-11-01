<?php

declare(strict_types=1);

namespace Training\Integration;

use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class HttpClient implements HttpTransport
{
    private string $baseUrl;

    /** @var array<string, string> */
    private array $defaultHeaders;

    private int $timeout;
    private int $connectTimeout;
    private ?LoggerInterface $logger;

    /**
     * @param array<string, string> $defaultHeaders
     * @param bool                  $allowHttp     true только для dev-стендов
     */
    public function __construct(
        string $baseUrl,
        array $defaultHeaders = [],
        int $timeout = 30,
        int $connectTimeout = 10,
        ?LoggerInterface $logger = null,
        bool $allowHttp = false
    ) {
        if ($timeout < 1 || $connectTimeout < 1) {
            throw new InvalidArgumentException(
                'Таймауты должны быть положительными'
            );
        }

        if (!extension_loaded('curl')) {
            throw new RuntimeException('PHP-расширение cURL не установлено');
        }

        $this->baseUrl = $this->validateBaseUrl($baseUrl, $allowHttp);
        $this->defaultHeaders = $this->normalizeHeaders($defaultHeaders);
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->logger = $logger;
    }

    public function get(
        string $endpoint,
        array $params = [],
        array $headers = []
    ): array {
        return $this->request('GET', $endpoint, null, $headers, $params);
    }

    public function post(
        string $endpoint,
        array $data,
        array $headers = []
    ): array {
        return $this->request('POST', $endpoint, $data, $headers);
    }

    /**
     * @param array<string, scalar> $query
     */
    private function request(
        string $method,
        string $endpoint,
        ?array $data,
        array $headers,
        array $query = []
    ): array {
        $method = strtoupper($method);

        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException('Метод не поддерживается');
        }

        $url = $this->buildUrl($endpoint);

        if ($query !== []) {
            $url .= '?' . http_build_query(
                    $query,
                    '',
                    '&',
                    PHP_QUERY_RFC3986
                );
        }

        $payload = null;

        if ($data !== null) {
            try {
                $payload = json_encode(
                    $data,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                );
            } catch (JsonException $e) {
                throw new TransportException(
                    'Не удалось сериализовать JSON',
                    0,
                    [
                        'method' => $method,
                        'url' => $this->safeUrl($url),
                        'phase' => 'not_sent',
                    ],
                    $e
                );
            }
        }

        $allHeaders = array_replace(
            $this->defaultHeaders,
            $this->normalizeHeaders($headers)
        );

        if ($payload !== null) {
            $allHeaders['content-type'] = 'application/json; charset=utf-8';
        }

        $allHeaders['accept'] ??= 'application/json';

        $curlHeaders = [];
        foreach ($allHeaders as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $ch = curl_init();

        if ($ch === false) {
            throw new TransportException(
                'Не удалось инициализировать cURL',
                0,
                [
                    'method' => $method,
                    'url' => $this->safeUrl($url),
                    'phase' => 'not_sent',
                ]
            );
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($payload !== null) {
                $options[CURLOPT_POSTFIELDS] = $payload;
            }
        }

        try {
            if (!curl_setopt_array($ch, $options)) {
                throw new TransportException(
                    'Не удалось настроить cURL',
                    0,
                    [
                        'method' => $method,
                        'url' => $this->safeUrl($url),
                        'phase' => 'not_sent',
                    ]
                );
            }

            $startedAt = microtime(true);
            $responseBody = curl_exec($ch);
            $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrorNumber = curl_errno($ch);
        } finally {
            curl_close($ch);
        }

        $safeUrl = $this->safeUrl($url);

        $this->logRequest(
            $method,
            $safeUrl,
            $httpCode,
            $durationMs,
            $curlErrorNumber
        );

        if ($responseBody === false) {
            throw new TransportException(
                'Ошибка сетевого запроса (cURL ' . $curlErrorNumber . ')',
                $curlErrorNumber,
                [
                    'method' => $method,
                    'url' => $safeUrl,
                    'http_code' => $httpCode,
                    'duration_ms' => $durationMs,
                    'curl_error_number' => $curlErrorNumber,
                    'phase' => 'unknown',
                ]
            );
        }

        $decoded = null;
        $jsonValid = false;

        if ($responseBody !== '') {
            try {
                $decoded = json_decode(
                    $responseBody,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                $jsonValid = true;
            } catch (JsonException) {
                // Тело ответа доступно в body; JSON отсутствует или некорректен.
            }
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'httpCode' => $httpCode,
            'body' => $responseBody,
            'data' => $jsonValid ? $decoded : null,
            'jsonValid' => $jsonValid,
            'duration' => $durationMs,
        ];
    }

    private function validateBaseUrl(
        string $baseUrl,
        bool $allowHttp
    ): string {
        if (
            $baseUrl === ''
            || preg_match('/[\x00-\x20\x7F]/', $baseUrl)
            || str_contains($baseUrl, '\\')
        ) {
            throw new InvalidArgumentException('Некорректный baseUrl');
        }

        $parts = parse_url(rtrim($baseUrl, '/'));

        $allowedSchemes = $allowHttp ? ['https', 'http'] : ['https'];

        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), $allowedSchemes, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException(
                'Некорректный baseUrl: ожидается HTTPS URL без credentials и query'
            );
        }

        $host = strtolower($parts['host']);

        if (
            filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            === false
        ) {
            throw new InvalidArgumentException('Некорректный хост API');
        }

        $path = $parts['path'] ?? '';

        if ($path !== '') {
            self::validateEndpoint($path);
        }

        $normalized = strtolower($parts['scheme']) . '://' . $host
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $path;

        return rtrim($normalized, '/');
    }

    public static function validateEndpoint(string $endpoint): void
    {
        if (
            !preg_match(
                '#^/?[A-Za-z0-9._~-]+(?:/[A-Za-z0-9._~-]+)*$#D',
                $endpoint
            )
        ) {
            throw new InvalidArgumentException(
                'Endpoint должен быть относительным путём без query и fragment'
            );
        }

        foreach (explode('/', ltrim($endpoint, '/')) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new InvalidArgumentException(
                    'Переходы по каталогам в endpoint запрещены'
                );
            }
        }
    }

    private function buildUrl(string $endpoint): string
    {
        self::validateEndpoint($endpoint);

        return $this->baseUrl . '/' . ltrim($endpoint, '/');
    }

    /**
     * Только origin. Путь, query и credentials в диагностику не попадают.
     */
    private function safeUrl(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https') . '://'
            . ($parts['host'] ?? 'unknown')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $name => $value) {
            if (
                !is_string($name)
                || !preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name)
                || !is_string($value)
                || preg_match('/[\x00-\x1F\x7F]/', $value)
            ) {
                throw new InvalidArgumentException(
                    'Некорректный HTTP-заголовок'
                );
            }

            $name = strtolower($name);

            if (in_array(
                $name,
                ['host', 'content-length', 'transfer-encoding'],
                true
            )) {
                throw new InvalidArgumentException(
                    'Управляемый транспортом заголовок запрещён'
                );
            }

            $result[$name] = $value;
        }

        return $result;
    }

    private function logRequest(
        string $method,
        string $safeUrl,
        int $httpCode,
        float $durationMs,
        int $curlErrorNumber
    ): void {
        $context = [
            'method' => $method,
            'url' => $safeUrl,
            'http_code' => $httpCode,
            'duration_ms' => $durationMs,
            'curl_error_number' => $curlErrorNumber,
        ];

        try {
            if ($this->logger !== null) {
                $this->logger->info(
                    'External HTTP request completed',
                    $context
                );
                return;
            }

            error_log(
                '[HttpClient] ' . json_encode(
                    $context,
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                )
            );
        } catch (Throwable) {
            // Сбой диагностики не должен менять результат выполненного запроса.
        }
    }
}