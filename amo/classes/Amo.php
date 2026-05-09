<?
namespace Itactis\AmoHelper;

use \Itactis\AmoHelper\AmoTable,
    \Itactis\AmoHelper\AmoAuth,
    \Itactis\AmoHelper\AmoSend;

/**
 * Класс треубет для работы таблицу highload
 * Таблица при старте работы должна хранить в себе:
 * redirectUri - страница для возврата из настроек интеграции АМО
 * integrationId - ID интеграции из настроек интеграции АМО
 * clientSecret - секретный ключ из настроек интеграции АМО
 * authorizationCode - код для авторизации из настроек интеграции АМО - живет 20 минут при перегенерации
 * subdomain - поддомен АМО
 * pipelineId - id воронки АМО
 * При запуске класса должна быть создана таблица с перечисленными выше данными, коды этих запимей хранятся в массиве класса
 * Важно обратить внимание, что при первом запуске должен быть валидный код для авторизации, который живет всего 20 минут
 * Остальные данные заполнит сам класс
 */
class Amo
{
    protected $startFieldCodes = [
        'redirectUri' => 'redirectUri',
        'integrationId' => 'integrationId',
        'clientSecret' => 'clientSecret',
        'authorizationCode' => 'authorizationCode',
        'subdomain' => 'subdomain',
        'pipelineId' => 'pipelineId'
    ];
    protected $startFieldValues = [];
    protected $accessTokenCode = 'accesToken';
    protected $refreshTokenCode = 'refreshToken';
    protected $settedFields = [];

    public function __construct() {
        $this->startFieldValues = $this->getFieldsValue();
        foreach ($this->startFieldValues as $startFieldValue) {
            if (empty($startFieldValue)) {
                $this->sendExeption('Не указаны обязательные параметры для подключения ' . $startFieldValue);
            }
        }
        $this->settedFields = $this->setAuthFields();
    }

    public function sendOrder($orderInfo)
    {
        $amoSend = new AmoSend($this->settedFields[$this->accessTokenCode], $this->startFieldValues['subdomain'], $this->startFieldValues['pipelineId']);
        $amoSend->sendOrder($orderInfo);
    }

    protected function getFieldsValue()
    {
        return AmoTable::getFieldsValue();
    }

    protected function setFieldValue($code, $value)
    {
        return AmoTable::setFieldValue($code, $value);
    }

    protected function setAuthFields()
    {
        //Проверим в базе ключи для подключения
        $accessToken = $this->startFieldValues['accesToken'];
        $refreshToken = $this->startFieldValues['refreshToken'];

        $amoAuth = new AmoAuth($this->startFieldValues, $this->accessTokenCode, $this->refreshTokenCode);

        //Ключей для авторизации в базе нет - попробуем получить
        // if (!$accessToken || !$refreshToken) {
        if (!$accessToken) {
            $tokens = $amoAuth->getTokens();
            if (array_key_exists('error', $tokens)) {
                $this->sendExeption($tokens['error']);
            }
            $accessToken = $tokens[$this->accessTokenCode];
            // $refreshToken = $tokens[$this->refreshTokenCode];
        }

        //Проверим ключи авторизации
        // if ($accessToken && $refreshToken) {
        if ($accessToken) {
            $checkTokens = $amoAuth->checkTokens($accessToken, $refreshToken);

            $accessToken = $checkTokens[$this->accessTokenCode];
            // $refreshToken = $checkTokens[$this->refreshTokenCode];
        }

        //Обновим ключи в бд
        // $this->setAccessToken($accessToken);
        // $this->setRefreshToken($refreshToken);

        // if ($accessToken && $refreshToken) {
        if ($accessToken) {
            return [
                $this->accessTokenCode => $accessToken,
                $this->refreshTokenCode => $refreshToken
            ];
        } else {
            $this->sendExeption('Не удалось получить ключи для подключения');
        }
    }

    protected function setAccessToken($token)
    {
        $this->setFieldValue($this->accessTokenCode, $token);
    }

    protected function setRefreshToken($token)
    {
        $this->setFieldValue($this->refreshTokenCode, $token);
    }

    protected function sendExeption($message)
    {
        p2log($message, 'amo_orders');
        // throw new \Exception($message);
    }

}