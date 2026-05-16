<?php

namespace Itactis\AmoHelper;

use \Itactis\AmoHelper\AmoTable,
    \Itactis\AmoHelper\AmoAuth,
    \Itactis\AmoHelper\AmoSend;

/**
 * Главный класс интеграции с AmoCRM.
 *
 * Изменения относительно оригинала:
 *  - sendExeption() теперь бросает реальный \RuntimeException вместо тихого логирования,
 *    чтобы вызывающий код (amo_send_order()) мог перехватить ошибку через try/catch
 *    и не останавливать основной поток обработки заказа.
 */
class Amo
{
    protected $startFieldCodes = [
        'redirectUri'       => 'redirectUri',
        'integrationId'     => 'integrationId',
        'clientSecret'      => 'clientSecret',
        'authorizationCode' => 'authorizationCode',
        'subdomain'         => 'subdomain',
        'pipelineId'        => 'pipelineId',
    ];
    protected $startFieldValues = [];
    protected $accessTokenCode  = 'accesToken';
    protected $refreshTokenCode = 'refreshToken';
    protected $settedFields     = [];

    public function __construct()
    {
        $this->startFieldValues = $this->getFieldsValue();

        foreach ($this->startFieldCodes as $code => $label) {
            if (empty($this->startFieldValues[$code])) {
                $this->sendExeption('Не указан обязательный параметр AMO: ' . $code);
            }
        }

        $this->settedFields = $this->setAuthFields();
    }

    public function sendOrder($orderInfo): void
    {
        $amoSend = new AmoSend(
            $this->settedFields[$this->accessTokenCode],
            $this->startFieldValues['subdomain'],
            $this->startFieldValues['pipelineId']
        );
        $amoSend->sendOrder($orderInfo);
    }

    protected function getFieldsValue(): array
    {
        return AmoTable::getFieldsValue();
    }

    protected function setFieldValue($code, $value)
    {
        return AmoTable::setFieldValue($code, $value);
    }

    protected function setAuthFields(): array
    {
        $accessToken  = $this->startFieldValues['accesToken']  ?? '';
        $refreshToken = $this->startFieldValues['refreshToken'] ?? '';

        $amoAuth = new AmoAuth(
            $this->startFieldValues,
            $this->accessTokenCode,
            $this->refreshTokenCode
        );

        if (!$accessToken) {
            $tokens = $amoAuth->getTokens();
            if (array_key_exists('error', $tokens)) {
                $this->sendExeption('Ошибка получения AMO токенов: ' . $tokens['error']);
            }
            $accessToken  = $tokens[$this->accessTokenCode];
            $refreshToken = $tokens[$this->refreshTokenCode] ?? '';
        }

        if ($accessToken) {
            $checkTokens  = $amoAuth->checkTokens($accessToken, $refreshToken);
            $accessToken  = $checkTokens[$this->accessTokenCode];
            $refreshToken = $checkTokens[$this->refreshTokenCode] ?? $refreshToken;
        }

        if (!$accessToken) {
            $this->sendExeption('Не удалось получить access token AMO');
        }

        return [
            $this->accessTokenCode  => $accessToken,
            $this->refreshTokenCode => $refreshToken,
        ];
    }

    protected function setAccessToken($token): void
    {
        $this->setFieldValue($this->accessTokenCode, $token);
    }

    protected function setRefreshToken($token): void
    {
        $this->setFieldValue($this->refreshTokenCode, $token);
    }

    /**
     * Логирует ошибку и бросает исключение.
     * Исключение перехватывается в amo_send_order() и НЕ доходит до пользователя.
     */
    protected function sendExeption(string $message): void
    {
        if (function_exists('p2log')) {
            p2log($message, 'amo_orders');
        }
        if (function_exists('dev_log_runtime')) {
            dev_log_runtime('AMO error: ' . $message);
        }
        throw new \RuntimeException($message);
    }
}
