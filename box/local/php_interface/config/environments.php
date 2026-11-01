<?php
/**
 * Настройки окружений.
 *
 * Секреты НЕ хранятся в этом файле.
 * Значения подтягиваются из переменных окружения (getenv),
 * которые в dev задаются через /local/php_interface/config/.env.local
 * (файл в .gitignore), а в prod — через php-fpm pool / systemd EnvironmentFile.
 */

// Общие настройки платежей. Пустые значения допустимы, пока интеграция выключена:
// платёжный клиент обязан проверять 'enabled' и наличие реквизитов ДО сетевого вызова.
$yookassa = [
    'enabled'         => false,
    'base_url'        => 'https://api.yookassa.ru/v3',
    'shop_id'         => getenv('YOOKASSA_SHOP_ID')    ?: '',
    'secret_key'      => getenv('YOOKASSA_SECRET_KEY') ?: '',
    'timeout'         => 30,
    'connect_timeout' => 10,
    'verify_ssl'      => true, // ЮKassa — всегда HTTPS, даже sandbox
];

return [
    'dev' => [
        'debug'      => true,
        'log_level'  => 'debug',
        'external_api' => [
            'timeout'    => 5,
            'verify_ssl' => false, // локальная разработка с ngrok
            'base_url'   => 'http://localhost:8080',
        ],
        'yookassa' => array_merge($yookassa, [
            'enabled'    => (bool)getenv('YOOKASSA_ENABLED'),
            'return_url' => getenv('YOOKASSA_RETURN_URL') ?: 'https://example.test/return',
            'timeout'    => 10,
        ]),
    ],

    'test' => [
        'debug'      => true,
        'log_level'  => 'info',
        'external_api' => [
            'timeout'    => 10,
            'verify_ssl' => true,
            'base_url'   => 'https://test-api.example.com',
        ],
        'yookassa' => array_merge($yookassa, [
            'enabled'    => (bool)getenv('YOOKASSA_ENABLED'),
            'return_url' => getenv('YOOKASSA_RETURN_URL') ?: 'https://example.test/return',
            'timeout'    => 15,
        ]),
    ],

    'prod' => [
        'debug'      => false,
        'log_level'  => 'warning',
        'external_api' => [
            'timeout'    => 30,
            'verify_ssl' => true,
            'base_url'   => 'https://api.example.com',
        ],
        'yookassa' => array_merge($yookassa, [
            'enabled'    => (bool)getenv('YOOKASSA_ENABLED'),
            'return_url' => getenv('YOOKASSA_RETURN_URL') ?: '',
            'timeout'    => 20,
        ]),
    ],
];