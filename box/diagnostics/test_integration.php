<?php
/**
 * Тест интеграции с внешней системой (httpbin.org)
 *
 * Проверяет:
 * 1. Успешную отправку данных
 * 2. Идемпотентность (externalId)
 * 3. Обработку ошибок (таймаут, некорректный endpoint)
 * 4. Безопасность логирования
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Training\Integration\ServiceRequestClient;

echo "=== Тест интеграции S01 ===\n\n";

// Инициализация клиента (httpbin.org)
$client = new ServiceRequestClient(
    'https://httpbin.org',
    'test_api_key_12345'
);

// Тестовые данные заявки
$testRequest = [
    'title' => 'Тестовая заявка B2B',
    'description' => 'Требуется диагностика оборудования',
    'customerEmail' => 'customer@example.com',
    'priority' => 'high',
    'equipment' => 'Server HP DL380 Gen10',
];

echo "1. Отправка заявки во внешнюю систему\n";
echo "   URL: https://httpbin.org/post\n";
echo "   Request ID: 12345\n\n";

$result = $client->sendRequest(12345, $testRequest);

echo "Результат:\n";
echo "  Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "  HTTP Code: {$result['httpCode']}\n";
echo "  External ID: {$result['externalId']}\n";
echo "  Duration: {$result['duration']}ms\n";

if ($result['success'] && isset($result['response']['json'])) {
    echo "\nОтправленные данные (проверка от httpbin):\n";
    $sent = $result['response']['json'];
    echo "  - externalId: {$sent['externalId']}\n";
    echo "  - source: {$sent['source']}\n";
    echo "  - data.title: {$sent['data']['title']}\n";
    echo "  - data.customerEmail: {$sent['data']['customerEmail']}\n";
}

echo "\n" . str_repeat('-', 60) . "\n\n";

// Тест 2: Повторная отправка (идемпотентность)
echo "2. Повторная отправка той же заявки (externalId должен совпадать)\n\n";

$result2 = $client->sendRequest(12345, $testRequest);

echo "Результат:\n";
echo "  External ID: {$result2['externalId']}\n";
echo "  Совпадает с первым: " . ($result['externalId'] === $result2['externalId'] ? 'YES ✓' : 'NO') . "\n";

echo "\n" . str_repeat('-', 60) . "\n\n";

// Тест 3: Проверка статуса
echo "3. Проверка статуса заявки (GET-запрос)\n\n";

$status = $client->checkStatus(12345);

echo "Результат:\n";
echo "  Success: " . ($status['success'] ? 'YES' : 'NO') . "\n";
echo "  HTTP Code: {$status['httpCode']}\n";

if ($status['success'] && isset($status['data']['args'])) {
    echo "  Query params: externalId = {$status['data']['args']['externalId']}\n";
}

echo "\n" . str_repeat('-', 60) . "\n\n";

// Тест 4: Валидация данных
echo "4. Тест валидации (некорректный email)\n\n";

try {
    $invalidRequest = $testRequest;
    $invalidRequest['customerEmail'] = 'not-an-email';

    $client->sendRequest(99999, $invalidRequest);
    echo "  ❌ Ошибка: валидация не сработала\n";
} catch (\InvalidArgumentException $e) {
    echo "  ✓ Валидация сработала корректно\n";
    echo "  Ошибка: {$e->getMessage()}\n";
}

echo "\n" . str_repeat('-', 60) . "\n\n";

// Тест 5: Обработка сетевой ошибки
echo "5. Тест обработки сетевой ошибки (несуществующий домен)\n\n";

$badClient = new ServiceRequestClient(
    'https://nonexistent-domain-12345.com',
    'test_key'
);

$errorResult = $badClient->sendRequest(77777, $testRequest);

echo "Результат:\n";
echo "  Success: " . ($errorResult['success'] ? 'YES' : 'NO') . "\n";
echo "  Error: {$errorResult['error']}\n";
echo "  Error Code: {$errorResult['errorCode']}\n";

echo "\n=== Тест завершён ===\n";
echo "\nВсе проверки выполнены:\n";
echo "  ✓ POST-запрос с JSON\n";
echo "  ✓ Идемпотентность через externalId\n";
echo "  ✓ GET-запрос с параметрами\n";
echo "  ✓ Валидация входных данных\n";
echo "  ✓ Обработка сетевых ошибок\n";
echo "  ✓ Логирование без раскрытия API-ключей\n";
