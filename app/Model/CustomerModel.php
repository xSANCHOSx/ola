<?php

declare(strict_types=1);

class CustomerModel
{
    public static function upsert(PDO $pdo, array $payload, int $orderNumber, float $total): ?int
    {
        $phoneNorm = self::normalizePhone((string) $payload['phone']);
        $emailNorm = mb_strtolower(trim((string) $payload['email']));

        OlaLogger::debug('CUSTOMER_UPSERT_KEYS', [
            'phone_norm' => $phoneNorm,
            'email_norm' => $emailNorm,
        ]);

        // INSERT IGNORE — новий клієнт
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO customers
                (full_name, email, phone, contact_method, contact_username,
                 phone_normalized, email_normalized,
                 first_order_number, last_order_number,
                 orders_count, total_spent, last_order_at)
             VALUES
                (:full_name, :email, :phone, :contact_method, :contact_username,
                 :phone_norm, :email_norm,
                 :first_order_no, :last_order_no,
                 0, 0, NOW())'
        );
        $insertResult = $stmt->execute([
            'full_name'       => $payload['name'],
            'email'           => $payload['email'],
            'phone'           => $payload['phone'],
            'contact_method'  => $payload['contact_method'],
            'contact_username'=> $payload['contact_username'],
            'phone_norm'      => $phoneNorm,
            'email_norm'      => $emailNorm,
            'first_order_no'  => $orderNumber,
            'last_order_no'   => $orderNumber,
        ]);
        $rowsInserted = $stmt->rowCount();
        OlaLogger::debug('CUSTOMER_INSERT_IGNORE', ['rows_inserted' => $rowsInserted]);

        // UPDATE — завжди оновлюємо лічильники
        $updateStmt = $pdo->prepare(
            'UPDATE customers
             SET full_name          = :full_name,
                 email              = :email,
                 phone              = :phone,
                 contact_method     = :contact_method,
                 contact_username   = :contact_username,
                 phone_normalized   = :phone_norm,
                 email_normalized   = :email_norm,
                 last_order_number  = :order_no,
                 orders_count       = orders_count + 1,
                 total_spent        = total_spent + :total_spent,
                 last_order_at      = NOW()
             WHERE phone_normalized = :phone_norm
                OR email_normalized = :email_norm
             LIMIT 1'
        );
        $updateStmt->execute([
            'full_name'       => $payload['name'],
            'email'           => $payload['email'],
            'phone'           => $payload['phone'],
            'contact_method'  => $payload['contact_method'],
            'contact_username'=> $payload['contact_username'],
            'phone_norm'      => $phoneNorm,
            'email_norm'      => $emailNorm,
            'order_no'        => $orderNumber,
            'total_spent'     => $total,
        ]);
        OlaLogger::debug('CUSTOMER_UPDATE', ['rows_affected' => $updateStmt->rowCount()]);

        // SELECT id
        $selectStmt = $pdo->prepare(
            'SELECT id FROM customers
             WHERE phone_normalized = :phone_norm
                OR email_normalized = :email_norm
             LIMIT 1'
        );
        $selectStmt->execute(['phone_norm' => $phoneNorm, 'email_norm' => $emailNorm]);
        $row = $selectStmt->fetch();

        $id = $row ? (int) $row['id'] : null;
        OlaLogger::info('CUSTOMER_ID', ['id' => $id]);

        return $id;
    }

    private static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
