<?php

/**
 * Точка входа для пользовательских расширений.
 * Подключается ядром Bitrix при инициализации.
 *
 * Документация:
 * https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2830
 *
 * Принцип: init.php — короткий. Вся логика — в отдельных файлах.
 */

declare(strict_types=1);

// Автозагрузка классов из /local/php_interface/classes/
spl_autoload_register(static function (string $className): void {
    $classFile = __DIR__ . '/classes/'
        . str_replace('\\', '/', $className)
        . '.php';

    if (is_file($classFile)) {
        require_once $classFile;
    }
});

// Разделение ответственности
foreach ([
             __DIR__ . '/kernel.php',        // env, service locator, конфигурация
             __DIR__ . '/events.php',        // регистрация обработчиков событий
             __DIR__ . '/vendor/autoload.php', // composer-пакеты (если есть)
             __DIR__ . '/legacy.php',        // устаревшие механики
         ] as $filePath) {
    if (is_file($filePath)) {
        require_once $filePath;
    }
}
unset($filePath);