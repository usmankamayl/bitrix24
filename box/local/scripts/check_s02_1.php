<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Training\Config\Environment;   // ← вот эта
use Training\Config\Logger;        // ← и вот эта

echo "env: " . Environment::getCurrent() . PHP_EOL;
echo "shop_id: " . Environment::get('yookassa.shop_id') . PHP_EOL;
echo "secret len: " . strlen((string)Environment::get('yookassa.secret_key')) . PHP_EOL;

Logger::info('s02.1 config check', [
    'env'        => Environment::getCurrent(),
    'shop_id'    => Environment::get('yookassa.shop_id'),
    'secret_key' => Environment::get('yookassa.secret_key'),
    'headers'    => ['Authorization' => 'Basic dGVzdDp0ZXN0'],
    'auth'       => ['Authorization' => 'Basic dGVzdDp0ZXN0'],
]);
echo "OK" . PHP_EOL;