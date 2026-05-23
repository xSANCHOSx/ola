<?php

use PHPUnit\Framework\TestCase;

class CustomerModelTest extends TestCase
{
    private PDO $pdo;
    private PDOStatement $stmt;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);
    }

    // ── Tests for upsert() - new customer ──────────────────────────────────

    public function testUpsertCreatesNewCustomer(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+380501234567',
            'contact_method' => 'phone',
            'contact_username' => 'john_doe',
        ];

        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(3))
            ->method('execute');

        $this->stmt->expects($this->exactly(2))
            ->method('fetch')
            ->willReturn(false); // No existing customer

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('123');

        $this->stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = CustomerModel::upsert($this->pdo, $payload, 1001, 500.0);

        $this->assertEquals(123, $result);
    }

    public function testUpsertUpdatesExistingCustomerByPhone(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+380501234567',
            'contact_method' => 'phone',
            'contact_username' => 'john_doe',
        ];

        $existingCustomer = [
            'id' => 456,
            'phone_normalized' => '380501234567',
            'email_normalized' => 'old@example.com',
        ];

        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(3))
            ->method('execute');

        $this->stmt->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($existingCustomer, false);

        $result = CustomerModel::upsert($this->pdo, $payload, 1002, 750.0);

        $this->assertEquals(456, $result);
    }

    public function testUpsertNormalizesPhoneNumber(): void
    {
        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+38 (050) 123-45-67',
            'contact_method' => 'email',
            'contact_username' => 'jane_doe',
        ];

        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(3))
            ->method('execute');

        $this->stmt->expects($this->exactly(2))
            ->method('fetch')
            ->willReturn(false);

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('789');

        $this->stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = CustomerModel::upsert($this->pdo, $payload, 1003, 300.0);

        $this->assertEquals(789, $result);
    }

    public function testUpsertNormalizesEmail(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => '  TEST@EXAMPLE.COM  ',
            'phone' => '',
            'contact_method' => 'email',
            'contact_username' => 'test',
        ];

        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(2))
            ->method('execute');

        $this->stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('999');

        $this->stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = CustomerModel::upsert($this->pdo, $payload, 1004, 100.0);

        $this->assertEquals(999, $result);
    }

    public function testUpsertWithEmptyPhone(): void
    {
        $payload = [
            'name' => 'No Phone User',
            'email' => 'nophone@example.com',
            'phone' => '',
            'contact_method' => 'email',
            'contact_username' => 'nophone',
        ];

        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(2))
            ->method('execute');

        $this->stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('666');

        $this->stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = CustomerModel::upsert($this->pdo, $payload, 1007, 50.0);

        $this->assertEquals(666, $result);
    }

    public function testUpsertWithEmptyEmail(): void
    {
        $payload = [
            'name' => 'No Email User',
            'email' => '',
            'phone' => '+380504444444',
            'contact_method' => 'phone',
            'contact_username' => 'noemail',
        ];

        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(2))
            ->method('execute');

        $this->stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('777');

        $this->stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = CustomerModel::upsert($this->pdo, $payload, 1008, 75.0);

        $this->assertEquals(777, $result);
    }

    public function testUpsertWithBothPhoneAndEmailEmpty(): void
    {
        $payload = [
            'name' => 'Anonymous User',
            'email' => '',
            'phone' => '',
            'contact_method' => 'other',
            'contact_username' => 'anon',
        ];

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->once())
            ->method('execute');

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('888');

        $this->stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = CustomerModel::upsert($this->pdo, $payload, 1009, 25.0);

        $this->assertEquals(888, $result);
    }

    public function testUpsertIncrementOrdersCount(): void
    {
        $payload = [
            'name' => 'Repeat Customer',
            'email' => 'repeat@example.com',
            'phone' => '+380506666666',
            'contact_method' => 'phone',
            'contact_username' => 'repeat',
        ];

        $existingCustomer = [
            'id' => 500,
            'phone_normalized' => '380506666666',
            'email_normalized' => 'repeat@example.com',
        ];

        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn($this->stmt);

        $this->stmt->expects($this->exactly(3))
            ->method('execute');

        $this->stmt->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($existingCustomer, false);

        $result = CustomerModel::upsert($this->pdo, $payload, 2001, 250.0);

        $this->assertEquals(500, $result);
    }
}
