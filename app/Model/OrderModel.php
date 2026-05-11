<?php

declare(strict_types=1);

class OrderModel
{
    public static function save(
        PDO    $pdo,
        int    $orderNumber,
        ?int   $customerId,
        array  $payload,
        float  $totalSum,
        bool   $priceVerified,
        ?string $idempotencyKey
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO orders
                (order_number, customer_id,
                 customer_name_snapshot, customer_email_snapshot, customer_phone_snapshot,
                 contact_method_snapshot, contact_username_snapshot, delivery_address_snapshot,
                 coupon, total, price_verified, idempotency_key, raw_payload, created_at)
             VALUES
                (:order_number, :customer_id,
                 :name, :email, :phone,
                 :contact_method, :contact_username, :delivery_address,
                 :coupon, :total, :price_verified, :idempotency_key, :raw_payload, NOW())'
        );

        $params = [
            'order_number'    => $orderNumber,
            'customer_id'     => $customerId,
            'name'            => $payload['name'],
            'email'           => $payload['email'],
            'phone'           => $payload['phone'],
            'contact_method'  => $payload['contact_method'],
            'contact_username'=> $payload['contact_username'],
            'delivery_address'=> $payload['comments'],
            'coupon'          => $payload['coupon'],
            'total'           => $totalSum,
            'price_verified'  => $priceVerified ? 1 : 0,
            'idempotency_key' => $idempotencyKey,
            'raw_payload'     => json_encode($_POST, JSON_UNESCAPED_UNICODE),
        ];

        OlaLogger::debug('ORDER_INSERT_PARAMS', array_diff_key($params, ['raw_payload' => 1]));

        $stmt->execute($params);
        $id = (int) $pdo->lastInsertId();

        OlaLogger::info('ORDER_INSERTED', ['id' => $id, 'order_number' => $orderNumber]);

        return $id;
    }

    public static function saveItems(PDO $pdo, int $orderId, array $items): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, product_external_id, catalog_number, name, price, quantity)
             VALUES
                (:order_id, :product_external_id, :catalog_number, :name, :price, :quantity)'
        );

        foreach ($items as $i => $item) {
            $params = [
                'order_id'           => $orderId,
                'product_external_id'=> (string) ($item['id']           ?? ''),
                'catalog_number'     => (string) ($item['catalogNumber'] ?? '-'),
                'name'               => (string) ($item['name']          ?? ''),
                'price'              => (float)  ($item['price']         ?? 0),
                'quantity'           => (int)    ($item['num']           ?? 0),
            ];
            OlaLogger::debug('ORDER_ITEM_INSERT', ['row' => $i, 'params' => $params]);
            $stmt->execute($params);
        }

        OlaLogger::info('ORDER_ITEMS_DONE', ['order_id' => $orderId, 'count' => count($items)]);
    }

    public static function updateOutboundStatus(
        PDO  $pdo,
        int  $orderId,
        bool $emailSent,
        bool $crmSent,
        bool $amoSent
    ): void {
        try {
            $stmt = $pdo->prepare(
                'UPDATE orders
                 SET outbound_email_sent = :email_sent,
                     outbound_crm_sent   = :crm_sent,
                     outbound_amo_sent   = :amo_sent
                 WHERE id = :id'
            );
            $stmt->execute([
                'email_sent' => $emailSent ? 1 : 0,
                'crm_sent'   => $crmSent   ? 1 : 0,
                'amo_sent'   => $amoSent   ? 1 : 0,
                'id'         => $orderId,
            ]);
            OlaLogger::debug('OUTBOUND_STATUS_UPDATED', [
                'order_id' => $orderId,
                'email'    => $emailSent,
                'crm'      => $crmSent,
                'amo'      => $amoSent,
            ]);
        } catch (Throwable $e) {
            OlaLogger::error('OUTBOUND_STATUS_FAIL', ['msg' => $e->getMessage(), 'order_id' => $orderId]);
            dev_log_runtime('Order outbound status update failed: ' . $e->getMessage());
        }
    }

    public static function findByIdempotencyKey(PDO $pdo, string $key): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, order_number FROM orders WHERE idempotency_key = :key LIMIT 1'
        );
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();

        OlaLogger::debug('IDEMPOTENCY_CHECK', ['key' => $key, 'found' => (bool) $row]);

        return $row ?: null;
    }
}
