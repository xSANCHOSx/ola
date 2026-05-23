<?php

use PHPUnit\Framework\TestCase;

class OrderModelTest extends TestCase
{
    private PDO $pdo;
    private PDOStatement $stmt;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);
    }

    // ── Tests for save() method ────────────────────────────────────────────

    public function testSaveOrderWithAllParameters(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+380501234567',
            'contact_method' => 'phone',
            'contact_username' => 'john_doe',
            'comments' => 'Kyiv, Shevchenko Ave 10',
            'coupon' => 'SAVE10',
        ];

        $orderResult = [
            ['id' => 'prod1', 'name' => 'Product 1', 'price' => 100.0, 'num' => 2],
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute');

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('999');

        $result = OrderModel::save(
            $this->pdo,
            1001,
            123,
            $payload,
            200.0,
            true,
            'idempotency-key-123',
            456,
            50.0
        );

        $this->assertEquals(999, $result);
    }

    public function testSaveOrderWithoutCoupon(): void
    {
        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+380509876543',
            'contact_method' => 'email',
            'contact_username' => 'jane_doe',
            'comments' => 'Lviv, Svobody Ave 5',
            'coupon' => '',
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute');

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('1000');

        $result = OrderModel::save(
            $this->pdo,
            1002,
            124,
            $payload,
            150.0,
            true,
            'idempotency-key-124',
            null,
            0.0
        );

        $this->assertEquals(1000, $result);
    }

    public function testSaveOrderWithoutCustomerId(): void
    {
        $payload = [
            'name' => 'Anonymous',
            'email' => 'anon@example.com',
            'phone' => '+380505555555',
            'contact_method' => 'phone',
            'contact_username' => 'anon_user',
            'comments' => 'Odesa, Derybasivska St',
            'coupon' => '',
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute');

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('1001');

        $result = OrderModel::save(
            $this->pdo,
            1003,
            null,
            $payload,
            300.0,
            false,
            'idempotency-key-125',
            null,
            0.0
        );

        $this->assertEquals(1001, $result);
    }

    public function testSaveOrderWithoutIdempotencyKey(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+380501111111',
            'contact_method' => 'phone',
            'contact_username' => 'test_user',
            'comments' => 'Test address',
            'coupon' => '',
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute');

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('1002');

        $result = OrderModel::save(
            $this->pdo,
            1004,
            125,
            $payload,
            100.0,
            true,
            null,
            null,
            0.0
        );

        $this->assertEquals(1002, $result);
    }

    // ── Tests for saveItems() method ───────────────────────────────────────

    public function testSaveItemsWithMultipleProducts(): void
    {
        $items = [
            [
                'id' => 'prod1',
                'catalogNumber' => 'CAT-001',
                'name' => 'Product 1',
                'price' => 100.0,
                'num' => 2,
            ],
            [
                'id' => 'prod2',
                'catalogNumber' => 'CAT-002',
                'name' => 'Product 2',
                'price' => 50.0,
                'num' => 1,
            ],
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(2))
            ->method('execute');

        OrderModel::saveItems($this->pdo, 999, $items);

        // If no exception, test passes
        $this->assertTrue(true);
    }

    public function testSaveItemsWithMissingFields(): void
    {
        $items = [
            [
                'id' => 'prod1',
                // Missing catalogNumber, name, price, num
            ],
            [
                'catalogNumber' => 'CAT-002',
                'name' => 'Product 2',
                // Missing id, price, num
            ],
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(2))
            ->method('execute');

        OrderModel::saveItems($this->pdo, 1000, $items);

        // Should handle missing fields gracefully
        $this->assertTrue(true);
    }

    public function testSaveItemsWithEmptyArray(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->never())
            ->method('execute');

        OrderModel::saveItems($this->pdo, 1001, []);

        $this->assertTrue(true);
    }

    // ── Tests for updateOutboundStatus() method ────────────────────────────

    public function testUpdateOutboundStatusAllTrue(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute')
            ->with([
                'email_sent' => 1,
                'crm_sent' => 1,
                'amo_sent' => 1,
                'id' => 999,
            ]);

        OrderModel::updateOutboundStatus($this->pdo, 999, true, true, true);

        $this->assertTrue(true);
    }

    public function testUpdateOutboundStatusAllFalse(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute')
            ->with([
                'email_sent' => 0,
                'crm_sent' => 0,
                'amo_sent' => 0,
                'id' => 1000,
            ]);

        OrderModel::updateOutboundStatus($this->pdo, 1000, false, false, false);

        $this->assertTrue(true);
    }

    public function testUpdateOutboundStatusMixed(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute')
            ->with([
                'email_sent' => 1,
                'crm_sent' => 0,
                'amo_sent' => 1,
                'id' => 1001,
            ]);

        OrderModel::updateOutboundStatus($this->pdo, 1001, true, false, true);

        $this->assertTrue(true);
    }

    public function testUpdateOutboundStatusHandlesException(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute')
            ->willThrowException(new Exception('DB Error'));

        // Should not throw, just log
        OrderModel::updateOutboundStatus($this->pdo, 1002, true, true, true);

        $this->assertTrue(true);
    }

    // ── Tests for findByIdempotencyKey() method ────────────────────────────

    public function testFindByIdempotencyKeyFound(): void
    {
        $key = 'idempotency-key-123';
        $expectedResult = [
            'id' => 999,
            'order_number' => 1001,
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute')
            ->with(['key' => $key]);

        $this->stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedResult);

        $result = OrderModel::findByIdempotencyKey($this->pdo, $key);

        $this->assertEquals($expectedResult, $result);
    }

    public function testFindByIdempotencyKeyNotFound(): void
    {
        $key = 'non-existent-key';

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute')
            ->with(['key' => $key]);

        $this->stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $result = OrderModel::findByIdempotencyKey($this->pdo, $key);

        $this->assertNull($result);
    }

    public function testFindByIdempotencyKeyWithEmptyKey(): void
    {
        $key = '';

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute')
            ->with(['key' => $key]);

        $this->stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $result = OrderModel::findByIdempotencyKey($this->pdo, $key);

        $this->assertNull($result);
    }

    public function testFindByIdempotencyKeyMultipleResults(): void
    {
        $key = 'duplicate-key';
        $firstResult = [
            'id' => 999,
            'order_number' => 1001,
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute')
            ->with(['key' => $key]);

        // LIMIT 1 ensures only first result
        $this->stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($firstResult);

        $result = OrderModel::findByIdempotencyKey($this->pdo, $key);

        $this->assertEquals($firstResult, $result);
    }
}
