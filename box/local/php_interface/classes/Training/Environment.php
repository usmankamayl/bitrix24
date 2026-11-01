<?php
namespace Training;

/**
 * Простой класс для проверки подключения из /local
 *
 * Модуль: P00 - Окружение и инженерная база
 */
class Environment
{
    /**
     * Получить информацию об окружении
     *
     * @return array
     */
    public static function getInfo(): array
    {
        $info = [
            'php_version' => PHP_VERSION,
            'bitrix_loaded' => defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true,
            'd7_available' => class_exists('\Bitrix\Main\Loader'),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // Если Bitrix загружен, добавим версию
        if ($info['bitrix_loaded']) {
            if (defined('SM_VERSION')) {
                $info['bitrix_version'] = SM_VERSION;
            }

            if (class_exists('\Bitrix\Main\ModuleManager')) {
                $info['rest_available'] = \Bitrix\Main\Loader::includeModule('rest');
            }
        }

        return $info;
    }

    /**
     * Простой метод для проверки работоспособности
     *
     * @param string $name
     * @return string
     */
    public static function hello(string $name = 'World'): string
    {
        return "Hello, {$name}! Training\\Environment class loaded successfully.";
    }
}
