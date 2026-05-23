<?php

use PHPUnit\Framework\TestCase;

class PriceServiceTest extends TestCase
{
    private PriceService $service;

    protected function setUp(): void
    {
        $this->service = new PriceService();
    }

    // ── Tests for verify() method ──────────────────────────────────────────

    public function testVerifyWithNoPdoReturnsSumFromClient(): void
    {
        $orderResult = [
            ['id' => '1', 'price' => 100.0, 'num' => 2],
            ['id' => '2', 'price' => 50.0, 'num' => 1],
        ];

        [$total, $verified] = $this->service->verify(null, $orderResult);

        $this->assertEquals(250.0, $total);
        $this->assertFalse($verified);
    }

    public function testVerifyWithEmptyOrderResultReturnsFalse(): void
    {
        $pdo = $this->createMock(PDO::class);

        [$total, $verified] = $this->service->verify($pdo, []);

        $this->assertEquals(0.0, $total);
        $this->assertFalse($verified);
    }

    public function testVerifyWithValidPricesFromDb(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $orderResult = [
            ['id' => 'prod1', 'price' => 100.0, 'num' => 2],
            ['id' => 'prod2', 'price' => 50.0, 'num' => 1],
        ];

        // fetchAll() повертає масив рядків з БД
        $dbRows = [
            ['external_id' => 'prod1', 'price' => 100.0],
            ['external_id' => 'prod2', 'price' => 50.0],
        ];

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute');

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($dbRows);

        [$total, $verified] = $this->service->verify($pdo, $orderResult);

        $this->assertEquals(250.0, $total);
        $this->assertTrue($verified);
    }

    public function testVerifyWithMissingProductInDb(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $orderResult = [
            ['id' => 'prod1', 'price' => 100.0, 'num' => 2],
            ['id' => 'prod2', 'price' => 50.0, 'num' => 1],
        ];

        $dbRows = [
            ['external_id' => 'prod1', 'price' => 100.0],
            // prod2 missing
        ];

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute');

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($dbRows);

        [$total, $verified] = $this->service->verify($pdo, $orderResult);

        // Uses client price for missing product
        $this->assertEquals(250.0, $total);
        $this->assertFalse($verified);
    }

    public function testVerifyWithDatabaseException(): void
    {
        $pdo = $this->createMock(PDO::class);

        $orderResult = [
            ['id' => 'prod1', 'price' => 100.0, 'num' => 2],
        ];

        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new Exception('DB Error'));

        [$total, $verified] = $this->service->verify($pdo, $orderResult);

        // Falls back to client sum
        $this->assertEquals(200.0, $total);
        $this->assertFalse($verified);
    }

    public function testVerifyUsesDbPriceWhenAvailable(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $orderResult = [
            ['id' => 'prod1', 'price' => 100.0, 'num' => 2],
        ];

        $dbRows = [
            ['external_id' => 'prod1', 'price' => 150.0], // Different from client price
        ];

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute');

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($dbRows);

        [$total, $verified] = $this->service->verify($pdo, $orderResult);

        // Uses DB price, not client price
        $this->assertEquals(300.0, $total);
        $this->assertTrue($verified);
    }

    public function testVerifyWithMissingPriceInOrderItem(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $orderResult = [
            ['id' => 'prod1', 'num' => 2], // No price
        ];

        $dbRows = [
            ['external_id' => 'prod1', 'price' => 100.0],
        ];

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute');

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($dbRows);

        [$total, $verified] = $this->service->verify($pdo, $orderResult);

        $this->assertEquals(200.0, $total);
        $this->assertTrue($verified);
    }

    // ── Tests for applyCoupon() method ─────────────────────────────────────

    public function testApplyCouponWithValidCode(): void
    {
        $coupons = [
            'SAVE10' => ['discount' => 50.0],
            'SAVE20' => ['discount' => 100.0],
        ];

        $result = $this->service->applyCoupon(500.0, 'SAVE10', $coupons);

        $this->assertEquals(450.0, $result);
    }

    public function testApplyCouponWithInvalidCode(): void
    {
        $coupons = [
            'SAVE10' => ['discount' => 50.0],
        ];

        $result = $this->service->applyCoupon(500.0, 'INVALID', $coupons);

        $this->assertEquals(500.0, $result);
    }

    public function testApplyCouponWithEmptyCode(): void
    {
        $coupons = [
            'SAVE10' => ['discount' => 50.0],
        ];

        $result = $this->service->applyCoupon(500.0, '', $coupons);

        $this->assertEquals(500.0, $result);
    }

    public function testApplyCouponPreventNegativeTotal(): void
    {
        $coupons = [
            'HUGE' => ['discount' => 1000.0],
        ];

        $result = $this->service->applyCoupon(500.0, 'HUGE', $coupons);

        // Should not go below 0
        $this->assertEquals(0.0, $result);
    }

    public function testApplyCouponWithZeroDiscount(): void
    {
        $coupons = [
            'FREE' => ['discount' => 0.0],
        ];

        $result = $this->service->applyCoupon(500.0, 'FREE', $coupons);

        $this->assertEquals(500.0, $result);
    }

    public function testApplyCouponWithFloatDiscount(): void
    {
        $coupons = [
            'PRECISE' => ['discount' => 33.33],
        ];

        $result = $this->service->applyCoupon(100.0, 'PRECISE', $coupons);

        $this->assertEqualsWithDelta(66.67, $result, 0.01);
    }

    // ── Tests for sumFromClient() private method (via verify) ──────────────

    public function testSumFromClientWithMultipleItems(): void
    {
        $orderResult = [
            ['price' => 100.0, 'num' => 2],
            ['price' => 50.0, 'num' => 3],
            ['price' => 25.0, 'num' => 1],
        ];

        [$total, $verified] = $this->service->verify(null, $orderResult);

        // 100*2 + 50*3 + 25*1 = 200 + 150 + 25 = 375
        $this->assertEquals(375.0, $total);
    }

    public function testSumFromClientWithMissingFields(): void
    {
        $orderResult = [
            ['price' => 100.0], // No num - defaults to 0
            ['num' => 2], // No price - defaults to 0
            [], // Empty - both default to 0
        ];

        [$total, $verified] = $this->service->verify(null, $orderResult);

        // 100*0 + 0*2 + 0*0 = 0
        $this->assertEquals(0.0, $total);
    }

    public function testSumFromClientWithZeroQuantity(): void
    {
        $orderResult = [
            ['price' => 100.0, 'num' => 0],
            ['price' => 50.0, 'num' => 2],
        ];

        [$total, $verified] = $this->service->verify(null, $orderResult);

        $this->assertEquals(100.0, $total);
    }

    public function testSumFromClientWithStringPrices(): void
    {
        $orderResult = [
            ['price' => '100.50', 'num' => '2'],
            ['price' => '50.25', 'num' => '1'],
        ];

        [$total, $verified] = $this->service->verify(null, $orderResult);

        $this->assertEqualsWithDelta(251.25, $total, 0.01);
    }
}
