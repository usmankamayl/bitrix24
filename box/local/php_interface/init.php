<?php
/**
 * Точка входа для пользовательских расширений
 * Этот файл автоматически подключается ядром Bitrix при инициализации
 *
 * Документация:
 * https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2830
 */

// Автозагрузка классов из /local/classes
spl_autoload_register(function ($class) {
    $classFile = str_replace('\\', '/', $class) . '.php';
    $classPath = $_SERVER['DOCUMENT_ROOT'] . '/local/classes/' . $classFile;

    if (file_exists($classPath)) {
        require_once $classPath;
        return true;
    }

    return false;
});

// Здесь позже добавим:
// - Регистрацию обработчиков событий
// - Подключение модулей
// - Настройки окружения
