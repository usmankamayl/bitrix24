<?php
/**
 * Проверка работы управления окружениями
 *
 * Использование:
 * php box/tests/test_environment.php
 */

require_once __DIR__ . '/../local/php_interface/init.php';

use Training\Config\Environment;

echo "=== Проверка управления окружениями ===\n\n";

// Текущее окружение
echo "1. Текущее окружение: " . Environment::getCurrent() . "\n";
echo "   - isDev(): " . (Environment::isDev() ? 'true' : 'false') . "\n";
echo "   - isTest(): " . (Environment::isTest() ? 'true' : 'false') . "\n";
echo "   - isProd(): " . (Environment::isProd() ? 'true' : 'false') . "\n\n";

// Получение параметров
echo "2. Параметры конфигурации:\n";
echo "   - debug: " . json_encode(Environment::get('debug')) . "\n";
echo "   - log_level: " . Environment::get('log_level') . "\n";
echo "   - external_api.timeout: " . Environment::get('external_api.timeout') . "\n";
echo "   - external_api.base_url: " . Environment::get('external_api.base_url') . "\n";
echo "   - external_api.verify_ssl: " . json_encode(Environment::get('external_api.verify_ssl')) . "\n\n";

// Несуществующий параметр
echo "3. Несуществующий параметр:\n";
echo "   - nonexistent (default null): " . json_encode(Environment::get('nonexistent')) . "\n";
echo "   - nonexistent (default 'test'): " . Environment::get('nonexistent', 'test') . "\n\n";

// Вся конфигурация
echo "4. Вся конфигурация текущего окружения:\n";
print_r(Environment::getAll());

echo "\n✓ Проверка завершена\n";
