<?php

/**
 * AMO CRM order integration.
 * Повністю ізольований від основного потоку — будь-яка помилка тут
 * логується і НЕ впливає на відправку пошти та збереження в БД.
 *
 * @return bool  true — успішно відправлено в AMO, false — помилка
 */
function amo_send_order(array $post): bool
{
    if (empty($post)) {
        return false;
    }

    try {
        $basketInfo = [];
        if (!empty($post['order_result'])) {
            $decoded = json_decode($post['order_result'], true);
            $basketInfo = is_array($decoded) ? $decoded : [];
        }

        $getParams = [];
        if (!empty($post['page_params'])) {
            $decoded = json_decode($post['page_params'], true);
            $getParams = is_array($decoded) ? $decoded : [];
        }

        $amoDir = __DIR__ . '/classes/';
        require_once $amoDir . 'Amo.php';
        require_once $amoDir . 'AmoTable.php';
        require_once $amoDir . 'AmoAuth.php';
        require_once $amoDir . 'AmoSend.php';

        $comment  = strip_tags($post['comments'] ?? '');
        $userName = strip_tags($post['name']    ?? '');
        $userPhone = strip_tags($post['phone']  ?? '');
        $userEmail = strip_tags($post['email']  ?? '');

        $siteName        = 'olaplex-shop.ru';
        $orderBasketString = '';
        $resultSumm      = 0;

        foreach ($basketInfo as $basketItem) {
            $quantity = (int)($basketItem['num']   ?? 0);
            $price    = (float)($basketItem['price'] ?? 0);
            if ($quantity) {
                $resultSumm += ($price * $quantity);
            }
            $orderBasketString .= ($basketItem['name'] ?? '') . ', '
                . ($basketItem['id'] ?? '') . ', '
                . $price . ' руб. - ' . $quantity . " шт\n";
        }

        $utmSource   = $_COOKIE['utm_source']   ?? '';
        $utmMedium   = $_COOKIE['utm_medium']   ?? '';
        $utmCampaign = $_COOKIE['utm_campaign'] ?? '';
        $utmContent  = $_COOKIE['utm_content']  ?? '';

        $counter = $post['ORDER_ID'] ?? 0;

        $amo = new \Itactis\AmoHelper\Amo();

        $toSendInfo = [
            'orderInfo' => [
                'price'           => intval($resultSumm),
                'Номер заказа'    => 'OLA-' . $counter,
                'Состав заказа'   => $orderBasketString,
                'Бренд/Сайт'      => $siteName,
                'Адрес доставки'  => $comment,
                'utm_source'      => $utmSource,
                'utm_medium'      => $utmMedium,
                'utm_campaign'    => $utmCampaign,
                'utm_content'     => $utmContent,
            ],
            'personInfo' => [
                'name'     => $userName,
                'Телефон'  => $userPhone,
                'Email'    => $userEmail,
            ],
        ];

        $amo->sendOrder($toSendInfo);
        return true;
    } catch (\Throwable $e) {
        // Логуємо, але НЕ пробрасуємо — замовлення вже збережено і відправлено на пошту
        if (function_exists('dev_log_runtime')) {
            dev_log_runtime('AMO send failed: ' . $e->getMessage());
        }
        if (function_exists('p2log')) {
            p2log('AMO send failed: ' . $e->getMessage(), 'amo_orders');
        }
        return false;
    }
}
