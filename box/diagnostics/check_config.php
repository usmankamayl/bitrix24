<?php
/**
 * Диагностический скрипт — проверка окружения и конфигурации
 *
 * Использование:
 * php box/diagnostics/check_config.php
 */

$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/..';

require_once __DIR__ . '/../local/classes/Training/Config/Environment.php';
require_once __DIR__ . '/../local/classes/Training/Config/Logger.php';

use Training\Config\Environment;
use Training\Config\Logger;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║         Диагностика конфигурации Bitrix24                ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

$errors = [];
$warnings = [];

// 1. Окружение
echo "┌─ Окружение ─────────────────────────────────────────────┐\n";
try {
    $env = Environment::getCurrent();
    echo "│ Текущее окружение: $env\n";

    if (Environment::isDev()) {
        echo "│ Режим разработки: ✓\n";
        $warnings[] = "Режим разработки активен — не используйте в production!";
    } elseif (Environment::isProd()) {
        echo "│ Режим production: ✓\n";
    }

    $debug = Environment::get('debug');
    echo "│ Debug: " . ($debug ? 'включён' : 'выключен') . "\n";

    if ($debug && Environment::isProd()) {
        $errors[] = "Debug включён в production окружении!";
    }

    $logLevel = Environment::get('log_level');
    echo "│ Уровень логирования: $logLevel\n";
} catch (\Exception $e) {
    $errors[] = "Ошибка загрузки окружения: " . $e->getMessage();
}
echo "└─────────────────────────────────────────────────────────┘\n\n";

// 2. Конфигурация внешних API
echo "┌─ Внешние API ───────────────────────────────────────────┐\n";
$apiConfig = Environment::get('external_api', []);

foreach ($apiConfig as $key => $value) {
    $displayValue = $value;
    if ($key === 'verify_ssl') {
        $displayValue = $value ? 'да' : 'нет';

        if (!$value && Environment::isProd()) {
            $errors[] = "SSL verification отключена в production!";
        } elseif (!$value) {
            $warnings[] = "SSL verification отключена";
        }
    }
    echo "│ $key: $displayValue\n";
}
echo "└─────────────────────────────────────────────────────────┘\n\n";

// 3. Файловая система
echo "┌─ Файловая система ──────────────────────────────────────┐\n";

$paths = [
    'Config directory' => __DIR__ . '/../local/php_interface/config',
    'Classes directory' => __DIR__ . '/../local/classes',
    'Logs directory' => __DIR__ . '/../local/logs',
];

foreach ($paths as $name => $path) {
    if (file_exists($path)) {
        $writable = is_writable($path);
        $status = $writable ? '✓' : '✗ не доступен для записи';
        echo "│ $name: $status\n";

        if (!$writable) {
            $errors[] = "$name недоступен для записи: $path";
        }
    } else {
        echo "│ $name: ✗ не существует\n";
        $errors[] = "$name не существует: $path";
    }
}
echo "└─────────────────────────────────────────────────────────┘\n\n";

// 4. Тест логирования
echo "┌─ Тест логирования ──────────────────────────────────────┐\n";
try {
    Logger::info('Диагностический тест', [
        'timestamp' => time(),
        'test_data' => 'sample',
        'password' => 'should_be_masked', // Этот пароль должен быть замаскирован
    ]);

    $logFile = __DIR__ . '/../local/logs/' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        if (strpos($logContent, 'should_be_masked') !== false) {
            $errors[] = "Маскирование чувствительных данных НЕ работает!";
            echo "│ Логирование: ✗ секреты не маскируются\n";
        } else {
            echo "│ Логирование: ✓\n";
            echo "│ Лог файл: $logFile\n";
        }
    } else {
        $warnings[] = "Лог файл не создан";
    }
} catch (\Exception $e) {
    $errors[] = "Ошибка логирования: " . $e->getMessage();
}
echo "└─────────────────────────────────────────────────────────┘\n\n";

// Итоги
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                        Итоги                             ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

if (!empty($errors)) {
    echo "ОШИБКИ:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "ПРЕДУПРЕЖДЕНИЯ:\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ $warning\n";
    }
    echo "\n";
}

if (empty($errors) && empty($warnings)) {
    echo "✓ Все проверки пройдены успешно!\n\n";
} elseif (empty($errors)) {
    echo "✓ Критических ошибок не обнаружено\n\n";
} else {
    echo "✗ Обнаружены критические проблемы\n\n";
    exit(1);
}
