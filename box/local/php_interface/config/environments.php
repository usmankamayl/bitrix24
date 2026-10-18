<?php
/**
 * Настройки окружений
 *
 * Каждое окружение содержит свои параметры:
 * - debug: режим отладки
 * - log_level: уровень логирования (debug, info, warning, error)
 * - external_api: настройки внешних API
 */

return [
    'dev' => [
        'debug' => true,
        'log_level' => 'debug',
        'external_api' => [
            'timeout' => 5,
            'verify_ssl' => false, // для локальной разработки
            'base_url' => 'http://localhost:8080',
        ],
    ],

    'test' => [
        'debug' => true,
        'log_level' => 'info',
        'external_api' => [
            'timeout' => 10,
            'verify_ssl' => true,
            'base_url' => 'https://test-api.example.com',
        ],
    ],

    'prod' => [
        'debug' => false,
        'log_level' => 'warning',
        'external_api' => [
            'timeout' => 30,
            'verify_ssl' => true,
            'base_url' => 'https://api.example.com',
        ],
    ],
];
