<?php

declare(strict_types=1);

class EmailView
{
    private const PRIMARY_COLOR = '#ba385c';

    public static function buildSubject(int $orderNumber): string
    {
        return 'Заказ с сайта Olaplex #OLA-' . $orderNumber . ' (' . date('d.m.Y H:i') . ')';
    }

    /**
     * Собирает полный HTML-письмо.
     */
    public static function buildTemplate(
        array  $payload,
        string $subject,
        array  $orderResult,
        float  $totalSum,
        float  $baseTotal      = 0.0,
        float  $discountAmount = 0.0
    ): string {
        $customerTable = self::buildCustomerTable($payload);
        $productTable  = self::buildProductTable($orderResult, $totalSum, $baseTotal, $discountAmount);
        $couponHtml    = '';
        if (!empty($payload['coupon']) && $discountAmount > 0.0) {
            $couponHtml = '<p style="margin-top:12px;">'
                . '<strong>Купон:</strong> ' . htmlspecialchars($payload['coupon'])
                . ' &mdash; скидка <strong>' . number_format($discountAmount, 2, '.', '') . ' руб.</strong>'
                . '</p>';
        } elseif (!empty($payload['coupon'])) {
            $couponHtml = '<p style="margin-top:12px;color:#999;">'
                . '<strong>Купон:</strong> ' . htmlspecialchars($payload['coupon'])
                . ' (не применён)</p>';
        }

        $color = self::PRIMARY_COLOR;

        return <<<HTML
        <html>
        <head>
            <style>
                * { font-family: Arial, sans-serif; }
                body { color: #333; line-height: 1.6; }
                h1 { color: {$color}; font-size: 22px; }
                h2 { color: {$color}; font-size: 20px; }
            </style>
        </head>
        <body>
            <h1>{$subject}</h1>
            {$customerTable}
            <h2 style="margin-top:20px;text-align:center;">Детали заказа</h2>
            {$productTable}
            {$couponHtml}
        </body>
        </html>
        HTML;
    }

    // ── Private builders ──────────────────────────────────────────────────────

    private static function buildCustomerTable(array $payload): string
    {
        $rows = [
            'Имя'        => $payload['name'],
            'Email'      => $payload['email'],
            'Телефон'    => $payload['phone'],
            'Мессенджер' => $payload['contact_method'],
            'Аккаунт'    => $payload['contact_username'],
            'Комментарии'=> $payload['comments'],
        ];

        $html = '<table style="border-collapse:collapse;margin-top:20px;width:100%;">';
        foreach ($rows as $label => $value) {
            $html .= self::tableRow(
                '<td style="padding:8px;border:1px solid #ddd;font-weight:bold;width:120px;">'
                    . htmlspecialchars($label) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;">'
                    . htmlspecialchars($value) . '</td>'
            );
        }
        $html .= '</table>';

        return $html;
    }

    private static function buildProductTable(
        array $orderResult,
        float $totalSum,
        float $baseTotal      = 0.0,
        float $discountAmount = 0.0
    ): string {
        $color = self::PRIMARY_COLOR;
        $thStyle = "padding:8px;border:1px solid #ddd;background:{$color};color:#fff;";

        $thead = '<thead><tr>'
            . "<th style=\"{$thStyle}\">Код</th>"
            . "<th style=\"{$thStyle}\">Название</th>"
            . "<th style=\"{$thStyle}\">Цена</th>"
            . "<th style=\"{$thStyle}\">Кол-во</th>"
            . '</tr></thead>';

        $tbody = '<tbody>';
        foreach ($orderResult as $item) {
            $tbody .= '<tr>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:center;width:70px;white-space:nowrap;">'
                    . htmlspecialchars((string) ($item['catalogNumber'] ?? '-')) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:left;">'
                    . htmlspecialchars((string) ($item['name'] ?? '')) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:center;width:90px;white-space:nowrap;">'
                    . htmlspecialchars((string) ($item['price'] ?? 0)) . ' руб.</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:center;width:70px;white-space:nowrap;">'
                    . htmlspecialchars((string) ($item['num'] ?? 0)) . '</td>'
                . '</tr>';
        }
        $tbody .= '</tbody>';

        $discountRow = '';
        if ($discountAmount > 0.0) {
            $discountRow = '<tr style="color:#ba385c;">'
                . '<td colspan="3" style="padding:8px;border:1px solid #ddd;">Скидка по купону:</td>'
                . '<td style="padding:8px;border:1px solid #ddd;">−'
                    . number_format($discountAmount, 2, '.', '') . ' руб.</td>'
                . '</tr>';
        }

        $tfoot = '<tfoot>' . $discountRow
            . '<tr style="font-weight:bold;background:#f9f9f9;">'
            . '<td colspan="3" style="padding:8px;border:1px solid #ddd;">Итого к оплате:</td>'
            . '<td style="padding:8px;border:1px solid #ddd;">'
                . number_format($totalSum, 2, '.', '') . ' руб.</td>'
            . '</tr></tfoot>';

        return '<table style="border-collapse:collapse;margin-top:20px;width:100%;">'
            . $thead . $tbody . $tfoot . '</table>';
    }

    private static function tableRow(string $cells): string
    {
        return '<tr>' . $cells . '</tr>';
    }
}
