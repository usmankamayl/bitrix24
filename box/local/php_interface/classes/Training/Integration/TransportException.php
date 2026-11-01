<?php

declare(strict_types=1);

namespace Training\Integration;

use RuntimeException;
use Throwable;

/**
 * Транспортная ошибка: сеть, TLS, сериализация, недоступность хоста.
 *
 * Не путать с InvalidArgumentException (ошибка входных данных/конфигурации).
 *
 * Контекст содержит только безопасные поля: без тела запроса/ответа,
 * без заголовков авторизации, без query-параметров, без сырого curl_error().
 *
 * phase:
 * - 'not_sent'  — запрос гарантированно не ушёл (ошибка сериализации,
 *                 неудачная настройка cURL). Повтор безопасен.
 * - 'unknown'   — запрос мог уйти, ответ не получен. Повтор опасен:
 *                 нужен reconcile().
 */
final class TransportException extends RuntimeException
{
    /** @var array<string, scalar|null> */
    private array $context;

    /**
     * @param array<string, scalar|null> $context
     */
    public function __construct(
        string $message,
        int $code = 0,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function isNotSent(): bool
    {
        return ($this->context['phase'] ?? null) === 'not_sent';
    }
}