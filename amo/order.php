<?php

if (!function_exists('p2log')) {
    function p2log($arr, $key = '')
    {
        $key = $key ?: 'main';

        $start = new DateTime();
        $start->modify('monday this week');
        $end = new DateTime();
        $end->modify('sunday this week');

        $weekRange = $start->format('d.m') . '-' . $end->format('d.m.y');
        $filepath  = $_SERVER['DOCUMENT_ROOT'] . '/log/' . $key . '_' . $weekRange . '.log';
        $dump      = '[' . date('Y-m-d H:i:s') . "]\n" . print_r($arr, true) . "\n\n";

        file_put_contents($filepath, $dump, FILE_APPEND);
    }
}

if (!empty($_POST)) {
    $basketInfo = !empty($_POST['order_result']) ? json_decode($_POST['order_result'], true) : [];

    include_once 'classes/Amo.php';
    include_once 'classes/AmoTable.php';
    include_once 'classes/AmoAuth.php';
    include_once 'classes/AmoSend.php';

    $comment   = strip_tags($_POST['comments'] ?? '');
    $userName  = strip_tags($_POST['name']     ?? '');
    $userPhone = strip_tags($_POST['phone']    ?? '');
    $userEmail = strip_tags($_POST['email']    ?? '');

    $siteName         = 'olaplex-shop.ru';
    $orderBasketString = '';
    $resultSumm        = 0;

    foreach ($basketInfo as $basketItem) {
        $quantity = (int)$basketItem['num'];
        $price    = (float)$basketItem['price'];
        if ($quantity) {
            $resultSumm += $price * $quantity;
        }
        $orderBasketString .= $basketItem['name'] . ', ' . $basketItem['id'] . ', ' . $price . ' руб. - ' . $quantity . " шт\n";
    }

    $utmSource   = $_COOKIE['utm_source']   ?? '';
    $utmMedium   = $_COOKIE['utm_medium']   ?? '';
    $utmCampaign = $_COOKIE['utm_campaign'] ?? '';
    $utmContent  = $_COOKIE['utm_content']  ?? '';

    $amo = new \Itactis\AmoHelper\Amo();
    $amo->sendOrder([
        'orderInfo' => [
            'price'          => (int)$resultSumm,
            'Номер заказа'   => 'OLA-' . ($counter ?? ''),
            'Состав заказа'  => $orderBasketString,
            'Бренд/Сайт'     => $siteName,
            'Адрес доставки' => $comment,
            'utm_source'     => $utmSource,
            'utm_medium'     => $utmMedium,
            'utm_campaign'   => $utmCampaign,
            'utm_content'    => $utmContent,
        ],
        'personInfo' => [
            'name'    => $userName,
            'Телефон' => $userPhone,
            'Email'   => $userEmail,
        ],
    ]);
}
