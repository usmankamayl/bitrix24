# Облачные приложения Bitrix24 — OAuth и жизненный цикл

## Модель облачного приложения

В облаке Bitrix24 приложения работают через **OAuth 2.0** и REST API. Разработчик не имеет доступа к файловой системе портала — вся интеграция идёт через HTTP-запросы.

### Типы приложений

1. **Локальное приложение** — устанавливается на один конкретный портал
2. **Массовое приложение** — публикуется в Bitrix24.Market, доступно всем порталам
3. **Webhook** — упрощённый вариант без OAuth (однократный токен)

## Создание локального приложения

### 1. Регистрация приложения

1. Войти в портал как администратор
2. Открыть: **Приложения → Разработчикам → Другое → Локальное приложение**
3. Заполнить форму:
   - **Название**: "Сервисные заявки"
   - **Код**: `service_requests`
   - **URL обработчика**: `https://your-server.com/bitrix24/handler.php`
   - **URL установки**: `https://your-server.com/bitrix24/install.php`
   - **Права**: выбрать необходимые (crm, task, user)

4. Получить учётные данные:
   - `CLIENT_ID` — публичный идентификатор
   - `CLIENT_SECRET` — секретный ключ (хранить безопасно!)

### 2. OAuth-поток установки

**Шаг 1: Пользователь устанавливает приложение**

Bitrix24 перенаправляет на URL установки с параметрами:

```
GET https://your-server.com/bitrix24/install.php?
  code=abc123def&
  domain=portal.bitrix24.ru&
  member_id=1234567890abcdef&
  server_domain=oauth.bitrix.info
```

**Шаг 2: Обмен code на токены**

Приложение отправляет запрос:

```php
POST https://oauth.bitrix.info/oauth/token/

{
  "grant_type": "authorization_code",
  "client_id": "local.12345.67890",
  "client_secret": "your_secret",
  "code": "abc123def"
}
```

Ответ:

```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJh...",
  "refresh_token": "def456ghi789...",
  "expires_in": 3600,
  "member_id": "1234567890abcdef",
  "domain": "portal.bitrix24.ru"
}
```

**Шаг 3: Сохранить токены**

Приложение должно сохранить:
- `access_token` — для API-запросов (срок жизни 1 час)
- `refresh_token` — для обновления токена
- `domain` — домен портала
- `member_id` — уникальный ID установки

### 3. Жизненный цикл токенов

#### Access Token
- **Срок жизни**: 1 час
- **Использование**: в каждом REST API запросе
- **Формат запроса**:
  ```
  https://portal.bitrix24.ru/rest/crm.lead.list?auth=ACCESS_TOKEN
  ```

#### Обновление токена

Когда `access_token` истекает:

```php
POST https://oauth.bitrix.info/oauth/token/

{
  "grant_type": "refresh_token",
  "client_id": "local.12345.67890",
  "client_secret": "your_secret",
  "refresh_token": "def456ghi789..."
}
```

Ответ — новая пара токенов.

**Важно**: старый `refresh_token` становится недействительным!

### 4. Изоляция порталов

Каждая установка приложения уникальна:
- Один портал = один `member_id`
- Токены действуют только для конкретного портала
- Нельзя использовать токен портала A для запросов к порталу B

**Архитектура хранения**:

```
installations/
  portal_a_member_id/
    tokens.json
    config.json
  portal_b_member_id/
    tokens.json
    config.json
```

## Пример класса для управления токенами

```php
<?php

namespace App\Bitrix24;

class TokenManager
{
    private string $storageDir;
    private string $clientId;
    private string $clientSecret;

    public function __construct(string $storageDir, string $clientId, string $clientSecret)
    {
        $this->storageDir = $storageDir;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Получить действующий access_token
     */
    public function getAccessToken(string $memberId): string
    {
        $tokens = $this->loadTokens($memberId);

        // Проверяем срок действия
        if ($tokens['expires_at'] < time()) {
            $tokens = $this->refreshTokens($memberId, $tokens['refresh_token']);
        }

        return $tokens['access_token'];
    }

    /**
     * Обновить токены
     */
    private function refreshTokens(string $memberId, string $refreshToken): array
    {
        $response = $this->httpClient->post('https://oauth.bitrix.info/oauth/token/', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        $data = json_decode($response, true);

        $tokens = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => time() + $data['expires_in'] - 60, // запас 1 минута
            'domain' => $data['domain'],
        ];

        $this->saveTokens($memberId, $tokens);

        return $tokens;
    }

    private function loadTokens(string $memberId): array
    {
        $file = $this->storageDir . '/' . $memberId . '/tokens.json';
        return json_decode(file_get_contents($file), true);
    }

    private function saveTokens(string $memberId, array $tokens): void
    {
        $dir = $this->storageDir . '/' . $memberId;
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents(
            $dir . '/tokens.json',
            json_encode($tokens, JSON_PRETTY_PRINT),
            LOCK_EX
        );
        chmod($dir . '/tokens.json', 0600);
    }
}
```

## События жизненного цикла

Bitrix24 отправляет вебхуки при изменении состояния приложения:

- **ONAPPINSTALL** — приложение установлено
- **ONAPPUPDATE** — права изменены
- **ONAPPUNINSTALL** — приложение удалено (токены больше не действительны)

## Ключевые отличия облака от коробки

| Аспект | Облако | Коробка |
|--------|--------|---------|
| Авторизация | OAuth 2.0 | REST API напрямую или локальная сессия |
| Установка | Через интерфейс портала | Копирование файлов в `/local` |
| Токены | Обновляются каждый час | Не требуются для D7 API |
| Изоляция | По `member_id` | По конфигурации окружения |
| Доступ к файлам | Нет | Полный |
| PHP-код | Не выполняется на портале | Выполняется на сервере |

## Контрольные вопросы

1. Что произойдёт, если не обновлять `access_token`?
2. Можно ли использовать один `refresh_token` дважды?
3. Как определить, для какого портала пришёл вебхук?
4. Где безопасно хранить `CLIENT_SECRET`?
5. Что делать, если пользователь удалил приложение, а затем установил снова?

## Требования для проверки (когда будет облачный портал)

- [ ] Создать локальное приложение
- [ ] Получить CLIENT_ID и CLIENT_SECRET
- [ ] Реализовать OAuth-обмен
- [ ] Сохранить токены в защищённое хранилище
- [ ] Проверить автообновление токена
- [ ] Протестировать изоляцию между двумя порталами
- [ ] Обработать удаление приложения (ONAPPUNINSTALL)
