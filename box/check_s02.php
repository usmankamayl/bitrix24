<?php
/**
 * Веб-версия диагностики S02
 * Доступ: https://shop.org/online/check_s02.php
 */

// Базовая защита (убери после проверки)
$allowed_ips = ['127.0.0.1', '::1']; // добавь свой IP если нужно
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
//     die('Access denied');

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/Training/Config/Environment.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/Training/Config/Logger.php';

use Training\Config\Environment;
use Training\Config\Logger;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S02 Диагностика</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'SF Mono', 'Consolas', monospace;
            background: #0B0E14;
            color: #F8FAFC;
            padding: 2rem;
            line-height: 1.6;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            color: #38BDF8;
            border-bottom: 1px solid #1E293B;
            padding-bottom: 0.5rem;
        }
        .section {
            background: #111827;
            border: 1px solid #1E293B;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1.1rem;
            color: #38BDF8;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #1E293B;
        }
        .row:last-child { border-bottom: none; }
        .label { color: #94A3B8; }
        .value { color: #F8FAFC; font-weight: 500; }
        .status-ok { color: #4FD1C5; }
        .status-warn { color: #F59E0B; }
        .status-error { color: #F97316; }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin: 0.5rem 0;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .alert-error {
            background: rgba(249, 115, 22, 0.1);
            border-left: 3px solid #F97316;
        }
        .alert-warn {
            background: rgba(245, 158, 11, 0.1);
            border-left: 3px solid #F59E0B;
        }
        .alert-success {
            background: rgba(79, 209, 197, 0.1);
            border-left: 3px solid #4FD1C5;
        }
        .icon { font-size: 1.2rem; line-height: 1; }
        pre {
            background: #0B0E14;
            padding: 1rem;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 0.9rem;
            border: 1px solid #1E293B;
        }
        .test-result {
            margin-top: 1rem;
            padding: 1rem;
            background: #0B0E14;
            border-radius: 6px;
            border: 1px solid #1E293B;
        }
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #1E293B;
            color: #64748B;
            font-size: 0.9rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 S02 Диагностика — Окружения и логирование</h1>

        <?php
        $errors = [];
        $warnings = [];

        // 1. Окружение
        ?>
        <div class="section">
            <div class="section-title">🌍 Окружение</div>
            <?php
            try {
                $env = Environment::getCurrent();
                echo '<div class="row"><span class="label">Текущее окружение:</span><span class="value">' . htmlspecialchars($env) . '</span></div>';

                if (Environment::isDev()) {
                    echo '<div class="row"><span class="label">Режим разработки:</span><span class="value status-ok">✓ Активен</span></div>';
                    $warnings[] = "Режим разработки активен — не используйте в production!";
                } elseif (Environment::isProd()) {
                    echo '<div class="row"><span class="label">Режим production:</span><span class="value status-ok">✓ Активен</span></div>';
                }

                $debug = Environment::get('debug');
                echo '<div class="row"><span class="label">Debug:</span><span class="value">' . ($debug ? 'включён' : 'выключен') . '</span></div>';

                if ($debug && Environment::isProd()) {
                    $errors[] = "Debug включён в production окружении!";
                }

                $logLevel = Environment::get('log_level');
                echo '<div class="row"><span class="label">Уровень логирования:</span><span class="value">' . htmlspecialchars($logLevel) . '</span></div>';

            } catch (\Exception $e) {
                $errors[] = "Ошибка загрузки окружения: " . $e->getMessage();
                echo '<div class="alert alert-error"><span class="icon">✗</span><div>' . htmlspecialchars($e->getMessage()) . '</div></div>';
            }
            ?>
        </div>

        <!-- 2. Конфигурация API -->
        <div class="section">
            <div class="section-title">🔌 Внешние API</div>
            <?php
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
                echo '<div class="row"><span class="label">' . htmlspecialchars($key) . ':</span><span class="value">' . htmlspecialchars($displayValue) . '</span></div>';
            }
            ?>
        </div>

        <!-- 3. Файловая система -->
        <div class="section">
            <div class="section-title">📁 Файловая система</div>
            <?php
            $paths = [
                'Config directory' => $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/config',
                'Classes directory' => $_SERVER['DOCUMENT_ROOT'] . '/local/classes',
                'Logs directory' => $_SERVER['DOCUMENT_ROOT'] . '/local/logs',
            ];

            foreach ($paths as $name => $path) {
                if (file_exists($path)) {
                    $writable = is_writable($path);
                    $statusClass = $writable ? 'status-ok' : 'status-error';
                    $status = $writable ? '✓ Доступен' : '✗ Не доступен для записи';
                    echo '<div class="row"><span class="label">' . htmlspecialchars($name) . ':</span><span class="value ' . $statusClass . '">' . $status . '</span></div>';

                    if (!$writable) {
                        $errors[] = "$name недоступен для записи: $path";
                    }
                } else {
                    echo '<div class="row"><span class="label">' . htmlspecialchars($name) . ':</span><span class="value status-error">✗ Не существует</span></div>';
                    $errors[] = "$name не существует: $path";
                }
            }
            ?>
        </div>

        <!-- 4. Тест логирования -->
        <div class="section">
            <div class="section-title">📝 Тест логирования и маскирования</div>
            <?php
            try {
                Logger::info('S02 веб-диагностика', [
                    'timestamp' => time(),
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'password' => 'should_be_masked_12345',
                    'api_key' => 'sk_test_1234567890abcdef',
                ]);

                $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/' . date('Y-m-d') . '.log';

                if (file_exists($logFile)) {
                    $logContent = file_get_contents($logFile);

                    // Проверка маскирования
                    $secretsFound = [];
                    if (strpos($logContent, 'should_be_masked_12345') !== false) {
                        $secretsFound[] = 'password';
                    }
                    if (strpos($logContent, 'sk_test_1234567890abcdef') !== false) {
                        $secretsFound[] = 'api_key';
                    }

                    if (!empty($secretsFound)) {
                        $errors[] = "Маскирование НЕ работает! Найдены секреты: " . implode(', ', $secretsFound);
                        echo '<div class="alert alert-error"><span class="icon">✗</span><div>Секреты не маскируются!</div></div>';
                    } else {
                        echo '<div class="alert alert-success"><span class="icon">✓</span><div>Логирование работает, секреты маскируются</div></div>';
                    }

                    echo '<div class="row"><span class="label">Лог файл:</span><span class="value">' . htmlspecialchars($logFile) . '</span></div>';

                    // Последние 2 записи
                    $lines = file($logFile);
                    $lastLines = array_slice($lines, -2);

                    if (!empty($lastLines)) {
                        echo '<div class="test-result"><strong>Последние записи:</strong><pre>';
                        foreach ($lastLines as $line) {
                            $entry = json_decode($line, true);
                            if ($entry) {
                                echo htmlspecialchars(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "\n";
                            }
                        }
                        echo '</pre></div>';
                    }

                } else {
                    $warnings[] = "Лог файл не создан";
                    echo '<div class="alert alert-warn"><span class="icon">⚠</span><div>Лог файл не создан</div></div>';
                }

            } catch (\Exception $e) {
                $errors[] = "Ошибка логирования: " . $e->getMessage();
                echo '<div class="alert alert-error"><span class="icon">✗</span><div>' . htmlspecialchars($e->getMessage()) . '</div></div>';
            }
            ?>
        </div>

        <!-- Итоги -->
        <div class="section">
            <div class="section-title">📊 Итоги</div>

            <?php if (!empty($errors)): ?>
                <h3 style="color: #F97316; margin-bottom: 0.5rem;">Ошибки:</h3>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error">
                        <span class="icon">✗</span>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($warnings)): ?>
                <h3 style="color: #F59E0B; margin-bottom: 0.5rem; margin-top: 1rem;">Предупреждения:</h3>
                <?php foreach ($warnings as $warning): ?>
                    <div class="alert alert-warn">
                        <span class="icon">⚠</span>
                        <div><?= htmlspecialchars($warning) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (empty($errors) && empty($warnings)): ?>
                <div class="alert alert-success">
                    <span class="icon">✓</span>
                    <div>Все проверки пройдены успешно!</div>
                </div>
            <?php elseif (empty($errors)): ?>
                <div class="alert alert-success">
                    <span class="icon">✓</span>
                    <div>Критических ошибок не обнаружено</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            S02 Module — Environment Management & Structured Logging<br>
            Server: <?= htmlspecialchars($_SERVER['HTTP_HOST']) ?> | PHP: <?= PHP_VERSION ?>
        </div>
    </div>
</body>
</html>
