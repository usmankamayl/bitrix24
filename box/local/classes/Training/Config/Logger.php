<?php

namespace Training\Config;

/**
 * Структурированное логирование с маскированием чувствительных данных
 */
class Logger
{
    private const LOG_DIR = '/local/logs';

    private const LEVEL_DEBUG = 'debug';
    private const LEVEL_INFO = 'info';
    private const LEVEL_WARNING = 'warning';
    private const LEVEL_ERROR = 'error';

    private const LEVELS = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
    ];

    /**
     * Чувствительные ключи для маскирования
     */
    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'secret',
        'api_key',
        'auth',
        'authorization',
        'webhook',
        'access_token',
        'refresh_token',
        'client_secret',
        'private_key',
    ];

    /**
     * Записать отладочное сообщение
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Записать информационное сообщение
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Записать предупреждение
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Записать ошибку
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Основной метод логирования
     */
    private static function log(string $level, string $message, array $context): void
    {
        $currentLevel = Environment::get('log_level', self::LEVEL_INFO);

        // Проверяем, нужно ли логировать это сообщение
        if (!self::shouldLog($level, $currentLevel)) {
            return;
        }

        // Маскируем чувствительные данные
        $safeContext = self::maskSensitiveData($context);

        // Формируем запись
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $safeContext,
            'environment' => Environment::getCurrent(),
        ];

        // Записываем в файл
        self::writeToFile($level, $entry);
    }

    /**
     * Проверить, нужно ли логировать сообщение данного уровня
     */
    private static function shouldLog(string $level, string $currentLevel): bool
    {
        return self::LEVELS[$level] >= self::LEVELS[$currentLevel];
    }

    /**
     * Маскировать чувствительные данные
     */
    private static function maskSensitiveData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;

            // Проверяем, содержит ли ключ чувствительное слово
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $result[$key] = self::maskValue($value);
            } elseif (is_array($value)) {
                $result[$key] = self::maskSensitiveData($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Замаскировать значение
     */
    private static function maskValue($value): string
    {
        if (is_string($value) && strlen($value) > 0) {
            $length = strlen($value);
            if ($length <= 4) {
                return '****';
            }
            // Показываем первые 2 и последние 2 символа
            return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
        }

        return '***';
    }

    /**
     * Записать в файл
     */
    private static function writeToFile(string $level, array $entry): void
    {
        $logDir = $_SERVER['DOCUMENT_ROOT'] . self::LOG_DIR;

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $filename = $logDir . '/' . date('Y-m-d') . '.log';
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }
}
