<?php

declare(strict_types=1);

require_once __DIR__ . '/../../amo/order.php';

class NotificationService
{

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
            $payload,
            $subject,
            $orderResult,
            $totalSum,
            $baseTotal,
            $discountAmount
        );
        $clientTemplate = EmailView::buildClientTemplate(
            $payload,
            $subject,
            $orderResult,
            $totalSum,
            $baseTotal,
            $discountAmount
        );
        $headers  = $this->buildHeaders();
        $recipients = $this->getRecipients();

        // ── Email до магазину ─────────────────────────────────────────────────
        // Обгортаємо в try/catch про всяк випадок (напр. якщо колись mail()
        // заміниться на SMTP-бібліотеку, яка кидає винятки на невалідний адресат).
        // Помилка листа НІКОЛИ не повинна ронити оформлення замовлення.
        OlaLogger::debug('MAIL_SHOP_START', ['to' => $recipients]);
        try {
            $emailToShop = mail($recipients, $subject, $shopTemplate, $headers);
        } catch (Throwable $e) {
            OlaLogger::error('MAIL_SHOP_EXCEPTION', ['msg' => $e->getMessage()]);
            $emailToShop = false;
        }
        OlaLogger::info('MAIL_SHOP_DONE', ['result' => $emailToShop]);

        // ── Email клиенту ─────────────────────────────────────────────────────
        // Некоректна/неіснуюча адреса клієнта НЕ повинна ламати оформлення
        // замовлення — це лише best-effort сповіщення.
        OlaLogger::debug('MAIL_USER_START', ['to' => $payload['email']]);
        try {
            $emailToUser = mail($payload['email'], $subject, $clientTemplate, $headers);
        } catch (Throwable $e) {
            OlaLogger::error('MAIL_USER_EXCEPTION', ['msg' => $e->getMessage(), 'email' => $payload['email']]);
            $emailToUser = false;
        }
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

        if (!$emailToUser) {
            // Раніше цей факт ніде не фіксувався — статус магазинного листа
            // помилково повертався під ключем 'email' і перекривав інформацію
            // про клієнтський лист. Логуємо явно, щоб можна було діагностувати
            // недоставку (SPF/DKIM домену, неіснуюча скринька тощо).
            OlaLogger::warn('MAIL_USER_NOT_DELIVERED', [
                'order_number' => $orderNumber,
                'email'        => preg_replace('/(?<=.).(?=[^@]*@)/', '*', $payload['email']),
            ]);
        }

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
            // 'email' — статус листа КЛІЄНТУ (саме це пишеться в
            // orders.outbound_email_sent і показується в адмінці).
            // Раніше тут помилково повертався статус листа в магазин,
            // через що адмінка завжди показувала "лист відправлено",
            // навіть коли клієнт нічого не отримував.
            'email'       => (bool) $emailToUser,
            'email_shop'  => (bool) $emailToShop,
            'crm'         => (bool) $crmSent,
            'amo'         => (bool) $amoResult,
        ];
    }

    private function getRecipients(): string
    {
        $cfg = dev_app_config();
        return $cfg['order_notification_emails'] ?? '';
    }

    private function buildHeaders(): string
    {
        $from = 'no-reply@' . site_domain();

        return implode("\r\n", [
            "From: {$from}",
            "Reply-To: {$from}",
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ]) . "\r\n";
    }
}
