<?php
/**
 * Тестовый скрипт для проверки подключения класса из /local
 *
 * Инструкция:
 * 1. Скопируй содержимое box/local/ на сервер в /local/
 * 2. Скопируй этот файл в корень установки Bitrix24
 * 3. Открой в браузере: https://твой-домен/test_local.php
 * 4. Скопируй вывод
 * 5. УДАЛИ этот файл после проверки
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTING /local CLASS LOADING ===\n\n";

try {
    // Проверяем, что класс доступен
    if (!class_exists('Training\Environment')) {
        throw new Exception('Class Training\Environment not found. Check /local/php_interface/init.php and /local/classes/');
    }

    echo "✓ Class Training\\Environment loaded\n\n";

    // Тестируем метод hello
    $helloMessage = \Training\Environment::hello('Bitrix24');
    echo "hello() result:\n";
    echo $helloMessage . "\n\n";

    // Получаем информацию об окружении
    $info = \Training\Environment::getInfo();
    echo "getInfo() result:\n";
    echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    echo "=== TEST PASSED ===\n";

} catch (Exception $e) {
    echo "✗ TEST FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nУдали этот файл после проверки.\n";

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
