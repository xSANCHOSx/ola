<?php

declare(strict_types=1);

class PriceService
{
    /**
     * Верифікує ціни товарів через БД.
     * Повертає [float $totalSum, bool $priceVerified].
     *
     * Не є блокуючою: якщо товар не знайдено або БД недоступна —
     * логуємо і використовуємо ціну клієнта як fallback.
     */
    public function verify(?PDO $pdo, array $orderResult): array
    {
        $totalSum      = 0.0;
        $priceVerified = true;

        if (!($pdo instanceof PDO) || empty($orderResult)) {
            $priceVerified = false;

            return [$this->sumFromClient($orderResult), false];
        }

        try {
            $productIds   = array_unique(array_column($orderResult, 'id'));
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));

            $stmt = $pdo->prepare(
                "SELECT external_id, price FROM products WHERE external_id IN ($placeholders)"
            );
            $stmt->execute(array_values($productIds));
            $dbPrices = array_column($stmt->fetchAll(), 'price', 'external_id');

            foreach ($orderResult as $item) {
                $pid      = (string) ($item['id'] ?? '');
                $quantity = (int) ($item['num'] ?? 0);

                if (!isset($dbPrices[$pid])) {
                    log_security_event('UNVERIFIED_PRODUCT', [
                        'id'          => $pid,
                        'client_price'=> $item['price'] ?? null,
                    ]);
                    $priceVerified = false;
                    $totalSum     += ((float) ($item['price'] ?? 0)) * $quantity;
                } else {
                    $totalSum += (float) $dbPrices[$pid] * $quantity;
                }
            }
        } catch (Throwable $e) {
            dev_log_runtime('Price verification DB error: ' . $e->getMessage());
            $priceVerified = false;
            $totalSum      = $this->sumFromClient($orderResult);
        }

        return [$totalSum, $priceVerified];
    }

    /**
     * Застосовує знижку купону до суми.
     */
    public function applyCoupon(float $totalSum, string $couponCode, array $coupons): float
    {
        if ($couponCode !== '' && isset($coupons[$couponCode])) {
            $discount = (float) $coupons[$couponCode]['discount'];
            $totalSum = max(0.0, $totalSum - $discount);
        }

        return $totalSum;
    }

    private function sumFromClient(array $orderResult): float
    {
        $total = 0.0;
        foreach ($orderResult as $item) {
            $total += ((float) ($item['price'] ?? 0)) * (int) ($item['num'] ?? 0);
        }

        return $total;
    }
}
