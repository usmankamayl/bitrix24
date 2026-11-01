<?php
// /home/bitrix/ext_www/shop.org/local/php_interface/events.php

use Bitrix\Main\EventManager;
use Training\Integration\ServiceRequestClient;
use Training\Config\Logger;
// Подключаем классы
require_once __DIR__ . '/../classes/Training/Integration/TransportException.php';
require_once __DIR__ . '/../classes/Training/Integration/HttpTransport.php';
require_once __DIR__ . '/../classes/Training/Integration/HttpClient.php';
require_once __DIR__ . '/../classes/Training/Integration/ServiceRequestClient.php';

// Регистрируем событие
AddEventHandler("crm", "OnAfterCrmDealUpdate", "onAfterCrmDealUpdateHandler");

function onAfterCrmDealUpdateHandler(&$arFields)
{
    if (empty($arFields['ID'])) {
        return;
    }

    $dealId = (int)$arFields['ID'];
    $stage = $arFields['STAGE_ID'] ?? '';

    // Проверяем стадию "Сделка успешна" (C2:WON)
    if ($stage !== 'C2:WON') {
        return;
    }

    // Защита от повторной отправки
    global $USER_FIELD_MANAGER;
    $orderIdField = 'UF_CRM_1792228829';
    $currentValue = $USER_FIELD_MANAGER->GetUserFields('CRM_DEAL', $dealId)[$orderIdField]['VALUE'] ?? null;

    if (!empty($currentValue)) {
        return;
    }

    // Получаем данные сделки
    $dbDeal = CCrmDeal::GetListEx([], ['ID' => $dealId], false, false, ['ID', 'TITLE', 'COMMENTS']);
    $deal = $dbDeal->Fetch();

    if (!$deal) {
        return;
    }

    $requestData = [
        'title' => !empty($deal['TITLE']) ? $deal['TITLE'] : 'Сделка #' . $dealId,
        'description' => !empty($deal['COMMENTS']) ? $deal['COMMENTS'] : 'Без описания',
        'customerEmail' => 'test@example.com',
    ];

    // В будущем URL и ключ лучше вынести в kernel.php (тема S02)
    $apiUrl = 'https://smudgy-caloric-arrogance.ngrok-free.dev/training1c/hs/api';

    try {
        $client = new ServiceRequestClient(apiUrl: $apiUrl, apiKey: 'test-key', sourceScope: 'dev', allowHttp: false);
        $result = $client->sendRequest($dealId, $requestData);

        if ($result['success'] && $result['outcome'] === 'accepted') {
            $orderId = $result['data']['orderId'] ?? null;
            if ($orderId) {
                $USER_FIELD_MANAGER->Update('CRM_DEAL', $dealId, [$orderIdField => $orderId]);
            }
        } else {
            // В S02 заменим на нормальный логгер Битрикса
            error_log("1C Integration Error for deal {$dealId}: outcome={$result['outcome']}, error={$result['error']}");
        }
    } catch (\Throwable $e) {
        error_log("1C Integration Exception for deal {$dealId}: " . $e->getMessage());
    }
}

// ============================================================
// S02.2.1: OnSaleOrderSaved — дым-тест логгера (без API)
// ============================================================

EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleOrderSaved',
    'onSaleOrderSavedHandler'
);

function onSaleOrderSavedHandler(\Bitrix\Main\Event $event): void
{
    /** @var \Bitrix\Sale\Order|null $order */
    $order = $event->getParameter('ENTITY');

    if (!$order instanceof \Bitrix\Sale\Order) {
        return;
    }

    Logger::info('sale.order.saved', [
        'order_id'       => $order->getId(),
        'account_number' => $order->getField('ACCOUNT_NUMBER'),
        'price'          => $order->getPrice(),
        'status_id'      => $order->getField('STATUS_ID'),
        'is_new'         => (bool)$event->getParameter('IS_NEW'),
    ]);
}