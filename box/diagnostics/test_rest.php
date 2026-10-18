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

// === ТЕСТ 1: Получение данных напрямую через D7 (аналог REST user.current) ===
echo "--- TEST 1: Get user data (D7 API equivalent to REST user.current) ---\n";
try {
    // В коробке REST методы часто просто обёртки над D7 API
    // Получаем данные текущего пользователя напрямую через D7

    $userFields = \Bitrix\Main\UserTable::getById($USER->GetID())->fetch();

    if ($userFields) {
        echo "✓ User data retrieved successfully\n";

        // Форматируем похоже на REST ответ
        $userData = [
            'ID' => $userFields['ID'],
            'NAME' => $userFields['NAME'],
            'LAST_NAME' => $userFields['LAST_NAME'],
            'EMAIL' => $userFields['EMAIL'],
            'LOGIN' => $userFields['LOGIN'],
            'ACTIVE' => $userFields['ACTIVE'],
            'IS_ADMIN' => $USER->IsAdmin(),
        ];

        echo "Response (via D7):\n";
        echo json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

        echo "✓ This is how REST API works internally in box version\n";
        echo "  REST methods are wrappers over D7 API\n\n";
    } else {
        echo "✗ Could not get user data\n\n";
    }

} catch (\Exception $e) {
    echo "✗ Exception caught:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n\n";
}

// === ТЕСТ 2: REST через HTTP (как внешние приложения) ===
echo "--- TEST 2: REST API via HTTP (external way) ---\n";

// Для внешних REST запросов в коробке нужен вебхук или OAuth токен
// Проверим, есть ли установленные REST приложения или вебхуки

try {
    \Bitrix\Main\Loader::includeModule('rest');

    // Проверяем наличие входящих вебхуков
    $webhooks = \Bitrix\Rest\APAuth\PasswordTable::getList([
        'filter' => ['USER_ID' => $USER->GetID()],
        'limit' => 1
    ])->fetch();

    if ($webhooks) {
        echo "✓ Incoming webhooks available for user\n";
        echo "  Note: To test REST via HTTP, create incoming webhook in admin panel\n";
        echo "  Settings > Developer resources > Other > Incoming webhook\n\n";
    } else {
        echo "ℹ No incoming webhooks found\n";
        echo "  For external REST calls, create incoming webhook:\n";
        echo "  Settings > Developer resources > Other > Incoming webhook\n\n";
    }

} catch (\Exception $e) {
    echo "✗ Could not check webhooks\n";
    echo "Message: " . $e->getMessage() . "\n\n";
}

// === ТЕСТ 3: Доступные REST контроллеры ===
echo "--- TEST 3: Available REST controllers ---\n";
try {
    // В коробке REST контроллеры регистрируются через события
    // Проверим список зарегистрированных обработчиков

    $event = new \Bitrix\Main\Event('rest', 'OnRestServiceBuildDescription');
    $event->send();

    $handlers = $event->getResults();
    $controllerCount = 0;

    if ($handlers) {
        foreach ($handlers as $handler) {
            if ($handler->getType() == \Bitrix\Main\EventResult::SUCCESS) {
                $data = $handler->getParameters();
                if (is_array($data)) {
                    $controllerCount += count($data);
                }
            }
        }
    }

    echo "✓ REST module is active\n";
    echo "  REST handlers registered: " . $controllerCount . "\n\n";

    // Проверяем базовые scope
    $availableScopes = ['user', 'crm', 'tasks', 'disk', 'calendar', 'im'];
    echo "Common scopes in box:\n";
    foreach ($availableScopes as $scope) {
        echo "  - {$scope}.*\n";
    }
    echo "\n";

} catch (\Exception $e) {
    echo "✗ Could not check REST handlers\n";
    echo "Message: " . $e->getMessage() . "\n\n";
}

echo "=== REST API TEST COMPLETE ===\n";
echo "Удали этот файл после проверки.\n";

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
