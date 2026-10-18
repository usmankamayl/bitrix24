# Файлы для копирования на сервер

## Структура /local на сервере

После копирования структура должна выглядеть так:

```
/local/
  php_interface/
    init.php
  classes/
    Training/
      Environment.php
```

## Инструкция по развёртыванию

### 1. Скопируй содержимое на сервер

Из корня репозитория `box/local/` скопируй на сервер в `/путь/к/bitrix/local/`:

```bash
# На сервере (от root или через sudo)
cd /home/bitrix/www  # замени на твой путь

# Создай структуру папок
mkdir -p local/php_interface
mkdir -p local/classes/Training

# Установи владельца
chown -R bitrix:bitrix local/
chmod -R 755 local/
```

Затем вручную или через SCP/FTP скопируй:
- `box/local/php_interface/init.php` → `/local/php_interface/init.php`
- `box/local/classes/Training/Environment.php` → `/local/classes/Training/Environment.php`

### 2. Проверь автозагрузку

Скопируй `box/diagnostics/test_local.php` в корень установки Bitrix24 и открой в браузере:

```
https://твой-домен/test_local.php
```

Должен увидеть:
```
=== TESTING /local CLASS LOADING ===

✓ Class Training\Environment loaded

hello() result:
Hello, Bitrix24! Training\Environment class loaded successfully.

getInfo() result:
{
    "php_version": "8.2.33",
    "bitrix_loaded": true,
    ...
}

=== TEST PASSED ===
```

### 3. После проверки

Удали `test_local.php` с сервера.

## Объяснение

**`/local/php_interface/init.php`**
- Автоматически подключается ядром Bitrix при каждом запросе
- Регистрирует автозагрузчик для классов из `/local/classes/`
- Здесь позже будем регистрировать обработчики событий

**`/local/classes/Training/Environment.php`**
- Простой класс-утилита в пространстве имён `Training`
- Показывает, что класс успешно загружается через автозагрузчик
- Предоставляет методы для получения информации об окружении

**Почему `/local`, а не `/bitrix`?**
- `/bitrix` — ядро системы, обновляется автоматически
- Любые изменения в `/bitrix` будут потеряны при обновлении
- `/local` — защищённая зона для пользовательского кода
