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

        // ── 1. Верифікація цін + купон ────────────────────────────────────────

        $priceService          = new PriceService();
        [$totalSum, $priceVerified] = $priceService->verify($this->pdo, $orderResult);
        $totalSum              = $priceService->applyCoupon(
            $totalSum,
            $payload['coupon'],
            $this->cfg['coupons'] ?? []
        );

        // ── 2. Номер замовлення ───────────────────────────────────────────────

        $orderNumberService = new OrderNumberService();
        $orderNumber        = $orderNumberService->getNext($this->pdo, $counterFile);

        if ($orderNumber === null) {
            dev_log_runtime('Order number generation fully failed');
            http_response_code(500);
            echo json_encode(['error' => 'Не вдалося згенерувати номер замовлення']);
            exit;
        }

        $_POST['ORDER_ID']    = $orderNumber;
        $_SESSION['order_id'] = $orderNumber;

        // ── 3. Збереження в БД ────────────────────────────────────────────────

        $dbSaved    = false;
        $dbOrderId  = null;

        if ($this->pdo instanceof PDO) {
            $dbSaved   = true; // буде скинуто до false при помилці
            $dbOrderId = $this->saveToDb($payload, $orderResult, $orderNumber, $totalSum, $priceVerified);
            if ($dbOrderId === null) {
                $dbSaved = false;
            }
        }

        // ── 4. Відправка сповіщень ────────────────────────────────────────────

        $subject = EmailView::buildSubject($orderNumber);

        $notifier = new NotificationService();
        $sent     = $notifier->send($payload, $orderResult, $totalSum, $subject, $orderNumber);

        // ── 5. Оновлення статусів відправки в БД ─────────────────────────────

        if ($this->pdo instanceof PDO && $dbSaved && $dbOrderId) {
            OrderModel::updateOutboundStatus(
                $this->pdo,
                $dbOrderId,
                $sent['email'],
                $sent['crm'],
                $sent['amo']
            );
        }

        // ── 6. Результат ──────────────────────────────────────────────────────

        $success = $dbSaved || $sent['email'];

        if (!$success) {
            dev_log_runtime(
                'Order fully failed: dbSaved=false, mail=false, orderNumber=' . $orderNumber
            );
        }

        return ['success' => $success];
    }

    private function saveToDb(
        array $payload,
        array $orderResult,
        int   $orderNumber,
        float $totalSum,
        bool  $priceVerified
    ): ?int {
        try {
            $this->pdo->beginTransaction();

            $idempotency = $payload['client_order_uuid'] !== '' ? $payload['client_order_uuid'] : null;

            if ($idempotency && OrderModel::findByIdempotencyKey($this->pdo, $idempotency)) {
                $this->pdo->rollBack();
                echo 'ok';
                exit;
            }

            $customerId = CustomerModel::upsert($this->pdo, $payload, $orderNumber, $totalSum);
            $dbOrderId  = OrderModel::save(
                $this->pdo,
                $orderNumber,
                $customerId,
                $payload,
                $totalSum,
                $priceVerified,
                $idempotency
            );

            if ($orderResult) {
                OrderModel::saveItems($this->pdo, $dbOrderId, $orderResult);
            }

            $this->pdo->commit();

            return $dbOrderId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            dev_log_runtime('DB order save failed: ' . $e->getMessage());

            return null;
        }
    }
}
