<?

namespace Itactis\AmoHelper;

class AmoAuth {
    protected $authValues;
    protected $accessTokenCode;
    protected $refreshTokenCode;

    public function __construct($authValues, $accessTokenCode, $refreshTokenCode) {
        $this->authValues = $authValues;
        $this->accessTokenCode = $accessTokenCode;
        $this->refreshTokenCode = $refreshTokenCode;
    }

    public function getTokens()
    {
        $link = 'https://' . $this->authValues['subdomain'] . '.amocrm.ru/oauth2/access_token';
        $data = [
            'client_id' => $this->authValues['integrationId'],
            'client_secret' => $this->authValues['clientSecret'],
            'grant_type' => 'authorization_code',
            'code' => $this->authValues['authorizationCode'],
            'redirect_uri' => $this->authValues['redirectUri'],
        ];

        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $code = (int)$code;

        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try {
            if ($code < 200 || $code > 204) {
                throw new \Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
            }
        } catch(\Exception $e) {
            return ['error' => 'Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode()];
        }

        $response = json_decode($out, true);
        return [
            $this->accessTokenCode => $response['access_token'],
            $this->refreshTokenCode => $response['refresh_token'],
        ];
    }

    public function checkTokens($accessToken, $refreshToken)
    {
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, 'https://' . $this->authValues['subdomain'] . '.amocrm.ru/api/v4/account');
        curl_setopt($curl,CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $accessToken));
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $code = (int)$code;

        //Если код 401, значит ключ истек, получим новый
        if ($code == 401) {
            $authData = [
                'client_id' => $this->authValues['integrationId'],
                'client_secret' => $this->authValues['clientSecret'],
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'redirect_uri' => $this->authValues['redirectUri'],
            ];
            $curl = curl_init();
            curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
            curl_setopt($curl,CURLOPT_URL, 'https://' . $this->authValues['subdomain'] . '.amocrm.ru/oauth2/access_token');
            curl_setopt($curl,CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($curl,CURLOPT_HEADER, false);
            curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($authData));
            curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
            $out = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            $response = json_decode($out, true);
            $result = [
                $this->accessTokenCode => $response['access_token'],
                $this->refreshTokenCode => $response['refresh_token']
            ];
        } else {
            $result = [
                $this->accessTokenCode => $accessToken,
                $this->refreshTokenCode => $refreshToken
            ];
        }

        return $result;
    }

}