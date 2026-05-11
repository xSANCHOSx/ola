<?php

declare(strict_types=1);

class PriceService
{
    public function verify(?PDO $pdo, array $orderResult): array
    {
        if (!($pdo instanceof PDO) || empty($orderResult)) {
            OlaLogger::warn('PRICE_VERIFY_SKIP', [
                'reason' => !($pdo instanceof PDO) ? 'no_pdo' : 'empty_cart',
            ]);
            return [$this->sumFromClient($orderResult), false];
        }

        $totalSum      = 0.0;
        $priceVerified = true;

        try {
            $productIds   = array_unique(array_column($orderResult, 'id'));
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));

            OlaLogger::debug('PRICE_DB_QUERY', ['ids' => $productIds]);

            $stmt = $pdo->prepare(
                "SELECT external_id, price FROM products WHERE external_id IN ($placeholders)"
            );
            $stmt->execute(array_values($productIds));
            $dbPrices = array_column($stmt->fetchAll(), 'price', 'external_id');

            OlaLogger::debug('PRICE_DB_RESULT', ['found' => count($dbPrices), 'prices' => $dbPrices]);

            foreach ($orderResult as $item) {
                $pid      = (string) ($item['id']    ?? '');
                $quantity = (int)   ($item['num']   ?? 0);

                if (!isset($dbPrices[$pid])) {
                    OlaLogger::warn('PRICE_UNVERIFIED_PRODUCT', [
                        'id'           => $pid,
                        'client_price' => $item['price'] ?? null,
                        'qty'          => $quantity,
                    ]);
                    $priceVerified = false;
                    $totalSum     += ((float) ($item['price'] ?? 0)) * $quantity;
                } else {
                    $totalSum += (float) $dbPrices[$pid] * $quantity;
                }
            }
        } catch (Throwable $e) {
            OlaLogger::error('PRICE_VERIFY_EXCEPTION', ['msg' => $e->getMessage()]);
            dev_log_runtime('Price verification DB error: ' . $e->getMessage());
            $priceVerified = false;
            $totalSum      = $this->sumFromClient($orderResult);
        }

        return [$totalSum, $priceVerified];
    }

    public function applyCoupon(float $totalSum, string $couponCode, array $coupons): float
    {
        if ($couponCode !== '' && isset($coupons[$couponCode])) {
            $discount = (float) $coupons[$couponCode]['discount'];
            $after    = max(0.0, $totalSum - $discount);
            OlaLogger::info('COUPON_APPLIED', [
                'code'     => $couponCode,
                'discount' => $discount,
                'before'   => $totalSum,
                'after'    => $after,
            ]);
            return $after;
        }

        if ($couponCode !== '') {
            OlaLogger::warn('COUPON_NOT_FOUND', ['code' => $couponCode]);
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
