<?php

declare(strict_types=1);

require_once __DIR__ . '/../../amo/order.php';

class NotificationService
{
    private const DOMAIN = 'olaplex.ru';

    public function send(
        array  $payload,
        array  $orderResult,
        float  $totalSum,
        string $subject,
        int    $orderNumber,
        float  $baseTotal      = 0.0,
        float  $discountAmount = 0.0
    ): array {
        $shopTemplate   = EmailView::buildTemplate(
            $payload, $subject, $orderResult, $totalSum, $baseTotal, $discountAmount
        );
        $clientTemplate = EmailView::buildClientTemplate(
            $payload, $subject, $orderResult, $totalSum, $baseTotal, $discountAmount
        );
        $headers  = $this->buildHeaders();
        $recipients = $this->getRecipients();

        // ── Email до магазину ─────────────────────────────────────────────────
        OlaLogger::debug('MAIL_SHOP_START', ['to' => $recipients]);
        $emailToShop = mail($recipients, $subject, $shopTemplate, $headers);
        OlaLogger::info('MAIL_SHOP_DONE', ['result' => $emailToShop]);

        // ── Email клиенту ─────────────────────────────────────────────────────
        OlaLogger::debug('MAIL_USER_START', ['to' => $payload['email']]);
        $emailToUser = mail($payload['email'], $subject, $clientTemplate, $headers);
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

        // ── p2log — тільки технічні дані, без PII ────────────────────────────
        p2log([
            'ORDER_ID'  => $orderNumber,
            'MAIL_OUR'  => 'Result = ' . ($emailToShop ? '1' : '0'),
            'MAIL_USER' => 'Result = ' . ($emailToUser ? '1' : '0'),
            'CRM_SENT'  => $crmSent ? '1' : '0',
        ]);

        // ── AMO CRM — явний масив з $payload (нормалізовані дані) ─────────────
        // Використовуємо $payload (нормалізований у sendmail.php) замість $_POST,
        // щоб телефон і email були у чистому вигляді, а $_POST не мутував.
        $amoPayload = [
            'name'         => $payload['name'],
            'email'        => $payload['email'],
            'phone'        => $payload['phone'],   // вже нормалізований (sendmail.php)
            'comments'     => $payload['comments'],
            'coupon'       => $payload['coupon'],
            'order_result' => json_encode($orderResult, JSON_UNESCAPED_UNICODE),
            'page_params'  => $_POST['page_params'] ?? '',
            'ORDER_ID'     => $orderNumber,
        ];

        OlaLogger::debug('AMO_START');
        try {
            $amoResult = amo_send_order($amoPayload);
            OlaLogger::info('AMO_DONE', ['result' => $amoResult]);
        } catch (Throwable $e) {
            OlaLogger::error('AMO_EXCEPTION', ['msg' => $e->getMessage()]);
            $amoResult = false;
        }

        p2log(['AMO_SENT' => $amoResult ? '1' : '0']);

        return [
            'email' => (bool) $emailToShop,
            'crm'   => (bool) $crmSent,
            'amo'   => (bool) $amoResult,
        ];
    }

    private function getRecipients(): string
    {
        $cfg = dev_app_config();
        return $cfg['order_notification_emails'] ?? '';
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
