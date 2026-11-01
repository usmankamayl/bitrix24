<?php

declare(strict_types=1);

namespace Training\Integration\Tests;

require_once __DIR__ . '/../classes/Training/Integration/TransportException.php';
require_once __DIR__ . '/../classes/Training/Integration/HttpTransport.php';
require_once __DIR__ . '/../classes/Training/Integration/HttpClient.php';
require_once __DIR__ . '/../classes/Training/Integration/ServiceRequestClient.php';
require_once __DIR__ . '/FakeHttpTransport.php';

use Training\Integration\ServiceRequestClient;
use Training\Integration\TransportException;

$passed = 0;
$failed = 0;

function check(string $name, bool $cond, string $details = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[OK]   {$name}\n";
    } else {
        $failed++;
        echo "[FAIL] {$name}" . ($details !== '' ? " — {$details}" : '') . "\n";
    }
}

function expectException(string $name, callable $fn, string $class): void
{
    try {
        $fn();
        check($name, false, "ожидалось {$class}");
    } catch (\Throwable $e) {
        check($name, $e instanceof $class, get_class($e));
    }
}

$validData = [
    'title' => 'Заявка на диагностику',
    'description' => 'Описание проблемы',
    'customerEmail' => 'customer@example.com',
];

function makeClient(FakeHttpTransport $transport): ServiceRequestClient
{
    return new ServiceRequestClient(
        'https://api.example.com',
        'test-key',
        'test',
        httpClient: $transport
    );
}

// 1. Валидная заявка
$t = new FakeHttpTransport();
$t->pushResponse(200, '{"orderId":98765}');
$r = makeClient($t)->sendRequest(123, $validData);
check('valid: success', $r['success'] === true && $r['outcome'] === 'accepted');
check(
    'valid: externalId с sourceScope',
    $r['externalId'] === 'b24_test_service_request_123'
);
check('valid: Idempotency-Key передан', isset($t->calls[0][3]['Idempotency-Key']));
check(
    'valid: Idempotency-Key = externalId',
    $t->calls[0][3]['Idempotency-Key'] === $r['externalId']
);

// 2. Валидация: нет title
expectException(
    'validation: нет title',
    fn() => makeClient(new FakeHttpTransport())->sendRequest(1, ['description' => 'x', 'customerEmail' => 'a@b.co']),
    \InvalidArgumentException::class
);

// 3. Валидация: плохой email
expectException(
    'validation: плохой email',
    fn() => makeClient(new FakeHttpTransport())->sendRequest(1, ['title' => 't', 'description' => 'd', 'customerEmail' => 'bad']),
    \InvalidArgumentException::class
);

// 4. Валидация: id=0
expectException(
    'validation: id=0',
    fn() => makeClient(new FakeHttpTransport())->sendRequest(0, $validData),
    \InvalidArgumentException::class
);

// 5. Валидация: пустой apiKey
expectException(
    'validation: пустой apiKey',
    fn() => new ServiceRequestClient('https://api.example.com', '   ', 'test'),
    \InvalidArgumentException::class
);

// 6. HTTP 500 -> unknown + reconcile
$t = new FakeHttpTransport();
$t->pushResponse(500, 'server error');
$r = makeClient($t)->sendRequest(1, $validData);
check('500: outcome=unknown', $r['outcome'] === 'unknown');
check('500: next_step=reconcile', $r['next_step'] === 'reconcile');

// 7. HTTP 400 -> http_error
$t = new FakeHttpTransport();
$t->pushResponse(400, '{"error":"bad"}');
$r = makeClient($t)->sendRequest(1, $validData);
check('400: outcome=http_error', $r['outcome'] === 'http_error');

// 8. Таймаут -> unknown
$t = new FakeHttpTransport();
$t->pushException(new TransportException(
    'timeout',
    28,
    ['phase' => 'unknown']
));
$r = makeClient($t)->sendRequest(1, $validData);
check('timeout: outcome=unknown', $r['outcome'] === 'unknown');
check('timeout: next_step=reconcile', $r['next_step'] === 'reconcile');

// 9. Ошибка сериализации -> not_sent
$t = new FakeHttpTransport();
$t->pushException(new TransportException(
    'json error',
    0,
    ['phase' => 'not_sent']
));
$r = makeClient($t)->sendRequest(1, $validData);
check('not_sent: outcome=not_sent', $r['outcome'] === 'not_sent');
check('not_sent: next_step=fix_cause', $r['next_step'] === 'fix_cause');

// 10. Invalid JSON в 2xx -> invalid_response + reconcile
$t = new FakeHttpTransport();
$t->pushResponse(200, '<html>ok</html>', false);
$r = makeClient($t)->sendRequest(1, $validData);
check('invalid json: outcome=invalid_response', $r['outcome'] === 'invalid_response');
check('invalid json: next_step=reconcile', $r['next_step'] === 'reconcile');

// 11. 2xx + JSON-объект (не массив) -> invalid_response
$t = new FakeHttpTransport();
$t->pushResponse(200, '{"orderId":1}');
// json_decode ассоциативно вернёт array — это ожидаемое поведение
$r = makeClient($t)->sendRequest(1, $validData);
check('2xx json object: accepted', $r['outcome'] === 'accepted');

// 12. checkStatus: 404 -> http_error, POST не выполняется
$t = new FakeHttpTransport();
$t->pushResponse(404, '{"error":"not found"}');
$r = makeClient($t)->checkStatus(1);
check('status 404: outcome=http_error', $r['outcome'] === 'http_error');
check('status 404: POST не вызван', count($t->calls) === 1 && $t->calls[0][0] === 'GET');

// 13. reconcile: status 404 -> unknown + manual_check_required
$t = new FakeHttpTransport();
$t->pushResponse(404, '{"error":"not found"}');
$r = makeClient($t)->reconcile(1);
check('reconcile 404: outcome=unknown', $r['outcome'] === 'unknown');
check('reconcile 404: next_step=manual_check_required', $r['next_step'] === 'manual_check_required');
check('reconcile 404: POST не вызван', count($t->calls) === 1 && $t->calls[0][0] === 'GET');

// 14. reconcile: status 200 -> reconciled
$t = new FakeHttpTransport();
$t->pushResponse(200, '{"status":"created","orderId":1}');
$r = makeClient($t)->reconcile(1);
check('reconcile 200: outcome=reconciled', $r['outcome'] === 'reconciled');
check('reconcile 200: success=true', $r['success'] === true);

// 15. Единый формат результата
$t = new FakeHttpTransport();
$t->pushResponse(200, '{"orderId":1}');
$r = makeClient($t)->sendRequest(1, $validData);
$keys = ['success', 'outcome', 'externalId', 'httpCode', 'duration', 'data', 'error', 'next_step'];
$hasAll = true;
foreach ($keys as $k) {
    if (!array_key_exists($k, $r)) {
        $hasAll = false;
        break;
    }
}
check('result: единый формат', $hasAll);

echo "\nРезультат: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);