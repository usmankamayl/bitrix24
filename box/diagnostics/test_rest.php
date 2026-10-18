<?php
/**
 * Тестовый скрипт для проверки REST API в коробке
 *
 * Инструкция:
 * 1. Скопируй этот файл в корень установки Bitrix24
 * 2. Авторизуйся в админке Bitrix24
 * 3. Открой в браузере: https://твой-домен/test_rest.php
 * 4. Скопируй вывод (без токенов, если они появятся)
 * 5. УДАЛИ этот файл после проверки
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Проверяем авторизацию
global $USER;
if (!$USER || !$USER->IsAuthorized()) {
    die("ERROR: You must be authorized to test REST API\nPlease login to Bitrix24 admin panel first.");
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTING REST API IN BOX ===\n\n";
echo "Current user ID: " . $USER->GetID() . "\n";
echo "Is admin: " . ($USER->IsAdmin() ? 'YES' : 'NO') . "\n\n";

// Проверяем модуль REST
if (!\Bitrix\Main\Loader::includeModule('rest')) {
    die("ERROR: REST module not loaded\n");
}

echo "✓ REST module loaded\n\n";

// === ТЕСТ 1: Успешный запрос через CRestUtil ===
echo "--- TEST 1: Valid REST request (user.current) ---\n";
try {
    // В коробке используем CRestUtil для выполнения REST методов
    // Эмулируем REST запрос через внутренний API

    $queryData = [
        'METHOD' => 'GET',
        'PARAMETERS' => []
    ];

    // Вызов метода user.current (получить текущего пользователя)
    $restResult = \CRestUtil::invokeMethod(
        'user.current',
        $queryData['PARAMETERS'],
        $queryData['METHOD']
    );

    if (is_array($restResult) && isset($restResult['result'])) {
        echo "✓ Request successful\n";
        echo "Response:\n";
        echo json_encode($restResult['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        echo "✓ Response received:\n";
        echo json_encode($restResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

} catch (\Exception $e) {
    echo "✗ Exception caught:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n\n";
}

// === ТЕСТ 2: Некорректный запрос (проверка обработки ошибки) ===
echo "--- TEST 2: Invalid REST request (method does not exist) ---\n";
try {
    $restResult = \CRestUtil::invokeMethod(
        'invalid.method.that.does.not.exist',
        [],
        'GET'
    );

    if (is_array($restResult) && isset($restResult['error'])) {
        echo "✓ Error handled correctly\n";
        echo "Error code: " . ($restResult['error'] ?? 'unknown') . "\n";
        echo "Error description: " . ($restResult['error_description'] ?? 'no description') . "\n\n";
    } else {
        echo "Response:\n";
        echo json_encode($restResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

} catch (\Exception $e) {
    echo "✓ Exception caught (expected):\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n\n";
}

// === ТЕСТ 3: Список доступных REST методов ===
echo "--- TEST 3: Available REST methods ---\n";
try {
    $restResult = \CRestUtil::invokeMethod(
        'methods',
        [],
        'GET'
    );

    if (is_array($restResult) && isset($restResult['result'])) {
        $methods = $restResult['result'];
        $scopeCount = [];

        foreach ($methods as $method => $info) {
            $scope = explode('.', $method)[0];
            $scopeCount[$scope] = ($scopeCount[$scope] ?? 0) + 1;
        }

        echo "✓ Total methods: " . count($methods) . "\n";
        echo "Top scopes:\n";

        arsort($scopeCount);
        foreach (array_slice($scopeCount, 0, 10, true) as $scope => $count) {
            echo "  - {$scope}: {$count} methods\n";
        }
        echo "\n";
    } else {
        echo "Response:\n";
        echo json_encode($restResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

} catch (\Exception $e) {
    echo "✗ Could not get methods list\n";
    echo "Message: " . $e->getMessage() . "\n\n";
}

echo "=== REST API TEST COMPLETE ===\n";
echo "Удали этот файл после проверки.\n";

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
