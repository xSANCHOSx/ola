<?
if (!empty($_POST)) {
    $basketInfo = (isset($_POST['order_result']) && !empty($_POST['order_result']) ? json_decode($_POST['order_result'], true) : []);
    $getParams = (isset($_POST['page_params']) && !empty($_POST['page_params']) ? json_decode($_POST['page_params'], true) : []);

    include_once 'classes/Amo.php';
    include_once 'classes/AmoTable.php';
    include_once 'classes/AmoAuth.php';
    include_once 'classes/AmoSend.php';

    /*
    $arAmoUTM = [
        'utm_source' => '',
        'utm_medium' => '',
        'utm_campaign' => '',
        'utm_content' => ''
    ];
    if (!empty($getParams)) {
        foreach ($getParams as $param) {
            if (isset($arAmoUTM[$param['name']])) {
                $arAmoUTM[$param['name']] = $param['value'];
            }
        }
    }
    */

    $comment = strip_tags($_POST['comments']);
    $userName = strip_tags($_POST['name']);
    $userPhone = strip_tags($_POST['phone']);
    $userEmail = strip_tags($_POST['email']);
    // $communication = strip_tags($_POST['communication']);

    $siteName = 'olaplex-shop.ru';
    $orderBasketString = '';
    $resultSumm = 0;
    foreach ($basketInfo as $basketItem) {
        $quantity = (int)$basketItem['num'];
        $price = (float)$basketItem['price'];
        if ($quantity) {
            $resultSumm += ($price * $quantity);
        }
        $orderBasketString .= $basketItem['name'] . ', ' . $basketItem['id'] . ', ' . $price . ' руб. - ' . $quantity . ' шт' . "\n";
    }

    $utmSource = ($_COOKIE['utm_source'] ?? '');
    $utmMedium = ($_COOKIE['utm_medium'] ?? '');
    $utmCampaign = ($_COOKIE['utm_campaign'] ?? '');
    $utmContent = ($_COOKIE['utm_content'] ?? '');

    $amo = new \Itactis\AmoHelper\Amo();
    $toSendInfo = [
        'orderInfo' => [
            'price' => intval($resultSumm),
            'Номер заказа' => 'OLA-' . ($_POST['ORDER_ID'] ?? ''),
            'Состав заказа' => $orderBasketString,
            // 'С этим товаром покупают' => $recommendResult,
            'Бренд/Сайт' => $siteName,
            // 'Способ доставки' => $delivery,
            // 'Способ оплаты' => $paySystem,
            // 'Город' => $propertyCollection->getDeliveryLocation()->getViewHtml(),
            'Адрес доставки' => $comment,
            // 'Комментарий' => $comment,
            // 'Желаемый способ коммуникации' => $communication,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent
        ],
        'personInfo' => [
            'name' => $userName,
            'Телефон' => $userPhone,
            'Email' => $userEmail
        ]
    ];
    // $toSendInfo['orderInfo'] = array_merge($toSendInfo['orderInfo'], $arAmoUTM);

    $amo->sendOrder($toSendInfo);
}
