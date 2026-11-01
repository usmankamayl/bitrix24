<?php

/**
 * Ядро приложения: загрузка окружения и общих сервисов.
 * Подключается из init.php до регистрации событий.
 */

declare(strict_types=1);

// --- Загрузка переменных окружения из .env.local (dev) ---
// В prod/test секреты приходят из php-fpm pool / systemd EnvironmentFile,
// файл .env.local там отсутствует — это норма.
$envLocal = __DIR__ . '/config/.env.local';

if (is_file($envLocal) && is_readable($envLocal)) {
    $lines = file($envLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new \RuntimeException('Не удалось прочитать .env.local');
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        // Не перетираем переменные, уже заданные на уровне процесса (prod-safe)
        if (getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

unset($envLocal, $lines, $line, $key, $value);