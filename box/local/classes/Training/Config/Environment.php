<?php

namespace Training\Config;

/**
 * Класс для управления окружениями (dev/test/prod)
 */
class Environment
{
    private static ?string $current = null;
    private static array $config = [];

    /**
     * Получить текущее окружение
     */
    public static function getCurrent(): string
    {
        if (self::$current === null) {
            self::load();
        }

        return self::$current;
    }

    /**
     * Проверить, является ли окружение dev
     */
    public static function isDev(): bool
    {
        return self::getCurrent() === 'dev';
    }

    /**
     * Проверить, является ли окружение test
     */
    public static function isTest(): bool
    {
        return self::getCurrent() === 'test';
    }

    /**
     * Проверить, является ли окружение prod
     */
    public static function isProd(): bool
    {
        return self::getCurrent() === 'prod';
    }

    /**
     * Получить параметр конфигурации
     *
     * @param string $key Ключ параметра (может быть с точкой: 'external_api.timeout')
     * @param mixed $default Значение по умолчанию
     */
    public static function get(string $key, $default = null)
    {
        if (empty(self::$config)) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Получить всю конфигурацию текущего окружения
     */
    public static function getAll(): array
    {
        if (empty(self::$config)) {
            self::load();
        }

        return self::$config;
    }

    /**
     * Загрузить конфигурацию окружения
     */
    private static function load(): void
    {
        $configDir = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/config';
        $envFile = $configDir . '/.environment';
        $environmentsFile = $configDir . '/environments.php';

        // Определяем текущее окружение
        if (file_exists($envFile)) {
            self::$current = trim(file_get_contents($envFile));
        } else {
            // По умолчанию dev
            self::$current = 'dev';
        }

        // Валидация окружения
        if (!in_array(self::$current, ['dev', 'test', 'prod'])) {
            throw new \RuntimeException(
                "Invalid environment: " . self::$current . ". Must be one of: dev, test, prod"
            );
        }

        // Загружаем конфигурацию
        if (!file_exists($environmentsFile)) {
            throw new \RuntimeException(
                "Environments config not found: " . $environmentsFile
            );
        }

        $environments = require $environmentsFile;

        if (!isset($environments[self::$current])) {
            throw new \RuntimeException(
                "Configuration for environment '" . self::$current . "' not found"
            );
        }

        self::$config = $environments[self::$current];
    }

    /**
     * Установить окружение (только для тестов)
     */
    public static function setForTest(string $env, array $config): void
    {
        self::$current = $env;
        self::$config = $config;
    }

    /**
     * Сбросить состояние (для тестов)
     */
    public static function reset(): void
    {
        self::$current = null;
        self::$config = [];
    }
}
