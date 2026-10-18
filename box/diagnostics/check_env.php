<?php
/**
 * Диагностический скрипт для проверки окружения Bitrix24 коробки
 *
 * Инструкция:
 * 1. Скопируй этот файл в корень установки Bitrix24 (рядом с /bitrix)
 * 2. Открой в браузере: https://твой-домен/check_env.php
 * 3. Скопируй вывод и передай Claude (без секретов, если они попадут)
 * 4. УДАЛИ файл после проверки
 */

// Попытка подключить ядро Bitrix
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$bitrixPath = $documentRoot . '/bitrix/modules/main/include/prolog_before.php';

$bitrixLoaded = false;
if (file_exists($bitrixPath)) {
    require_once $bitrixPath;
    $bitrixLoaded = true;
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== BITRIX24 ENVIRONMENT DIAGNOSTICS ===\n\n";

// PHP версия
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n\n";

// Bitrix подключён?
if ($bitrixLoaded) {
    echo "Bitrix Core: LOADED\n";

    // Версия Bitrix
    if (defined('SM_VERSION')) {
        echo "Bitrix24 Version: " . SM_VERSION . "\n";
    } elseif (class_exists('\Bitrix\Main\ModuleManager')) {
        $mainModule = \Bitrix\Main\ModuleManager::getInstalledModules()['main'] ?? null;
        if ($mainModule) {
            echo "Bitrix Version: " . ($mainModule['VERSION'] ?? 'unknown') . "\n";
        }
    }

    // D7 доступен?
    echo "D7 ORM Available: " . (class_exists('\Bitrix\Main\Entity\DataManager') ? 'YES' : 'NO') . "\n";
    echo "REST API Module: " . (\Bitrix\Main\Loader::includeModule('rest') ? 'YES' : 'NO') . "\n\n";

} else {
    echo "Bitrix Core: NOT LOADED (файл запущен вне установки или путь неверен)\n\n";
}

// Структура /local
$localPath = $documentRoot . '/local';
echo "=== /local STRUCTURE ===\n";
if (is_dir($localPath)) {
    echo "Path: $localPath\n";
    echo "Writable: " . (is_writable($localPath) ? 'YES' : 'NO') . "\n";
    echo "Contents:\n";

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $items = [];
    foreach ($iterator as $item) {
        $relativePath = str_replace($localPath, '', $item->getPathname());
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($iterator->getDepth() < 3) { // ограничиваем глубину
            $items[] = ($item->isDir() ? '[DIR]  ' : '[FILE] ') . $relativePath;
        }
    }

    sort($items);
    echo implode("\n", array_slice($items, 0, 50)); // первые 50 элементов
    if (count($items) > 50) {
        echo "\n... и ещё " . (count($items) - 50) . " элементов\n";
    }
} else {
    echo "/local directory: NOT FOUND\n";
}

echo "\n\n=== PHP MODULES ===\n";
$requiredExtensions = ['mbstring', 'json', 'curl', 'mysqli', 'gd'];
foreach ($requiredExtensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? 'YES' : 'NO') . "\n";
}

echo "\n=== ENVIRONMENT CHECK COMPLETE ===\n";
echo "Удали этот файл после копирования результата.\n";
