<?php

declare(strict_types=1);

final class PreorderRequestException extends RuntimeException
{
    private int $httpStatus;

    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

final class PreorderRequestService
{
    /**
     * @return array{request_id:int,email_sent:bool}
     */
    public function create(PDO $pdo, PreorderRequestData $data): array
    {
        $product = $this->findEligibleProduct($pdo, $data->productExternalId());
        if (!$product) {
            throw new PreorderRequestException('Товар больше недоступен для заявки.', 404);
        }

        if ($this->hasRecentDuplicate($pdo, (string)$product['external_id'], $data->phone())) {
            throw new PreorderRequestException('Такая заявка уже была отправлена. Мы свяжемся с вами.', 409);
        }

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT INTO preorder_requests
                    (product_id, product_external_id, product_name_snapshot, product_cat_number_snapshot,
                     customer_name, customer_phone, customer_email)
                 VALUES
                    (:product_id, :product_external_id, :product_name_snapshot, :product_cat_number_snapshot,
                     :customer_name, :customer_phone, :customer_email)'
            );
            $insert->execute([
                'product_id' => (int)$product['id'],
                'product_external_id' => (string)$product['external_id'],
                'product_name_snapshot' => (string)$product['name'],
                'product_cat_number_snapshot' => $product['cat_number'] !== null ? (string)$product['cat_number'] : null,
                'customer_name' => $data->name(),
                'customer_phone' => $data->phone(),
                'customer_email' => $data->email() !== '' ? $data->email() : null,
            ]);
            $requestId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $emailSent = $this->sendNotification($requestId, $product, $data);
        return ['request_id' => $requestId, 'email_sent' => $emailSent];
    }

    private function findEligibleProduct(PDO $pdo, string $externalId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, external_id, name, cat_number
             FROM products
             WHERE external_id = :external_id AND is_visible = 1 AND status = "preorder"
             LIMIT 1'
        );
        $stmt->execute(['external_id' => $externalId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product ?: null;
    }

    private function hasRecentDuplicate(PDO $pdo, string $productExternalId, string $phone): bool
    {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM preorder_requests
             WHERE product_external_id = :product_external_id
               AND customer_phone = :customer_phone
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'product_external_id' => $productExternalId,
            'customer_phone' => $phone,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    private function sendNotification(int $requestId, array $product, PreorderRequestData $data): bool
    {
        $recipients = trim((string)(dev_app_config()['order_notification_emails'] ?? ''));
        $subject = 'Заявка «Под заказ» #' . $requestId . ' — ' . (string)$product['name'];
        $html = '<h2>Новая заявка «Под заказ»</h2>'
            . '<p><strong>Заявка:</strong> #' . $requestId . '</p>'
            . '<p><strong>Товар:</strong> ' . htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Артикул:</strong> ' . htmlspecialchars((string)($product['cat_number'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Имя:</strong> ' . htmlspecialchars($data->name(), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Телефон:</strong> ' . htmlspecialchars($data->phone(), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Email:</strong> ' . htmlspecialchars($data->email() !== '' ? $data->email() : 'не указан', ENT_QUOTES, 'UTF-8') . '</p>';
        $headers = implode("\r\n", [
            'From: no-reply@' . site_domain(),
            'Reply-To: no-reply@' . site_domain(),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ]) . "\r\n";

        if ($recipients === '') {
            dev_log_runtime('Preorder email skipped: ORDER_NOTIFY_EMAILS is empty.');
            return false;
        }

        try {
            $sent = mail($recipients, $subject, $html, $headers);
        } catch (Throwable $e) {
            dev_log_runtime('Preorder email failed: ' . $e->getMessage());
            return false;
        }
        dev_log_runtime('Preorder request created: #' . $requestId . '; email=' . ($sent ? '1' : '0'));
        return $sent;
    }
}
