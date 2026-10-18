<?php
/**
 * Проверка логирования с маскированием чувствительных данных
 */

$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/..';

require_once __DIR__ . '/../local/classes/Training/Config/Environment.php';
require_once __DIR__ . '/../local/classes/Training/Config/Logger.php';

use Training\Config\Logger;
use Training\Config\Environment;

echo "=== Проверка структурированного логирования ===\n\n";

// Текущее окружение
echo "Текущее окружение: " . Environment::getCurrent() . "\n";
echo "Уровень логирования: " . Environment::get('log_level') . "\n\n";

// 1. Логирование на разных уровнях
echo "1. Записываем сообщения на разных уровнях:\n";

Logger::debug('Отладочное сообщение', ['step' => 1, 'action' => 'test']);
Logger::info('Информационное сообщение', ['user_id' => 123]);
Logger::warning('Предупреждение', ['attempt' => 3, 'max_attempts' => 5]);
Logger::error('Ошибка', ['exception' => 'RuntimeException', 'message' => 'Test error']);

echo "   ✓ Записано 4 сообщения\n\n";

// 2. Проверка маскирования
echo "2. Проверка маскирования чувствительных данных:\n";

Logger::info('API запрос', [
    'url' => 'https://api.example.com/users',
    'method' => 'POST',
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer abc123def456ghi789',
    ],
    'body' => [
        'username' => 'testuser',
        'password' => 'SecretPassword123',
        'email' => 'test@example.com',
    ],
    'api_key' => 'sk_live_1234567890abcdef',
]);

Logger::info('Webhook получен', [
    'webhook_url' => 'https://shop.org/hook/12345/abc',
    'token' => 'webhook_token_very_secret',
    'payload' => [
        'event' => 'ONCRMLEADADD',
        'data' => ['ID' => 456],
    ],
]);

echo "   ✓ Записаны сообщения с чувствительными данными\n\n";

// 3. Читаем лог и проверяем маскирование
$logFile = __DIR__ . '/../local/logs/' . date('Y-m-d') . '.log';

if (file_exists($logFile)) {
    echo "3. Содержимое лога (последние 2 записи):\n\n";

    $lines = file($logFile);
    $lastLines = array_slice($lines, -2);

    foreach ($lastLines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            echo "   [{$entry['timestamp']}] {$entry['level']}: {$entry['message']}\n";
            echo "   Context: " . json_encode($entry['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        }
    }

    // Проверяем, что секреты замаскированы
    $logContent = file_get_contents($logFile);

    echo "4. Проверка безопасности:\n";

    $secrets = [
        'SecretPassword123' => false,
        'abc123def456ghi789' => false,
        'sk_live_1234567890abcdef' => false,
        'webhook_token_very_secret' => false,
    ];

    foreach ($secrets as $secret => $found) {
        if (strpos($logContent, $secret) !== false) {
            echo "   ✗ ОПАСНО: Секрет '$secret' найден в логе!\n";
        } else {
            echo "   ✓ Секрет замаскирован\n";
        }
    }

    echo "\n✓ Проверка завершена\n";
    echo "\nЛог файл: $logFile\n";
} else {
    echo "✗ Лог файл не создан\n";
}
