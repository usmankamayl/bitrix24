# S01 — Интеграции: практическое руководство

## Что реализовано

### 1. HTTP-клиент (`Training\Integration\HttpClient`)

**Файл:** `box/local/classes/Training/Integration/HttpClient.php`

Универсальный HTTP-клиент для работы с внешними API:
- ✅ Настраиваемые таймауты (connect + total)
- ✅ SSL-верификация
- ✅ Автоматическая обработка JSON
- ✅ Логирование без раскрытия секретов
- ✅ Обработка сетевых ошибок

**Пример использования:**

```php
$client = new HttpClient(
    'https://api.example.com',
    ['Authorization' => 'Bearer token'],
    timeout: 30,
    connectTimeout: 10
);

$response = $client->post('endpoint', ['data' => 'value']);
```

### 2. Клиент для B2B-заявок (`ServiceRequestClient`)

**Файл:** `box/local/classes/Training/Integration/ServiceRequestClient.php`

Специализированный клиент для отправки заявок:
- ✅ Идемпотентность через `externalId` (формат: `bitrix24_request_{id}`)
- ✅ Валидация обязательных полей
- ✅ Методы `sendRequest()` и `checkStatus()`
- ✅ Обработка ошибок валидации и сети

**Пример:**

```php
$client = new ServiceRequestClient(
    'https://external-api.com',
    'api_key_12345'
);

$result = $client->sendRequest(12345, [
    'title' => 'Заявка на диагностику',
    'description' => 'Описание проблемы',
    'customerEmail' => 'customer@example.com',
]);

if ($result['success']) {
    echo "Заявка отправлена: {$result['externalId']}";
}
```

### 3. Тесты

**Standalone тест:** `box/diagnostics/test_integration_standalone.php`
- Работает без подключения Bitrix
- Использует httpbin.org для проверки

**Bitrix-интегрированный тест:** `box/diagnostics/test_integration.php`
- Запускается на коробке через prolog

## Проверенные сценарии

### ✅ Тест 1: POST-запрос с JSON
- Отправка структурированных данных
- Корректная сериализация JSON
- HTTP 200, данные получены

### ✅ Тест 2: Идемпотентность
- Повторная отправка той же заявки
- `externalId` совпадает → внешняя система может распознать дубль

### ✅ Тест 3: GET-запрос с параметрами
- Проверка статуса через `checkStatus()`
- Query-параметры передаются корректно

### ✅ Тест 4: Валидация входных данных
- Некорректный email отклоняется
- `InvalidArgumentException` с понятным сообщением

### ✅ Тест 5: Обработка таймаутов
- Запрос к `/delay/5` с timeout 2 сек
- CURL error 28 обрабатывается → `RuntimeException`
- Приложение не падает, ошибка возвращается структурированно

## Безопасность

### ✅ Секреты не в коде
- Создан `.env.example` с шаблоном конфигурации
- `.gitignore` защищает `.env` от попадания в Git

### ✅ Логирование без раскрытия секретов
Метод `HttpClient::log()` маскирует query-параметры:
```php
// До: https://api.com?token=secret123&key=abc
// После: https://api.com?token=***&key=***
```

### ✅ SSL-верификация включена
```php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
```

## Ключевые различия: облако vs коробка

| Аспект | Облако | Коробка |
|--------|--------|---------|
| **Исходящие запросы** | Через REST API Bitrix24 (метод `app.send.request`) или внешний сервер-прокси | Прямые HTTP-запросы через `curl` |
| **Входящие вебхуки** | Публичный URL от Bitrix24 | Собственный endpoint в `/local` |
| **Хранение credentials** | В настройках приложения (OAuth) | `.env` или конфиг в `/local` |
| **Таймауты** | Ограничены платформой (~30 сек) | Настраиваются в `curl` |

## Для облака (когда настроим портал)

В облаке исходящие запросы делаются через метод `app.send.request`:

```javascript
// JavaScript в приложении
BX24.callMethod('app.send.request', {
    method: 'POST',
    url: 'https://external-api.com/endpoint',
    headers: {
        'Authorization': 'Bearer token'
    },
    data: {
        externalId: 'bitrix24_request_12345',
        source: 'bitrix24'
    }
}, function(result) {
    if (result.error()) {
        console.error(result.error());
    } else {
        console.log(result.data());
    }
});
```

**Ограничение:** нельзя установить произвольный timeout, платформа контролирует лимиты.

## Идемпотентность

**Проблема:** сеть может упасть после отправки, но до получения ответа. Повторный запрос создаст дубль.

**Решение:** `externalId = "bitrix24_request_{$requestId}"`

Внешняя система должна:
1. Проверять `externalId` перед созданием записи
2. Если запись с таким `externalId` существует → вернуть её, не создавая дубль

**Пример логики на стороне внешней системы:**

```php
// Псевдокод внешнего API
$externalId = $request->json('externalId');

$existing = DB::table('requests')->where('external_id', $externalId)->first();

if ($existing) {
    return response()->json($existing, 200); // уже обработано
}

$newRequest = DB::table('requests')->insert([
    'external_id' => $externalId,
    'data' => $request->json('data'),
]);

return response()->json($newRequest, 201);
```

## Следующий шаг

Модуль S01 завершён. Переходим к **S02 — Приложения и авторизация**:
- OAuth 2.0 для облачных приложений
- Обмен авторизационным кодом на токен
- Refresh token и обновление доступа
