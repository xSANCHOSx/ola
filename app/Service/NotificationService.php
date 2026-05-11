<?php

declare(strict_types=1);

require_once __DIR__ . '/../../amo/order.php';

class NotificationService
{
    private const RECIPIENTS = 'client@macadamia-shop.ru, client@olaplex-shop.ru';
    private const DOMAIN     = 'olaplex.ru';

    /**
     * Відправляє email, Bitrix CRM та AMO.
     * Повертає масив результатів: ['email' => bool, 'crm' => bool, 'amo' => bool].
     */
    public function send(
        array  $payload,
        array  $orderResult,
        float  $totalSum,
        string $subject,
        int    $orderNumber
    ): array {
        $template = EmailView::buildTemplate($payload, $subject, $orderResult, $totalSum);
        $headers  = $this->buildHeaders();

        $emailToShop = mail(self::RECIPIENTS, $subject, $template, $headers);
        $emailToUser = mail($payload['email'], $subject, $template, $headers);

        $crmSent = dev_send_bitrix_lead($subject, $payload);

        // Логування для p2log
        $_POST['MAIL_OUR']  = 'Result = ' . ($emailToShop ? '1' : '0');
        $_POST['MAIL_USER'] = 'Result = ' . ($emailToUser ? '1' : '0');
        $_POST['CRM_SENT']  = $crmSent ? '1' : '0';

        p2log($_POST);

        $amoResult = amo_send_order($_POST);
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

// ── Legacy helper (потрібний для p2log) ──────────────────────────────────────

if (!function_exists('p2log')) {
    function p2log(array $arr, string $key = ''): void
    {
        $key   = $key ?: 'main';
        $dump  = print_r($arr, true) . "\r\n";
        $file  = $_SERVER['DOCUMENT_ROOT'] . '/log/' . $key . '.log';
        @file_put_contents($file, $dump, FILE_APPEND);
    }
}
