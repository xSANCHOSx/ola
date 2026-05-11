<?php

declare(strict_types=1);

require_once __DIR__ . '/../../amo/order.php';

class NotificationService
{
    private const RECIPIENTS = 'client@macadamia-shop.ru, client@olaplex-shop.ru';
    private const DOMAIN     = 'olaplex.ru';

    public function send(
        array  $payload,
        array  $orderResult,
        float  $totalSum,
        string $subject,
        int    $orderNumber
    ): array {
        $template = EmailView::buildTemplate($payload, $subject, $orderResult, $totalSum);
        $headers  = $this->buildHeaders();

        // ── Email до магазину ─────────────────────────────────────────────────
        OlaLogger::debug('MAIL_SHOP_START', ['to' => self::RECIPIENTS]);
        $emailToShop = mail(self::RECIPIENTS, $subject, $template, $headers);
        OlaLogger::info('MAIL_SHOP_DONE', ['result' => $emailToShop]);

        // ── Email клієнту ─────────────────────────────────────────────────────
        OlaLogger::debug('MAIL_USER_START', ['to' => $payload['email']]);
        $emailToUser = mail($payload['email'], $subject, $template, $headers);
        OlaLogger::info('MAIL_USER_DONE', ['result' => $emailToUser]);

        // ── Bitrix CRM ────────────────────────────────────────────────────────
        OlaLogger::debug('CRM_START');
        try {
            $crmSent = dev_send_bitrix_lead($subject, $payload);
            OlaLogger::info('CRM_DONE', ['result' => $crmSent]);
        } catch (Throwable $e) {
            OlaLogger::error('CRM_EXCEPTION', ['msg' => $e->getMessage()]);
            $crmSent = false;
        }

        // ── p2log ─────────────────────────────────────────────────────────────
        $_POST['MAIL_OUR']  = 'Result = ' . ($emailToShop ? '1' : '0');
        $_POST['MAIL_USER'] = 'Result = ' . ($emailToUser ? '1' : '0');
        $_POST['CRM_SENT']  = $crmSent ? '1' : '0';
        p2log($_POST);

        // ── AMO CRM ───────────────────────────────────────────────────────────
        OlaLogger::debug('AMO_START');
        try {
            $amoResult = amo_send_order($_POST);
            OlaLogger::info('AMO_DONE', ['result' => $amoResult]);
        } catch (Throwable $e) {
            OlaLogger::error('AMO_EXCEPTION', ['msg' => $e->getMessage()]);
            $amoResult = false;
        }

        $_POST['AMO_SENT'] = $amoResult ? '1' : '0';

        return [
            'email' => $emailToShop && $emailToUser,
            'crm'   => (bool) $crmSent,
            'amo'   => (bool) $amoResult,
        ];
    }

    private function buildHeaders(): string
    {
        $from = 'no-reply@' . self::DOMAIN;

        return implode("\r\n", [
            "From: {$from}",
            "Reply-To: {$from}",
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ]) . "\r\n";
    }
}

if (!function_exists('p2log')) {
    function p2log(array $arr, string $key = ''): void
    {
        $key  = $key ?: 'main';
        $dump = print_r($arr, true) . "\r\n";
        $file = $_SERVER['DOCUMENT_ROOT'] . '/log/' . $key . '.log';
        @file_put_contents($file, $dump, FILE_APPEND);
    }
}
