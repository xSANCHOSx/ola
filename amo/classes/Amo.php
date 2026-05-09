<?php

namespace Itactis\AmoHelper;

use \Itactis\AmoHelper\AmoTable,
    \Itactis\AmoHelper\AmoAuth,
    \Itactis\AmoHelper\AmoSend;

class Amo
{
    protected $startFieldCodes = [
        'redirectUri'      => 'redirectUri',
        'integrationId'    => 'integrationId',
        'clientSecret'     => 'clientSecret',
        'authorizationCode' => 'authorizationCode',
        'subdomain'        => 'subdomain',
        'pipelineId'       => 'pipelineId',
    ];
    protected $startFieldValues = [];
    protected $accessTokenCode  = 'accesToken';
    protected $refreshTokenCode = 'refreshToken';
    protected $settedFields     = [];

    public function __construct()
    {
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
        $amoSend = new AmoSend(
            $this->settedFields[$this->accessTokenCode],
            $this->startFieldValues['subdomain'],
            $this->startFieldValues['pipelineId']
        );
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
        $accessToken  = $this->startFieldValues['accesToken'];
        $refreshToken = $this->startFieldValues['refreshToken'];

        $amoAuth = new AmoAuth($this->startFieldValues, $this->accessTokenCode, $this->refreshTokenCode);

        if (!$accessToken) {
            $tokens = $amoAuth->getTokens();
            if (array_key_exists('error', $tokens)) {
                $this->sendExeption($tokens['error']);
            }
            $accessToken = $tokens[$this->accessTokenCode];
        }

        if ($accessToken) {
            $checkTokens = $amoAuth->checkTokens($accessToken, $refreshToken);
            $accessToken = $checkTokens[$this->accessTokenCode];
        }

        if ($accessToken) {
            return [
                $this->accessTokenCode  => $accessToken,
                $this->refreshTokenCode => $refreshToken,
            ];
        }

        $this->sendExeption('Не удалось получить ключи для подключения');
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
    }
}
