<?php

declare(strict_types=1);

/**
 * CustomerModel — безопасный upsert клиента
 *
 * Две отдельные подзадачи:
 *
 *   A) Поиск: вместо одного SELECT с OR используем два отдельных SELECT
 *      с явным приоритетом: телефон > email.
 *      Причина: OR с LIMIT 1 в MySQL выбирает первую найденную строку без гарантии,
 *      какой именно клиент будет выбран — зависит от порядка internal storage.
 *      Если телефон соответствует клиенту А, а email — клиенту Б (data drift),
 *      старый код мог обновить случайного клиента.
 *
 *   B) UPDATE: вместо WHERE phone OR email (LIMIT 1) — UPDATE WHERE id = :id.
 *      Обновление всегда бьет конкретную строку найденного клиента.
 *      Исключает сценарий "обновил не того".
 */
class CustomerModel
{
    public static function upsert(PDO $pdo, array $payload, int $orderNumber, float $total): ?int
    {
        $phoneNorm = self::normalizePhone((string)$payload['phone']);
        $emailNorm = mb_strtolower(trim((string)$payload['email']));

        OlaLogger::debug('CUSTOMER_UPSERT_KEYS', [
            'phone_norm' => $phoneNorm,
            'email_norm' => $emailNorm,
        ]);

        // ── Sub-task A: Двухэтапный поиск с приоритетом ────────────────────────
        $existing = self::resolveExisting($pdo, $phoneNorm, $emailNorm);

        if ($existing !== null) {
            // ── Sub-task B: UPDATE по id — всегда точный ──────────────────────
            self::updateById($pdo, $existing['id'], $payload, $phoneNorm, $emailNorm, $orderNumber, $total);
            OlaLogger::info('CUSTOMER_UPDATED', ['id' => $existing['id']]);
            return $existing['id'];
        }

        // ── Новый клиент — INSERT ──────────────────────────────────────────────
        return self::insertNew($pdo, $payload, $phoneNorm, $emailNorm, $orderNumber);
    }

    /**
     * Sub-task A: найти существующего клиента с приоритетом телефон > email.
     *
     * Если телефон и email соответствуют РАЗНЫМ клиентам (data drift) —
     * логируем предупреждение и возвращаем клиента по телефону.
     * Телефон является более надежным идентификатором: он уникален для человека,
     * тогда как email иногда передается другому (семейная почта, рабочая).
     */
    private static function resolveExisting(PDO $pdo, string $phoneNorm, string $emailNorm): ?array
    {
        $byPhone = null;
        $byEmail = null;

        if ($phoneNorm !== '') {
            $stmt = $pdo->prepare(
                'SELECT id, phone_normalized, email_normalized
                 FROM customers WHERE phone_normalized = :p LIMIT 1'
            );
            $stmt->execute(['p' => $phoneNorm]);
            $byPhone = $stmt->fetch() ?: null;
        }

        if ($emailNorm !== '') {
            $stmt = $pdo->prepare(
                'SELECT id, phone_normalized, email_normalized
                 FROM customers WHERE email_normalized = :e LIMIT 1'
            );
            $stmt->execute(['e' => $emailNorm]);
            $byEmail = $stmt->fetch() ?: null;
        }

        // Оба найдены и это РАЗНЫЕ клиенты → data drift, приоритет: телефон
        if ($byPhone && $byEmail && (int)$byPhone['id'] !== (int)$byEmail['id']) {
            OlaLogger::warn('CUSTOMER_AMBIGUOUS_MATCH', [
                'by_phone_id'    => $byPhone['id'],
                'by_phone_email' => $byPhone['email_normalized'],
                'by_email_id'    => $byEmail['id'],
                'by_email_phone' => $byEmail['phone_normalized'],
                'decision'       => 'phone_wins',
            ]);
            return $byPhone;
        }

        return $byPhone ?? $byEmail;
    }

    /**
     * Sub-task B: обновить конкретного клиента по id.
     * WHERE id = :id — никаких OR, никаких LIMIT 1, никакого шанса попасть не туда.
     */
    private static function updateById(
        PDO    $pdo,
        int    $customerId,
        array  $payload,
        string $phoneNorm,
        string $emailNorm,
        int    $orderNumber,
        float  $total
    ): void {
        $stmt = $pdo->prepare(
            'UPDATE customers
             SET full_name         = :full_name,
                 email             = :email,
                 phone             = :phone,
                 contact_method    = :contact_method,
                 contact_username  = :contact_username,
                 phone_normalized  = :phone_norm,
                 email_normalized  = :email_norm,
                 last_order_number = :order_no,
                 orders_count      = orders_count + 1,
                 total_spent       = total_spent + :total_spent,
                 last_order_at     = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'full_name'       => $payload['name'],
            'email'           => $payload['email'],
            'phone'           => $payload['phone'],
            'contact_method'  => $payload['contact_method'],
            'contact_username'=> $payload['contact_username'],
            'phone_norm'      => $phoneNorm,
            'email_norm'      => $emailNorm,
            'order_no'        => $orderNumber,
            'total_spent'     => $total,
            'id'              => $customerId,
        ]);

        OlaLogger::debug('CUSTOMER_UPDATE_BY_ID', [
            'id'            => $customerId,
            'rows_affected' => $stmt->rowCount(),
        ]);
    }

    /**
     * Вставить нового клиента.
     * INSERT IGNORE оставляем как safety net против race condition:
     * если между resolveExisting() и insert() другой процесс успел вставить
     * того же клиента — INSERT IGNORE молча пропустит, а мы сделаем SELECT.
     */
    private static function insertNew(
        PDO    $pdo,
        array  $payload,
        string $phoneNorm,
        string $emailNorm,
        int    $orderNumber
    ): ?int {
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
        $stmt->execute([
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

        $insertId   = (int)$pdo->lastInsertId();
        $rowsInserted = $stmt->rowCount();
        OlaLogger::debug('CUSTOMER_INSERT', [
            'rows_inserted' => $rowsInserted,
            'last_insert_id'=> $insertId,
        ]);

        if ($insertId > 0) {
            OlaLogger::info('CUSTOMER_CREATED', ['id' => $insertId]);
            return $insertId;
        }

        // INSERT IGNORE сработал молча — найти кто успел вставить
        OlaLogger::warn('CUSTOMER_INSERT_RACE', [
            'phone_norm' => $phoneNorm,
            'email_norm' => $emailNorm,
        ]);
        $fallback = self::resolveExisting($pdo, $phoneNorm, $emailNorm);
        $id = $fallback ? (int)$fallback['id'] : null;
        OlaLogger::info('CUSTOMER_ID_FALLBACK', ['id' => $id]);
        return $id;
    }

    private static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
