<?
if (!function_exists('p2log')) {
    function p2log($arr, $key = '')
    {
        if (empty($key)) {
            $key = 'main';
        }

        // 👉 Определяем начало (понедельник) и конец (воскресенье) недели
        $start = new DateTime();
        $start->modify('monday this week');

        $end = new DateTime();
        $end->modify('sunday this week');

        // 👉 Формируем имя файла: log/main_2024-02-12_2024-02-18.log
        $fileName = $key . '_' . $start->format('Y-m-d') . '_' . $end->format('Y-m-d') . '.log';
        $logPath = $_SERVER['DOCUMENT_ROOT'] . '/log/' . $fileName;

        // 👉 Дампим данные
        $content = (is_array($arr) || is_object($arr)) ? print_r($arr, true) : $arr;
        $dump = "[" . date('Y-m-d H:i:s') . "] " . $content . "\r\n";
        @file_put_contents($logPath, $dump, FILE_APPEND);
    }
}
if (!function_exists('dump')) {
    function dump($data)
    {
        if (!empty($data) && $data) {
            echo "<pre>";
            print_r($data);
            echo "</pre>";
        } else {
            echo "<pre>";
            print_r('empty!');
            echo "</pre>";
        }
    }
}
// $_POST = [
//     'name' => 'TEST',
//     'email' => 'dsaf@fds.ds',
//     'phone' => '+7(999)999-99-99',
//     'order_result' => '{"ID006":{"id":"ID006","name":"No.4 Bond Maintenance Shampoo 250 ml","price":"2899","num":1,"url":"https://olaplex-shop.ru/?utm_source=test1&utm_medium=test2&utm_campaign=test3&utm_content=test=4","photo":""}}',
//     // 'page_params' => '{"0":{"name":"utm_source","value":"test1"},"1":{"name":"utm_medium","value":"test2"},"2":{"name":"utm_campaign","value":"test3"},"3":{"name":"utm_content","value":"test4"},"4":{"name":"hz","value":"testsadf4"}}',
//     'page_params' => '{}',
//     'comments' => 'TEST'
// ];

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

    $counter = $_POST['ORDER_ID'] ?? 0;

    $amo = new \Itactis\AmoHelper\Amo();
    $toSendInfo = [
        'orderInfo' => [
            'price' => intval($resultSumm),
            'Номер заказа' => 'OLA-' . $counter,
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
    // dump($toSendInfo);

    $amo->sendOrder($toSendInfo);
}
