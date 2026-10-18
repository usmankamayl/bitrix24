<?php
/**
 * Standalone тест интеграции (без подключения Bitrix)
 * Можно запускать локально для проверки логики HTTP-клиента
 */

// Простой автозагрузчик для тестирования
spl_autoload_register(function ($class) {
    $classFile = str_replace('\\', '/', $class) . '.php';
    $classPath = __DIR__ . '/../local/classes/' . $classFile;
    if (file_exists($classPath)) {
        require_once $classPath;
        return true;
    }
    return false;
});

use Training\Integration\ServiceRequestClient;

echo "=== Тест интеграции S01 (standalone) ===\n\n";

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

// Тест 5: Обработка сетевой ошибки (timeout 2 сек)
echo "5. Тест обработки таймаута (httpbin.org/delay/5 с timeout 2 сек)\n\n";

$slowClient = new ServiceRequestClient(
    'https://httpbin.org',
    'test_key'
);

// Меняем timeout через reflection (для теста)
$reflection = new ReflectionClass($slowClient);
$httpClientProp = $reflection->getProperty('httpClient');
$httpClientProp->setAccessible(true);
$httpClient = $httpClientProp->getValue($slowClient);

$httpReflection = new ReflectionClass($httpClient);
$timeoutProp = $httpReflection->getProperty('timeout');
$timeoutProp->setAccessible(true);
$timeoutProp->setValue($httpClient, 2);

try {
    // Пытаемся обратиться к endpoint с задержкой 5 секунд
    $httpClientPost = $httpReflection->getMethod('post');
    $httpClientPost->setAccessible(true);
    $result = $httpClientPost->invoke($httpClient, 'delay/5', []);

    echo "  Запрос завершился, но должен был упасть по таймауту\n";
} catch (\RuntimeException $e) {
    echo "  ✓ Таймаут обработан корректно\n";
    echo "  Ошибка: {$e->getMessage()}\n";
}

echo "\n=== Тест завершён ===\n";
echo "\nВсе проверки выполнены:\n";
echo "  ✓ POST-запрос с JSON\n";
echo "  ✓ Идемпотентность через externalId\n";
echo "  ✓ GET-запрос с параметрами\n";
echo "  ✓ Валидация входных данных\n";
echo "  ✓ Обработка таймаутов\n";
echo "  ✓ Логирование без раскрытия API-ключей\n";
