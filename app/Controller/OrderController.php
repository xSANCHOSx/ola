<?php

declare(strict_types=1);

class OrderController
{
    private ?PDO $pdo;
    private array $cfg;

    public function __construct(?PDO $pdo, array $cfg)
    {
        $this->pdo = $pdo;
        $this->cfg = $cfg;
    }

    public function handle(array $payload, array $orderResult): array
    {
        $counterFile = $this->cfg['fallback_counter_file'] ?? (__DIR__ . '/../../counter.txt');

        // ── 1. Верификация цен + купон ────────────────────────────────────────

        OlaLogger::debug('PRICE_VERIFY_START', ['items' => count($orderResult)]);
        $priceService = new PriceService();
        [$baseTotal, $priceVerified] = $priceService->verify($this->pdo, $orderResult);

        // Предварительная валидация купона (без FOR UPDATE — просто проверка)
        $couponData     = null;
        $discountAmount = 0.0;
        $totalSum       = $baseTotal;
        $couponCode     = $payload['coupon'];

        if ($couponCode !== '' && $this->pdo instanceof PDO) {
            // $forUpdate = false: здесь транзакции еще нет, просто проверяем
            $validation = validate_coupon_for_order($this->pdo, $couponCode, $baseTotal, false);
            if ($validation['valid']) {
                $couponData     = $validation['coupon'];
                $discountAmount = calculate_discount_amount($couponData, $baseTotal);
                $totalSum       = max(0.0, $baseTotal - $discountAmount);
                OlaLogger::info('COUPON_APPLIED', [
                    'code'     => $couponCode,
                    'discount' => $discountAmount,
                    'before'   => $baseTotal,
                    'after'    => $totalSum,
                ]);
            } else {
                OlaLogger::warn('COUPON_NOT_VALID', [
                    'code'  => $couponCode,
                    'error' => $validation['error'],
                ]);
            }
        } elseif ($couponCode !== '') {
            OlaLogger::warn('COUPON_SKIP_NO_DB', ['code' => $couponCode]);
        }

        OlaLogger::info('PRICE_VERIFY_DONE', [
            'base_total'     => $baseTotal,
            'discount'       => $discountAmount,
            'total'          => $totalSum,
            'price_verified' => $priceVerified,
            'coupon'         => $couponCode ?: '(none)',
        ]);

        // ── 2. Номер заказа ───────────────────────────────────────────────

        OlaLogger::debug('ORDER_NUMBER_START');
        $orderNumberService = new OrderNumberService();
        $orderNumber        = $orderNumberService->getNext($this->pdo, $counterFile);

        if ($orderNumber === null) {
            OlaLogger::error('ORDER_NUMBER_FAIL', ['counter_file' => $counterFile]);
            dev_log_runtime('Order number generation fully failed');
            http_response_code(500);
            echo json_encode(['error' => 'Не удалось сгенерировать номер заказа']);
            exit;
        }

        OlaLogger::info('ORDER_NUMBER_OK', ['order_number' => $orderNumber]);
        $_POST['ORDER_ID']    = $orderNumber;
        $_SESSION['order_id'] = $orderNumber;
        // Сохраняем данные купона в сессии для success.php
        $_SESSION['coupon_code']     = $couponCode;
        $_SESSION['discount_amount'] = $discountAmount;
        $_SESSION['base_total']      = $baseTotal;

        // ── 3. Сохранение в БД ────────────────────────────────────────────────

        $dbSaved   = false;
        $dbOrderId = null;

        if ($this->pdo instanceof PDO) {
            OlaLogger::debug('DB_SAVE_START', ['order_number' => $orderNumber]);
            $dbOrderId = $this->saveToDb(
                $payload, $orderResult, $orderNumber,
                $totalSum, $priceVerified, $couponData, $discountAmount
            );
            $dbSaved = $dbOrderId !== null;
            OlaLogger::info('DB_SAVE_DONE', ['db_order_id' => $dbOrderId, 'saved' => $dbSaved]);
        } else {
            OlaLogger::warn('DB_SAVE_SKIP', ['reason' => 'pdo_is_null']);
        }

        // ── 4. Отправка уведомлений ────────────────────────────────────────────

        $subject = EmailView::buildSubject($orderNumber);
        OlaLogger::debug('NOTIFICATION_START', ['subject' => $subject]);

        $notifier = new NotificationService();
        $sent     = $notifier->send(
            $payload, $orderResult, $totalSum, $subject, $orderNumber,
            $baseTotal, $discountAmount
        );

        OlaLogger::info('NOTIFICATION_DONE', [
            'email' => $sent['email'],
            'crm'   => $sent['crm'],
            'amo'   => $sent['amo'],
        ]);

        // ── 5. Обновление статусов отправки в БД ─────────────────────────────

        if ($this->pdo instanceof PDO && $dbSaved && $dbOrderId) {
            OlaLogger::debug('OUTBOUND_STATUS_UPDATE', ['db_order_id' => $dbOrderId]);
            OrderModel::updateOutboundStatus($this->pdo, $dbOrderId, $sent['email'], $sent['crm'], $sent['amo']);
        }

        // ── 6. Результат ──────────────────────────────────────────────────────

        $success = $dbSaved || $sent['email'];

        OlaLogger::info('ORDER_RESULT', [
            'success'      => $success,
            'db_saved'     => $dbSaved,
            'email_sent'   => $sent['email'],
            'order_number' => $orderNumber,
        ]);

        if (!$success) {
            OlaLogger::error('ORDER_FULLY_FAILED', [
                'order_number' => $orderNumber,
                'db_saved'     => $dbSaved,
                'email'        => $sent['email'],
                'crm'          => $sent['crm'],
                'amo'          => $sent['amo'],
            ]);
        }

        return ['success' => $success];
    }

    private function saveToDb(
        array  $payload,
        array  $orderResult,
        int    $orderNumber,
        float  $totalSum,
        bool   $priceVerified,
        ?array $couponData     = null,
        float  $discountAmount = 0.0
    ): ?int {
        try {
            $this->pdo->beginTransaction();

            $idempotency = $payload['client_order_uuid'] !== '' ? $payload['client_order_uuid'] : null;

            // Возвращаем существующий ID заказа.
            // Откатываем транзакцию, но flow не прерываем — логирование и
            // уведомления в handle() продолжаются нормально.
            if ($idempotency && $existing = OrderModel::findByIdempotencyKey($this->pdo, $idempotency)) {
                OlaLogger::warn('IDEMPOTENCY_DUPLICATE', ['key' => $idempotency]);
                $this->pdo->rollBack();
                return (int)$existing['id'];
            }

            // Проверяем купон снова внутри транзакции с FOR UPDATE.
            // Теперь блокировка действительно срабатывает — race condition устранена.
            $finalCouponData     = $couponData;
            $finalDiscountAmount = $discountAmount;
            $finalTotalSum       = $totalSum;

            if ($couponData !== null && isset($couponData['code'])) {
                $baseTotal = (float)($_SESSION['base_total'] ?? ($totalSum + $discountAmount));
                $validation = validate_coupon_for_order($this->pdo, $couponData['code'], $baseTotal, true);

                if (!$validation['valid']) {
                    // Купон уже использован пока мы считали — сбрасываем скидку
                    OlaLogger::warn('COUPON_RACE_CONDITION', [
                        'code'  => $couponData['code'],
                        'error' => $validation['error'],
                    ]);
                    $finalCouponData     = null;
                    $finalDiscountAmount = 0.0;
                    $finalTotalSum       = $baseTotal;
                } else {
                    $finalCouponData = $validation['coupon'];
                }
            }

            OlaLogger::debug('CUSTOMER_UPSERT_START', ['email' => $payload['email'], 'phone' => $payload['phone']]);
            $customerId = CustomerModel::upsert($this->pdo, $payload, $orderNumber, $finalTotalSum);
            OlaLogger::debug('CUSTOMER_UPSERT_DONE', ['customer_id' => $customerId]);

            OlaLogger::debug('ORDER_INSERT_START', ['order_number' => $orderNumber, 'total' => $finalTotalSum]);
            $dbOrderId = OrderModel::save(
                $this->pdo, $orderNumber, $customerId,
                $payload, $finalTotalSum, $priceVerified, $idempotency,
                $finalCouponData ? (int)$finalCouponData['id'] : null,
                $finalDiscountAmount
            );
            OlaLogger::debug('ORDER_INSERT_DONE', ['db_order_id' => $dbOrderId]);

            if ($orderResult) {
                OlaLogger::debug('ORDER_ITEMS_INSERT', ['count' => count($orderResult)]);
                OrderModel::saveItems($this->pdo, $dbOrderId, $orderResult);
            }

            $this->pdo->commit();
            OlaLogger::info('TRANSACTION_COMMITTED', ['db_order_id' => $dbOrderId]);

            // Атомарная запись использования купона после commit основной транзакции.
            // log_coupon_usage_atomic() открывает собственную транзакцию: BEGIN → INSERT → UPDATE → COMMIT
            if ($finalCouponData !== null && $finalDiscountAmount > 0.0) {
                $atomicOk = log_coupon_usage_atomic(
                    $this->pdo,
                    (int)$finalCouponData['id'],
                    $dbOrderId,
                    $finalDiscountAmount,
                    $customerId
                );
                OlaLogger::info('COUPON_USAGE_LOGGED', [
                    'coupon_id'       => $finalCouponData['id'],
                    'order_id'        => $dbOrderId,
                    'discount_amount' => $finalDiscountAmount,
                    'atomic_ok'       => $atomicOk,
                ]);
            }

            return $dbOrderId;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            OlaLogger::error('DB_SAVE_EXCEPTION', [
                'msg'   => $e->getMessage(),
                'code'  => $e->getCode(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 600),
            ]);
            dev_log_runtime('DB order save failed: ' . $e->getMessage());

            return null;
        }
    }
}
