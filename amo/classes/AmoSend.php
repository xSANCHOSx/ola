<?

namespace Itactis\AmoHelper;

class AmoSend {
    protected $accessToken;
    protected $domain;
    protected $pipelineId;

    protected $allowedOrderFields = [
        'Номер заказа',
        'Состав заказа',
        // 'С этим товаром покупают',
        'Бренд/Сайт',
        // 'Способ доставки',
        // 'Способ оплаты',
        // 'Город',
        'Адрес доставки',
        // 'Комментарий',
        // 'Желаемый способ коммуникации',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content'
    ];
    protected $allowedUserFields = [
        'Телефон',
        'Email'
    ];

    public function __construct($accessToken, $domain, $pipelineId) {
        $this->accessToken = $accessToken;
        $this->domain = $domain;
        $this->pipelineId = $pipelineId;
    }

    public function sendOrder($orderInfo)
    {
        $orderFields = $this->getOrderFields();

        $preparedOrderData = [
            // 'name' => 'Заявка с сайта ' . $orderInfo['orderInfo']['Бренд/Сайт'],
            'name' => $orderInfo['orderInfo']['Номер заказа'] . ' с сайта ' . $orderInfo['orderInfo']['Бренд/Сайт'],
            'price' => $orderInfo['orderInfo']['price'],
        ];
        foreach ($orderInfo['orderInfo'] as $orderKey => $orderValue) {
            if (!empty($orderValue) && isset($orderFields[$orderKey])) {
                $preparedOrderData['custom_fields_values'][] = [
                    'field_id' => $orderFields[$orderKey],
                    'values' => [
                        [
                            'value' => $orderValue
                        ]
                    ]
                ];
            }
        }

        $personFields = $this->getPersonFields();
        $preparedUserData = [
            'name' => $orderInfo['personInfo']['name']
        ];
        foreach ($orderInfo['personInfo'] as $personKey => $personValue) {
            if (!empty($personValue) && isset($personFields[$personKey])) {
                $preparedUserData['custom_fields_values'][] = [
                    'field_id' => $personFields[$personKey],
                    'values' => [
                        [
                            'value' => $personValue
                        ]
                    ]
                ];
            }
        }

        $phone = $orderInfo['personInfo']['Телефон'];
        $userId = $this->getUserByData($phone);
        if ($userId > 0) {
            $preparedUserData = [
                'id' => $userId
            ];
        }

        $allOrderData = [
            [
                'source_name' => $orderInfo['orderInfo']['Бренд/Сайт'],
                'source_uid' => uniqid('', true),
                'created_at' => time(),
                'pipeline_id' => (int)$this->pipelineId,
                '_embedded' => [
                    'leads' => [
                        $preparedOrderData
                    ],
                    'contacts' => [
                        $preparedUserData
                    ],
                ],
                'metadata' => [
                    // 'ip' => $_SERVER['REMOTE_ADDR'],
                    'form_id' => $_SERVER['SERVER_NAME'],
                    'form_sent_at' => time(),
                    // 'form_name' => 'Заявка с сайта ' . $orderInfo['orderInfo']['Бренд/Сайт'],
                    'form_name' => $orderInfo['orderInfo']['Номер заказа'] . ' с сайта ' . $orderInfo['orderInfo']['Бренд/Сайт'],
                    'form_page' => $_SERVER['SERVER_NAME'],
                    'referer' => $_SERVER['SERVER_NAME']
                ]
            ]
        ];
        // dump($allOrderData);
        // dump($this->accessToken);
        // dump($this->domain);
        // die('123');

        $curl = curl_init();
            curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
            curl_setopt($curl,CURLOPT_HTTPHEADER,['Accept: application/json', 'Authorization: Bearer ' . $this->accessToken]);
            curl_setopt($curl,CURLOPT_URL,'https://' . $this->domain . '.amocrm.ru/api/v4/leads/unsorted/forms');
            curl_setopt($curl,CURLOPT_HEADER,false);
            curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($allOrderData));
            curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,1);
            curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,2);
            $out = curl_exec($curl);
        curl_close($curl);

        $arAmoResult = json_decode($out, true);
        p2log($orderInfo, 'amo_orders');
        p2log($arAmoResult, 'amo_orders');
    }

    protected function getOrderFields()
    {
        $link = 'https://' . $this->domain . '.amocrm.ru/api/v4/leads/custom_fields';
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $this->accessToken));
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($out, true);

        $fields = [];
        foreach ($result['_embedded']['custom_fields'] as $field) {
            if (in_array(trim($field['name']), $this->allowedOrderFields)) {
                $fields[trim($field['name'])] = $field['id'];
            }
        }

        return $fields;
    }

    protected function getPersonFields()
    {
        $link = 'https://' . $this->domain . '.amocrm.ru/api/v4/contacts/custom_fields';
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $this->accessToken));
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($out, true);

        $fields = [];
        foreach ($result['_embedded']['custom_fields'] as $field) {
            if (in_array(trim($field['name']), $this->allowedUserFields)) {
                $fields[trim($field['name'])] = $field['id'];
            }
        }

        return $fields;
    }

    protected function getUserByData($phone)
    {
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Accept: application/json', 'Authorization: Bearer ' . $this->accessToken]);
        curl_setopt($curl, CURLOPT_POST, false);
        curl_setopt($curl,CURLOPT_URL,'https://' . $this->domain . '.amocrm.ru/api/v4/contacts?query=' . $phone);
        curl_setopt($curl,CURLOPT_HEADER,false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,2);
        $out = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($out, true);
        return $res['_embedded']['contacts'][0]['id'] ? $res['_embedded']['contacts'][0]['id'] : 0;
    }

}